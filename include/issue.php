<?php

require_once(__DIR__.'/db.php');
require_once(__DIR__.'/mail.php');
require_once(__DIR__.'/Parsedown.php');

class Issue {

  function getIssues($options=array()) {
    global $db;

    $issues = $db->getIssues($options);

    foreach($issues as &$issue) {
      normalizeIssue($issue);
    }

    return $issues;
  }

  function getIssue($id) {
    global $db;

    $issue = $db->getIssue($id);

    if (!$issue) {
      return false;
    }

    normalizeIssue($issue);

    $issue['history'] = $this->getHistory($id);

    return $issue;
  }

  function getIssuesByTag($tag, $options) {
    $issues = $this->getIssues($options);
    $out = array();
    foreach($issues as $issue) {
      if (in_array($tag, $issue['tags'])) {
        $out[] = $issue;
      }
    }
    return $out;
  }

  function getIssuesByUser($user, $options) {
    $issues = $this->getIssues($options);
    $out = array();
    $_user = strtolower($user);
    foreach($issues as $issue) {
      if (strtolower($issue['creator']['email']) == $_user ||
          ($issue['assignee'] && strtolower($issue['assignee']['email']) == $_user)) {
        $out[] = $issue;
      }
    }
    return $out;
  }

  function updateIssue($user, $id, $fields) {
    global $db;

    if (isset($fields['action'])) {
      $action = $fields['action'];
      unset($fields['action']);

      if ($action == "COMMENT") {
        $db->insertIssueHistory($user, $id, $action, $fields['message']);
      }

      return;
    }

    if (isset($fields['assignee'])) {
      $fields['assigned'] = date('c');
    }

    if (isset($fields['subscribers'])) {
      $list = imap_rfc822_parse_adrlist($fields['subscribers'], null);

      $notify = $db->getIssueNotify($id);
      $notify_email = array_map(function($user) { return $user['email']; }, $notify);

      foreach($list as $addr) {
        $email = $addr->mailbox . "@" . $addr->host;
        if (!in_array($email, $notify_email)) {
          $db->insertIssueNotify($email, $id, true);
        }
      }

      // unset($fields['subscribers']);
    }

    $db->updateIssue($user, $id, $fields);

    $data = serialize($fields);
    $db->insertIssueHistory($user, $id, "UPDATE", $data);
  }

  function addIssue($user, $options) {
    global $db;

    // // Required
    // $title = $options['title'];
    // $description = $options['description'];
    $creator = isset($options['creator']) ? $options['creator'] : $user;
    $options['creator'] = $creator;
    // $created = time();

    // // Optional
    // $status = $options['status'];
    // $assignee = $options['assignee'];
    // $assigned = time();
    // $deadline = $options['deadline'];
    $tags = isset($options['tags']) ? $options['tags'] : array();
    // $messageID = $options['messageID'];

    $notify = isset($options['notify']) ? $options['notify'] : array(); // array

    // $fields => array(
    //   "title" =>        $title,
    //   "description" =>  $description,
    //   "creator" =>      $creator,
    //   "created" =>      $created,

    //   "status" =>       $status,
    //   "assignee" =>     $assignee,
    //   "assigned" =>     $assigned,
    //   "deadline" =>     $deadline,
    //   "tags" =>         $tags,
    //   "messageID" =>    $messageID,
    // );

    $options['tags'] = implode(",", $tags);

    $issue_id = $db->insertIssue($user, $options);

    $data = serialize($options);
    $db->insertIssueHistory($user, $issue_id, "CREATE", $data);

    // Make sure the creator is in the notify list.
    // Duplicates are OK here because they are unique filtered later.
    array_unshift($notify, $creator);

    $default_notify = $db->getDefaultNotify();

    $resolved_notify = array_unique(array_merge($notify, $default_notify));

    foreach($resolved_notify as $email) {
      $db->insertIssueNotify($issue_id, $email, true);
    }

    return $issue_id;
  }

  function replyIssue($user, $issue_id, $message) {
    global $db;

    $db->insertIssueHistory($user, $issue_id, "COMMENT", $message);

  }

  function addNotify($id, $list) {
    global $db;

    if(is_array($list)) {
      foreach($list as $email) {
        $db->insertIssueNotify($id, $email, true);
      }
    }
  }

  function getHistory($id) {
    global $db;

    $history = $db->getIssueHistory($id);
    foreach($history as &$entry){
      $entry['user'] = array(
        "email" => $entry['user_email'],
        "name" => $entry['user_name']
      );

      unset($entry['user_email']);
      unset($entry['user_name']);

      if ($entry['type'] == "UPDATE" || $entry['type'] == "CREATE") {
        $entry['value'] = unserialize($entry['value']);
      }
    }
    return $history;
  }

  function notifyNewIssue ($id, $reply_headers = array()) {
    global $db, $mail;

    // Send email to all on notify list
    // This includes those added automatically
    $notify_users = $db->getIssueNotify($id);

    $issue = $this->getIssue($id);

    // old $reply_to potentially had more info (i.e. names);
    $reply_to = array();
    foreach($notify_users as $user) {
      $reply_to[] = $user['name'] . " <" . $user['email'] . ">";
    }

    $reply_subject = $issue['title'];

    $url = $this->getURL($id);

    $reply_body =
      "<p><a href=\"$url\">Issue</a> has been created. You will be notified when there are any updates.</p>\n\n"
      ."<br>\n"
      ."<div class=\"gmail_quote\">"
        ."On ".date("D, j M Y, H:i ", $issue['created'])
        .$issue['creator']['name'].", &lt;<a href=\"mailto:".$issue['creator']['email']."\">".$issue['creator']['email']."</a>&gt; wrote:"
        ."<br>\n"
      ."</div>"
      ."<blockquote class=\"gmail_quote\" style=\"margin:0 0 0 .8ex;border-left:1px #ccc solid;padding-left:1ex\">"
        .$this->getDescription($id)
      ."</blockquote>";

    $reply_headers["Reply-To"] = "i-Learner Bugtracker <bugtracker.ilearner+issue$id@gmail.com>";
      // "CC" => implode(", ", $reply_cc),

    $mail->sendMail(implode(", ", $reply_to), $reply_subject, $reply_body, $reply_headers);
    // var_dump(implode(", ", $reply_to));
    // var_dump(implode(", ", $reply_cc));
    // continue;
  }

  function getURL($id) {
    return "http://192.168.0.200/c/bugtracker2/issue/$id";
  }

  function getDescription($id) {
    $issue = $this->getIssue($id);

    $parsedown = new Parsedown();
    return $parsedown->text($issue['description']);
  }
}



function normalizeIssue (&$issue) {
    $issue['creator'] = array('email' => $issue['creator_email'], "name" => $issue['creator_name']);

    if ($issue['assignee_email']) {
      $issue['assignee'] = array('email' => $issue['assignee_email'], "name" => $issue['assignee_name']);
    }
    else {
      $issue['assignee'] = null;
    }

    unset($issue['creator_name']);
    unset($issue['creator_email']);
    unset($issue['assignee_name']);
    unset($issue['assignee_email']);
    unset($issue['message_id']);

    $issue['tags'] = array_filter(
      array_map(
        function($t) {
          return trim($t);
        },
        explode(",", $issue['tags'])
      ),
      function ($t) {
        return strlen($t) > 0;
      }
    );
}


// Singleton
$issue = new Issue;
