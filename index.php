<?php

// API:
//
// /bugtracker
// /bugtracker/issue    GET   POST
// /bugtracker/issue/35 GET   POST
// /bugtracker/project  GET   POST
// /bugtracker/user     GET   POST

define("URL_BASE", "/c/bugtracker2");

require_once("db.php");
require_once("Parsedown.php");

$base_len = strlen(URL_BASE);

if (strncmp(URL_BASE, $_SERVER['REQUEST_URI'], $base_len) == 0) {
  $tail = substr($_SERVER['REQUEST_URI'], $base_len + 1);
  $parts = explode("/", $tail);
  for($i = 0; $i < count($parts); $i++){
    $parts[$i] = urldecode($parts[$i]);
  }
}

switch ($parts[0]) {
  case "issue":
    if (count($parts) >= 2) {

      if ($_SERVER['REQUEST_METHOD'] == "POST") {
        updateIssue($parts[1], $_POST);
        redirect(URL_BASE . "/issue/" . $parts[1]);
      }
      else {
        $context = array("issue" => getIssue($parts[1]));
        viewIssue($context);
      }
    }
    else {
      methodUnavailable();
    }
    break;
  case "tag":
    if (count($parts) >= 2) {
      $context = array("title" => "Tag: ".$parts[1], "issues" => getIssuesByTag($parts[1]));
      viewIndex($context);
    }
    else {
      methodUnavailable();
    }
    break;
  case "user":
    if (count($parts) >= 2) {
      $context = array("title" => "User: ".$parts[1], "issues" => getIssuesByUser($parts[1]));
      viewIndex($context);
    }
    else {
      methodUnavailable();
    }
    break;
  case "project":
    // methodUnavailable();
    // break;
  default:
    $context = array("issues" => getIssues());
    viewIndex($context);
}

function methodUnavailable() {
  die("Method Unavilable");
}

function getIssues() {
  $db = dbConnect();

  $issues = dbGetIssues($db);

  foreach($issues as &$issue) {
    normalizeIssue($issue);
  }

  return $issues;
}

function getIssue($id) {
  $db = dbConnect();

  $issue = dbGetIssue($db, $id);

  normalizeIssue($issue);

  $history = dbGetIssueHistory($db, $id);
  foreach($history as &$entry){
    $entry['user'] = array(
      "email" => $entry['user_email'],
      "name" => $entry['user_name']
    );

    if ($entry['type'] == "UPDATE") {
      $entry['value'] = unserialize($entry['value']);
    }
  }
  $issue['history'] = $history;

  return $issue;
}

function getIssuesByTag($tag) {
  $issues = getIssues();
  $out = array();
  foreach($issues as $issue) {
    if (in_array($tag, $issue['tags'])) {
      $out[] = $issue;
    }
  }
  return $out;
}

function getIssuesByUser($user) {
  $issues = getIssues();
  $out = array();
  foreach($issues as $issue) {
    if ($issue['creator']['email'] == $user ||
        ($issue['assignee'] && $issue['assignee']['email'] == $user)) {
      $out[] = $issue;
    }
  }
  return $out;
}

function updateIssue($id, $fields) {
  $db = dbConnect();

  $user = "IJMacD@gmail.com";

  if (isset($fields['action'])) {
    $action = $fields['action'];
    unset($fields['action']);

    if ($action == "COMMENT") {
      dbInsertIssueHistory($db, $user, $id, $action, $fields['message']);
    }

    return;
  }

  dbUpdateIssue($db, $user, $id, $fields);
}

function getUser ($email) {
  $db = dbConnect();
  return dbGetUser($db, $email);
}

function viewIndex($context) {
  $title = isset($context['title']) ? $context['title'] : "BugTracker";
  renderHeader();
  ?>

  <h1>
    <?php echo $title ?>
    <button class="btn btn-primary">New Issue</button>
  </h1>

  <table class="table">
    <thead>
      <tr>
        <th>Title</th><th>Status</th><th>Created</th><th>Assigned To</th><th>Deadline</th><th>Tags</th>
      </tr>
    </thead>
    <tbody>
      <?php
      foreach($context['issues'] as $issue) {
        ?>
        <tr class="status-<?php echo $issue['status'] ?> <?php echo $issue['assignee'] ? "status-assigned" : "status-unassigned" ?>">
          <td><a href="<?php echo URL_BASE ?>/issue/<?php echo $issue['id'] ?>"><?php echo $issue['title'] ?></a></td>
          <td class="status"><?php echo $issue['status'] ?></td>
          <td>
            <?php echo formatUser($issue['creator']) ?>
            <div class="date"><?php echo formatDate($issue['date']) ?></div>
          </td>
          <td><?php
            if ($issue['assignee']) {
              echo formatUser($issue['assignee']);
              echo '<div class="date">'. formatDate($issue['assigned']) .'</div>';
            } else if ($issue['status'] == "open") {
              echo '<button class="btn btn-sm">Assign</button>';
            }
          ?></td>
          <td><?php echo formatDate($issue['deadline']) ?></td>
          <td><?php echo formatTags($issue['tags']) ?></td>
        </tr>
        <?php
      }
      ?>
    </tbody>
  </table>

  <?php
  renderFooter();
}

function viewIssue($context) {
  $issue = $context['issue'];
  renderHeader();
  ?>

  <h1>
  <?php
    echo $issue['title'];
    if($issue['status'] == "closed") {
      echo ' <span class="status">(Closed)</span>';
    }
  ?>
  </h1>

  <div class="row">
    <div class="col-md-9">
      <div class="description">
      <?php
        $parsedown = new Parsedown();
        echo $parsedown->text($issue['description']);
      ?>
      </div>

      <div class="history">
        <?php
        foreach($issue['history'] as $entry) {
          if ($entry['type'] == "CREATE") {
            // Skip
          }
          else {
          ?>
          <div class="history-entry">
            <div class="user">
              <?php echo formatUser($entry['user']) ?>
              <div class="date"><?php echo formatDate($entry['date']) ?></div>
            </div>
            <div class="details">
              <?php
                if($entry['type'] == "UPDATE") {
                  $updates = array();
                  foreach($entry['value'] as $field => $value) {
                    if ($field == "status") {
                      echo '<p class="status-change '.$value.'">'.($value == "open" ? 'Opened Issue' : 'Closed Issue').'</p>';
                    }
                    else if ($field == "assignee") {
                      $user = getUser($value);
                      echo '<p class="assignee-change">Assigned to: '.formatUser($user ? $user : $value).'</p>';
                    }
                    else {
                      $updates[] = "Set $field to '$value'.";
                    }
                  }
                  echo implode("<br>", $updates);
                } else if ($entry['type'] == "COMMENT") {
                  $parsedown = new Parsedown;
                  echo '<div class="message">'.$parsedown->text($entry['value']).'</div>';
                } else {
                  echo $entry['value'];
                }
              ?>
            </div>
          </div>
          <?php
          }
        }
        ?>
      </div>

      <?php
      if ($issue['status'] == "open") {
      ?>
        <div class="message">
          <div class="user">
            <?php $currentUser = array("email" => "IJMacD@gmail.com", "name" => "Iain MacDonald"); ?>
            <?php echo formatUser($currentUser); ?>
            <div class="note">Add Comment</div>
          </div>
          <form action="" method="post" class="details">
            <input type="hidden" name="action" value="COMMENT" />
            <textarea class="form-control" name="message"></textarea>
            <input type="submit" class="btn btn-primary" value="Comment" />
          </form>
        </div>
      <?php
      }
      ?>
    </div>

    <div class="col-md-3">
      <h2>Created By</h2>
      <div class="clearfix">
        <?php echo formatUser($issue['creator']) ?>
        <div class="date"><?php echo formatDate($issue['date']) ?></div>
      </div>

      <h2>Assigned To</h2>
      <div class="clearfix">
        <?php
          if ($issue['assignee']) {
            echo formatUser($issue['assignee']);
            echo '<div class="date">'. formatDate($issue['assigned']) .'</div>';
          }
          if ($issue['status'] == "open") {
            echo '<button class="btn btn-sm" data-toggle="#assign-form">'.($issue['assignee'] ? 'Re-assign' : 'Assign').'</button>';
            echo '<form id="assign-form" action="" method="post" style="display: none; margin:4px;" class="form-inline">'
                  .'<div class="input-group">'
                    .'<input type="email" class="form-control" name="assignee" value="'.($issue['assignee'] ? $issue['assignee']['email'] : '').'" />'
                    .'<input type="submit" value="Set" class="btn btn-primary" />'
                  .'</div>'
                .'</form>';
          }
        ?>
      </div>

      <h2>Status</h2>
      <div class="clearfix">
        <?php echo $issue['status']; ?><br>
        <?php
          if ($issue['status'] == "open") {
            echo '<form action="" method="post"><input type="hidden" name="status" value="closed" /><input type="submit" class="btn btn-sm btn-danger" value="Close Issue" /></form>';
          }
          else {
            echo '<form action="" method="post"><input type="hidden" name="status" value="open" /><input type="submit" class="btn btn-sm btn-secondary" value="Re-open Issue" /></form>';
          }
        ?>
      </div>

      <h2>Tags</h2>
      <div class="clearfix">
        <?php echo formatTags($issue['tags']) ?>
      </div>

    </div>
  </div>

  <?php
  renderFooter();
}

function formatDate($timestamp) {
  if (!$timestamp) return "";
  return date("Y-m-d H:i:s", $timestamp);
}

/**
 * formatUser(array("email" => "foo@example.com", "name" => "Foo Bar"))
 * formatUser("foo@example.com")
 */
function formatUser($user){
  if (!$user) return;

  if (is_array($user)) {
    $email = $user['email'];
    $name = $user['name'] ? $user['name'] : $email;
  }
  else {
    $email = $name = $user;
  }

  $default = "identicon";
  $size = 48;
  $grav_url = "https://www.gravatar.com/avatar/" . md5( strtolower( trim( $email ) ) ) . "?d=" . urlencode( $default ) . "&s=" . $size;
  return '<div class="avatar" style="background-image: url('.$grav_url.')"></div>'
  .'<a href="'.URL_BASE.'/user/'.$email.'" class="name">'
    .$name
  .'</a>';
}

function formatTags($tags) {
  $out = array();
  foreach($tags as $tag) {
    $bg = substr(md5($tag), 0, 6);
    $out[] = '<a href="'.URL_BASE.'/tag/'.$tag.'" class="badge" style="background: #'.$bg.'">'.$tag.'</a>';
  }
  return implode(" ", $out);
}

function renderHeader() {
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
    <title>BugTracker</title>
    <style>
    h1 .status {
      color: #999;
    }
    .status-open {
    }
    .status-open.status-unassigned {
      font-weight: bold;
    }
    .status-open.status-assigned .status {
      color: #633;
    }
    .status-closed td,
    .status-closed a {
      background: #eee;
      color: #999;
    }
    .status-closed .avatar,
    .status-closed .badge {
      opacity: 0.25;
    }
    .avatar {
      background-size: cover;
      height: 32px;
      width: 32px;
      display: inline-block;
      margin: 4px;
      float: left;
    }
    .date {
      font-size: 0.8em;
      color: #333;
    }
    .description {
      border: 1px solid #999;
      box-shadow: 2px 2px 4px -2px;
      padding: 8px;
    }
    .history-entry,
    .message {
      display: flex;
      padding: 16px;
      margin: 8px;
    }
    .history-entry .user,
    .message .user {
      width: 200px;
    }
    .history-entry .details,
    .message .details {
      flex: 1 0 auto;
      margin: 0 16px;
    }
    .history-entry .status-change {
      font-weight: bold;
      font-size: 1.5em;
    }
    .history-entry .message,
    .message .details {
      border: 1px solid #999;
      box-shadow: 2px 2px 4px -2px;
      padding: 16px;
    }
    .message .note {
      color: #333;
      font-size: 0.8em;
      font-style: italic;
    }
    .message .details {
      text-align: right;
    }
    .message .details textarea {
      height: 150px;
    }
    .message .details .btn {
      margin-top: 4px;
    }
    </style>
  </title>
  <body>
    <div class="navbar navbar-inverse bg-inverse">
      <a class="navbar-brand" href="<?php echo URL_BASE ?>">BugTracker</a>
    </div>
    <div class="container">
  <?php
}

function renderFooter() {
  ?>
    </div>
    <script>
    var els = document.querySelectorAll('[data-toggle]');
    els.forEach(function(el) {
      el.addEventListener("click", function (){
        var target = document.querySelector(el.dataset.toggle);
        if(target) {
          target.style.display = target.style.display == "none" ? "" : "none";
        }
      });
    });
    </script>
  </body>
  </html>
  <?php
}

function redirect ($url) {
  header("HTTP/1.1 301 Moved Temporarily");
  header("Location: ".$url);
  exit;
}

function normalizeIssue (&$issue) {
    $issue['creator'] = array('email' => $issue['creator_email'], "name" => $issue['creator_name']);
    if ($issue['assignee_email']) {
      $issue['assignee'] = array('email' => $issue['assignee_email'], "name" => $issue['assignee_name']);
    }
    else {
      $issue['assignee'] = null;
    }
    $issue['tags'] = explode(",", $issue['tags']);
}
