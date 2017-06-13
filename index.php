<?php

// API:
//
// /bugtracker
// /bugtracker/issue    GET   POST
// /bugtracker/issue/35 GET   POST
// /bugtracker/project  GET   POST
// /bugtracker/user     GET   POST

date_default_timezone_set("Asia/Hong_Kong");

define("URL_BASE", "/c/bugtracker2");

require_once("./issue.php");
require_once("./Parsedown.php");

$base_len = strlen(URL_BASE);

if (strncmp(URL_BASE, $_SERVER['REQUEST_URI'], $base_len) == 0) {
  $tail = substr($_SERVER['REQUEST_URI'], $base_len + 1);
  $query_index = strpos($tail, "?");
  if($query_index != false) {
    $tail = substr($tail, 0, $query_index);
  }
  $parts = array();
  $raw_parts = explode("/", $tail);
  for($i = 0; $i < count($raw_parts); $i++){
    $p = urldecode($raw_parts[$i]);
    if(strlen($p) > 0) {
      $parts[$i] = $p;
    }
  }
}


switch (count($parts) > 0 ? $parts[0] : "") {
  case "issue":
    if (count($parts) >= 2) {

      if ($_SERVER['REQUEST_METHOD'] == "POST") {
        // UPDATE
        $issue->updateIssue("IJMacD@gmail.com", $parts[1], $_POST);

        if (isset($_SERVER['HTTP_REFERER'])) {
          redirect($_SERVER['HTTP_REFERER']);
        } else {
          redirect(URL_BASE . "/issue/" . $parts[1]);
        }
      }
      else if ($parts[1] == "new") {
        $context = array();

        if(isset($_GET['tags'])) {
          $context['tags'] = explode(",", urldecode($_GET['tags']));
        }

        if(isset($_GET['notify'])) {
          $context['notify'] = explode(",", urldecode($_GET['notify']));
        }

        viewNewIssue($context);
      }
      else {
        // GET
        $context = array("issue" => $issue->getIssue($parts[1]));

        if(!$context['issue']) {
          header("HTTP/1.1 404 Not Found");
          echo "Issue not found";
          exit;
        }

        viewIssue($context);
      }
    }
    else {
      if ($_SERVER['REQUEST_METHOD'] == "POST") {
        // Create issue

        if(!isset($_POST['title']) || !isset($_POST['description'])) {
          // $form->addError()
          redirect(URL_BASE . "/issue/new");
        }

        $options = array(
          "title" => $_POST['title'],
          "description" => $_POST['description'],
          "tags" => $_POST['tags'],
        );

        $id = $issue->addIssue("IJMacD@gmail.com", $options);

        redirect(URL_BASE . "/issue/" . $id);
      } else {
        redirect(URL_BASE);
      }
    }
    break;
  case "tag":
    if (count($parts) >= 2) {
      $context = array(
        "title" => "Tag: ".$parts[1],
        "issues" => $issue->getIssuesByTag($parts[1]),
        "new_link" => "?tags=".urlencode($parts[1]),
      );
      viewIndex($context);
    }
    else {
      methodUnavailable();
    }
    break;
  case "user":
    if (count($parts) >= 2) {
      $context = array(
        "title" => "User: ".$parts[1],
        "issues" => $issue->getIssuesByUser($parts[1]),
        "new_link" => "?notify=".urlencode($parts[1]),
      );
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
    $context = array("issues" => $issue->getIssues());
    viewIndex($context);
}

function methodUnavailable() {
  die("Method Unavilable");
}

function viewIndex($context) {
  $title = isset($context['title']) ? $context['title'] : "BugTracker";
  renderHeader();

  $new_link = URL_BASE . "/issue/new";

  if(isset($context['new_link'])) {
    $new_link .= $context['new_link'];
  }
  ?>

  <h1>
    <?php echo $title ?>
    <a class="btn btn-primary" href="<?php echo $new_link; ?>">New Issue</a>
  </h1>

  <table class="table">
    <thead>
      <tr>
        <th>Title</th><th>Status</th><th>Created By</th><th>Assigned To</th><th>Deadline</th><th>Tags</th>
      </tr>
    </thead>
    <tbody>
      <?php
      foreach($context['issues'] as $issue) {
        ?>
        <tr class="status-<?php echo $issue['status'] ?> <?php echo $issue['assignee'] ? "status-assigned" : "status-unassigned" ?>">
          <td><a href="<?php echo URL_BASE ?>/issue/<?php echo $issue['id'] ?>"><?php echo $issue['title'] ?></a></td>
          <td class="status"><?php echo $issue['status']; if($issue['status'] == "open" && !$issue['assignee']) { echo ", unassigned"; } ?></td>
          <td>
            <?php echo formatUser($issue['creator']) ?>
            <div class="date"><?php echo formatDate($issue['date']) ?></div>
          </td>
          <td><?php
            if ($issue['assignee']) {
              echo formatUser($issue['assignee']);
              echo '<div class="date">'. formatDate($issue['assigned']) .'</div>';
            } else if ($issue['status'] == "open") {
              renderAssignment($issue, true);
            }
          ?></td>
          <td><?php
            $class = ($issue['status'] == "open" && $issue['deadline'] < time() ? "deadline-expired" : "");
            echo '<span class="'.$class.'">' . formatDate($issue['deadline']) . '</span>';
          ?></td>
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
  global $db;

  $issue = $context['issue'];
  renderHeader();
  ?>

  <div class="row">
    <div class="col-md-9">

      <h1 class="clearfix">
      <?php
        echo $issue['title'];
        if($issue['status'] == "closed") {
          echo ' <span class="badge badge-danger">Closed</span>';
        } else if ($issue['status'] == "open") {
          echo '<button class="btn btn-default btn-sm m-1 float-right" data-toggle="#edit-title">Edit Title</button>';
        }
      ?>
      </h1>

      <form action="" method="post" style="display: none;" class="form-inline" id="edit-title">
        <input type="text" class="form-control m-1" style="flex-grow:1;" name="title" value="<?php echo $issue['title'] ?>" />
        <input type="submit" class="form-control btn btn-primary m-1" value="Save" />
      </form>
      <div class="description clearfix">
      <?php
        $parsedown = new Parsedown();
        echo $parsedown->text($issue['description']);

        if ($issue['status'] == "open") {
        ?>
          <button class="btn btn-default btn-sm float-right" data-toggle="#edit-description">Edit Description</button>
          <form action="" method="post" id="edit-description" style="display: none;" >
            <textarea class="form-control m-1" name="description" style="height: 160px;"><?php echo $issue['description']; ?></textarea>
            <input type="submit" class="btn btn-primary m-1" value="Save" />
          </form>
        <?php
        }
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
                  if(is_array($entry['value'])) {
                    $updates = array();
                    foreach($entry['value'] as $field => $value) {
                      if ($field == "status") {
                        echo '<p class="status-change '.$value.'">'.($value == "open" ? 'Opened Issue' : 'Closed Issue').'</p>';
                      }
                      else if ($field == "assignee") {
                        $user = $db->getUser($value);
                        echo '<p class="assignee-change">Assigned to: '.formatUser($user ? $user : $value).'</p>';
                      }
                      else if ($field == "assigned") {
                        // ignore
                      }
                      else {
                        $updates[] = "Set $field to '" . htmlspecialchars($value) . "'.";
                      }
                    }
                    echo implode("<br>", $updates);
                  } else {
                    echo '<p class="text-muted">Unknown update</p>';
                  }
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
        <div class="edit-message">
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

      <div class="box clearfix">
        <h2>Created By</h2>
        <?php echo formatUser($issue['creator']) ?>
        <div class="date"><?php echo formatDate($issue['date']) ?></div>
      </div>

      <div class="box clearfix">
        <h2>
        Assigned To
        <?php
        if ($issue['status'] == "open") {
          renderAssignment($issue);
        }
        ?>
        </h2>
        <?php
          if ($issue['assignee']) {
            echo formatUser($issue['assignee']);
            echo '<div class="date">'. formatDate($issue['assigned']) .'</div>';
          }
        ?>
      </div>

      <div class="box clearfix">
        <h2>
          Status
          <?php
            if ($issue['status'] == "open") {
              $value = "closed";
              $class = "btn-danger";
              $label = "Close Issue";
            }
            else {
              $value = "open";
              $class = "btn-default";
              $label = "Re-open Issue";
            }
          ?>
          <form action="" method="post" style="display: inline;">
            <input type="hidden" name="status" value="<?php echo $value ?>" />
            <input type="submit" class="btn btn-sm <?php echo $class ?>" value="<?php echo $label ?>" />
          </form>
        </h2>
        <?php echo $issue['status']; ?>
      </div>

      <div class="box clearfix">
        <h2>
          Deadline
          <?php
            if ($issue['status'] == "open") { ?>
              <button class="btn btn-default btn-sm" data-toggle="#edit-deadline">Set Deadline</button>
            <?php
            }
          ?>
        </h2>
        <?php
          $class = ($issue['status'] == "open" && $issue['deadline'] < time() ? "deadline-expired" : "");
          echo '<span class="'.$class.'">' . formatDate($issue['deadline']) . '</span>';
        ?>
        <form action="" method="post" style="display: none;" id="edit-deadline">
          <input class="form-control m-1" name="deadline" type="datetime-local" value="<?php echo ($issue['deadline'] ? substr(date('c', $issue['deadline']), 0, 19) : '') ?>" />
          <input type="submit" class="btn btn-primary m-1" value="Set" />
        </form>
      </div>


      <div class="box clearfix">
        <h2>
          Tags
          <?php
            if ($issue['status'] == "open") { ?>
              <button class="btn btn-default btn-sm" data-toggle="#edit-tags">Edit Tags</button>
            <?php
            }
          ?>
        </h2>
        <?php echo formatTags($issue['tags']) ?>
        <form action="" method="post" style="display: none;" id="edit-tags">
          <textarea class="form-control m-1" name="tags" style="font-family: monospace"><?php
            echo implode(", ", $issue['tags']);
          ?></textarea>
          <div class="note">Comma separated</div>
          <input type="submit" class="btn btn-primary m-1" value="Save" />
        </form>
      </div>


      <div class="box clearfix">
        <h2>
          Subscribers
          <?php
            if ($issue['status'] == "open") { ?>
              <button class="btn btn-default btn-sm" data-toggle="#edit-subscribers">Add subcribers</button>
            <?php
            }
          ?>
        </h2>
        <ul class="subscriber-list">
          <?php

          $notify = $db->getIssueNotify($issue['id']);

          $flat_list = array();

          foreach ($notify as $user) {
            echo '<li>'.formatUser($user).'<button class="btn btn-outline-danger btn-sm m-1">Remove</button></li>';
            $flat_list[] = formatUserAddress($user);
          }
          ?>
        </ul>
        <small class="text-muted">Users who will be notified of updates.</small>
        <form action="" method="post" style="display: none;" id="edit-subscribers">
          <input type="text" class="form-control m-1" name="subscribers" placeholder="Email addresses" />
          <input type="submit" class="btn btn-primary m-1" value="Save" />
        </form>
      </div>

    </div>
  </div>

  <?php
  renderFooter();
}



function viewNewIssue($context) {
  global $db;

  renderHeader();

  $tags = "";

  if(isset($context['tags'])) {
    $tags = htmlspecialchars(implode(", ", $context['tags']));
  }

  $notify = "";

  if(isset($context['notify'])) {
    $notify = htmlspecialchars(implode(", ", $context['notify']));
  }
  ?>

  <h1>Create Issue</h1>

  <form action="<?php echo URL_BASE . "/issue/"; ?>" method="post">
    <div class="form-group">
      <label for="title">Title</label>
      <input type="text" class="form-control" id="title" name="title" aria-describedby="titleHelp" placeholder="Enter title" required>
      <small id="titleHelp" class="form-text text-muted">Short descriptive title of issue.</small>
    </div>
    <div class="form-group">
      <label for="description">Description</label>
      <textarea class="form-control" id="description" name="description" rows="6" aria-describedby="descriptionHelp" required></textarea>
      <small id="descriptionHelp" class="form-text text-muted">Explain the issue with more detail. You can use formatting such as: *<em>emphasis</em>*, **<b>bold</b>**, and links.</small>
    </div>
    <div class="form-group">
      <label for="title">Tags <em class="text-muted">(Optional)</em></label>
      <input type="text" class="form-control" id="tags" name="tags" aria-describedby="tagsHelp" placeholder="Enter tags" value="<?php echo $tags; ?>">
      <small id="tagsHelp" class="form-text text-muted">You can added comma separated tags to help searching/categorising issues.</small>
    </div>
    <input type="hidden" name="notify" value="<?php echo $notify; ?>" />
    <button type="submit" class="btn btn-primary">Submit</button>
  </form>

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
  .'<a href="'.URL_BASE.'/user/'.$email.'" class="name" title="'.$email.'">'
    .$name
  .'</a>';
}

function formatTags($tags) {
  $out = array();
  foreach($tags as $tag) {
    $tag = trim($tag);
    $bg = substr(md5($tag), 0, 6);
    $out[] = '<a href="'.URL_BASE.'/tag/'.$tag.'" class="badge" style="background: #'.$bg.'">'.$tag.'</a>';
  }
  return implode(" ", $out);
}

function formatUserAddress($user){
  if (isset($user['name']) && $user['name']) {
    return $user['name'] . " <" . $user['email'] . ">";
  }

  return $user['email'];
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
    .container {
      padding-top: 16px;
    }
    h1 .status {
      color: #999;
    }
    .floating {
      position: absolute;
      background: white;
      border: 1px solid #999;
      padding: 8px;
    }
    .status-open {
    }
    .status-open.status-unassigned {
      font-weight: bold;
    }
    .status-open.status-assigned .status {
      /*color: #633;*/
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
    /*.box:hover,*/
    .description {
      border: 1px solid #999;
      box-shadow: 2px 2px 4px -2px;
      padding: 8px;
      margin: 8px 0;
    }
    .box {
      border: 1px solid #ccc;
      box-shadow: 2px 2px 4px -4px;
      padding: 8px;
      margin: 8px 0;
      transition: all 0.25s;
    }
    .history-entry,
    .edit-message {
      display: flex;
      padding: 16px;
      margin: 8px;
    }
    .history-entry .user,
    .edit-message .user {
      width: 200px;
      flex-shrink: 0;
    }
    .history-entry .details,
    .edit-message .details {
      flex: 1 0 200px;
      margin: 0 16px;
    }
    .history-entry .status-change {
      font-weight: bold;
      font-size: 1.5em;
    }
    .status-change.open {
      color: #5cb85c;
    }
    .status-change.closed {
      color: #d9534f;
    }
    .history-entry .message,
    .edit-message .details {
      border: 1px solid #999;
      box-shadow: 2px 2px 4px -2px;
      padding: 16px;
    }
    .note {
      color: #333;
      font-size: 0.8em;
      font-style: italic;
    }
    .edit-message .details {
      text-align: right;
    }
    .edit-message .details textarea {
      height: 150px;
    }
    .edit-message .details .btn {
      margin-top: 4px;
    }
    .deadline-expired {
      color: #c00;
    }
    .subscriber-list {
      list-style: none;
      padding: 0;
    }
    .subscriber-list li {
      clear: both;
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

function renderAssignment ($issue, $floating=false) {
  $formId = "assign-form-" . rand(1, 1000000);
  ?>
  <button class="btn btn-sm" data-toggle="<?php echo '#'.$formId; ?>"><?php echo ($issue['assignee'] ? 'Re-assign' : 'Assign') ?></button>
  <form
    id="<?php echo $formId ?>"
    action="<?php echo URL_BASE . "/issue/" . $issue['id']?>"
    method="post" style="display: none; margin:4px;"
    class="form-inline <?php echo ($floating ? "floating" : "") ?>"
  >
    <div class="input-group">
      <input type="email" class="form-control" name="assignee" autocomplete="email" value="<?php echo ($issue['assignee'] ? $issue['assignee']['email'] : '') ?>" />
      <input type="submit" value="Set" class="btn btn-primary" />
    </div>
  </form>
  <?php
}

function redirect ($url) {
  header("HTTP/1.1 301 Moved Temporarily");
  header("Location: ".$url);
  exit;
}
