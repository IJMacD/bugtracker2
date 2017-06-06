<?php

// API:
//
// /bugtracker
// /bugtracker/issue    GET   POST
// /bugtracker/issue/35 GET   POST
// /bugtracker/project  GET   POST
// /bugtracker/user     GET   POST

define("URL_BASE", "/c/bugtracker2");

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
      $context = array("issue" => getIssue($parts[1]));
      viewIssue($context);
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
  return array(
    array(
      "id" => 1,
      "title" => "Test issue",
      "status" => "open",
      "description" => "##Test Issue Details\n\nThis is the description of the issue. Here are some points:\n\n* First Point\n* Second Point",
      "date" => 1497760620,
      "creator" => array(
        "email" => "IJMacD@gmail.com",
        "name" => "Iain MacDonald"
      ),
      "assignee" => null,
      "deadline" => 0,
      "tags" => array("TST"),
    ),
    array(
      "id" => 3,
      "title" => "Third issue",
      "status" => "open",
      "description" => "Short description",
      "date" => 1497790620,
      "creator" => array(
        "email" => "IJMacD@gmail.com",
        "name" => "Iain MacDonald"
      ),
      "assignee" => array(
        "email" => "IJMacD@gmail.com",
        "name" => "Iain MacDonald"
      ),
      "deadline" => 1498790620,
      "tags" => array("Wan Chai", "English"),
    ),
    array(
      "id" => 2,
      "title" => "Another issue",
      "status" => "closed",
      "description" => "Short description",
      "date" => 1497890620,
      "creator" => array(
        "email" => "IJMacD@gmail.com",
        "name" => "Iain MacDonald"
      ),
      "assignee" => array(
        "email" => "IJMacD@gmail.com",
        "name" => "Iain MacDonald"
      ),
      "deadline" => 1498797620,
      "tags" => array("TST", "English"),
    )
  );
}

function getIssue($id) {
  $issues = getIssues();
  foreach($issues as $issue) {
    if ($issue['id'] == $id) {
      return $issue;
    }
  }
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
        ($issue['asignee'] && $issue['assignee']['email'] == $tag)) {
      $out[] = $issue;
    }
  }
  return $out;
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

  <h1><?php echo $issue['title'] ?></h1>

  <div class="row">
    <div class="col-md-9">
      <div class="description">
      <?php
        $parsedown = new Parsedown();
        echo $parsedown->text($issue['description']);
      ?>
      </div>
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
          } else if ($issue['status'] == "open") {
            echo '<button class="btn btn-sm">Assign</button>';
          }
        ?>
      </div>

      <h2>Status</h2>
      <div class="clearfix">
        <?php echo $issue['status']; ?><br>
        <?php
          if ($issue['status'] == "open") {
            echo '<button class="btn btn-sm btn-danger">Close Issue</button>';
          }
          else {
            echo '<button class="btn btn-sm btn-secondary">Re-open Issue</button>';
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

function formatUser($user){
  if (!$user) return;
  $default = "identicon";
  $size = 48;
  $grav_url = "https://www.gravatar.com/avatar/" . md5( strtolower( trim( $user['email'] ) ) ) . "?d=" . urlencode( $default ) . "&s=" . $size;
  return '<div class="avatar" style="background-image: url('.$grav_url.')"></div><a href="'.URL_BASE.'/user/'.$user['email'].'" class="name">'.$user['name'].'</a>';
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
    .description {
      border: 2px solid #ccc;
      padding: 8px;
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
  </body>
  </html>
  <?php
}
