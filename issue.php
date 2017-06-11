<?php

require_once('./session.php');
require_once('./db.php');
require_once('./mail.php');

class Issue {

  function getIssues() {
    global $db;

    $issues = $db->getIssues();

    foreach($issues as &$issue) {
      normalizeIssue($issue);
    }

    return $issues;
  }

  function getIssue($id) {
    global $db;

    $issue = $db->getIssue($id);

    normalizeIssue($issue);

    $history = $db->getIssueHistory($id);
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
    $issues = $this->getIssues();
    $out = array();
    foreach($issues as $issue) {
      if (in_array($tag, $issue['tags'])) {
        $out[] = $issue;
      }
    }
    return $out;
  }

  function getIssuesByUser($user) {
    $issues = $this->getIssues();
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
    // $creator = $options['creator'];
    // $created = time();

    // // Optional
    // $status = $options['status'];
    // $assignee = $options['assignee'];
    // $assigned = time();
    // $deadline = $options['deadline'];
    // $tags = $options['tags'];
    // $messageID = $options['messageID'];

    // $notify = $options['notify']; // array

    // $fileds => array(
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

    $issue_id = $db->insertIssue($user, $options);

    $data = serialize($options);
    $db->insertIssueHistory($user, $time, $issue_id, "CREATE", $data);

    // Make sure the creator is in the notify list.
    // Duplicates are OK here because they are unique filtered later.
    array_unshift($notify, $creator);

    $default_notify = $db->getDefaultNotify();

    $resolved_notify = array_unique(array_merge($notify, $default_notify));

    foreach($resolved_notify as $email) {
      $db->insertIssueNotify($issue_id, $email, true);
    }

  }

  function replyIssue($user, $issue_id, $message) {
    global $db;

    $db->insertIssueHistory($user, $issue_id, "COMMENT", $message);

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

    $issue['tags'] = explode(",", $issue['tags']);
    foreach($issue['tags'] as &$tag) {
      $tag = trim($tag);
    }
}


// Singleton
$issue = new Issue;
