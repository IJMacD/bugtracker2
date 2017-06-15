<?php

define("DB_HOST", "localhost");
define("DB_NAME", "bugtracker");
define("DB_USER", "bugtracker");
define("DB_PASS", "il3ii388i5");

class DB {

  var $db;

  function __construct() {
    $this->connect();
  }

  function connect() {
    $this->db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $this->db;
  }

  function getIssues($options) {
    $where = "";
    $values = array();

    if(isset($options['status'])) {
      if ($options['status'] == "unassigned") {
        $where = " WHERE status = 'open' AND assignee = ''";
      }
      else {
        $where = " WHERE status = ?";
        $values[] = $options['status'];
      }
    }

    $order = " ORDER BY status DESC, assignee_email = '' DESC, deadline = '0000-00-00 00:00:00' ASC, deadline ASC, created ASC";

    $stmt = $this->db->prepare(_selectIssues() . $where . $order);

    $stmt->execute($values);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  function getIssue($id) {

    $stmt = $this->db->prepare(_selectIssues() . " WHERE a.id = ?");

    $stmt->execute(array($id));

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  function insertIssue($user, $fields) {
      $stmt = $this->db->prepare("INSERT INTO issues (title, description, creator, tags) VALUES (?,?,?,?)");

      $stmt->execute(array(
        $fields['title'],
        $fields['description'],
        $fields['creator'],
        isset($fields['tags']) ? $fields['tags'] : "",
      ));

      $id = $this->db->lastInsertId();

      return $id;
  }

  function updateIssue($user, $id, $fields) {
      $placeholders = array();
      $values = array();
      $valid_fields = array("title", "description", "creator", "created", "status", "assignee", "assigned", "deadline", "tags", "message_id");
      foreach($fields as $name => $value) {
        if (in_array($name, $valid_fields)) {
          $placeholders[] = "$name = ?";
          $values[] = $value;
        }
      }
      if(count($values) == 0) {
        return;
      }
      $values[] = $id;
      $stmt = $this->db->prepare("UPDATE issues SET ".implode(",", $placeholders)." WHERE id = ?");
      $stmt->execute($values);
  }

  function getIssueHistory($id) {
      $stmt = $this->db->prepare("SELECT
          user as user_email,
          name as user_name,
          UNIX_TIMESTAMP(date) as date,
          type,
          value
          FROM history
              LEFT JOIN users ON user = email
          WHERE issue_id = ?
          ORDER BY date ASC");
      $stmt->execute(array($id));
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  function insertIssueHistory($user, $id, $type, $value) {
      $stmt = $this->db->prepare("INSERT INTO history (issue_id, user, type, value) VALUES (?, ?, ?, ?)");
      $stmt->execute(array($id, $user, $type, $value));
  }

  function getIssueNotify($id) {
    $stmt = $this->db->prepare("SELECT user as email, name FROM notify LEFT JOIN users ON user = email WHERE issue_id = ? AND enabled = 1");
    $stmt->execute(array($id));
    return $stmt->fetchALL(PDO::FETCH_ASSOC);
  }

  function insertIssueNotify($id, $user, $enabled) {
    try {
      $stmt = $this->db->prepare("INSERT INTO notify (issue_id, user, enabled) VALUES (?, ?, ?)");
      $stmt->execute(array($id, $user, $enabled));
    } catch (Exception $e) {
      // Probably duplicate key
    }
  }

  function getUsers() {
    $stmt = $this->db->query("SELECT
      email, name
      FROM users
      ORDER BY email");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  function getUser($email) {
    $stmt = $this->db->prepare("SELECT
      email, name
      FROM users
      WHERE email = ?");
    $stmt->execute(array($email));

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  function getDefaultNotify() {
    $stmt = $this->db->query("SELECT
      email
      FROM users
      WHERE notify_new_issues = 1
      ORDER BY email");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
  }
}

// Singleton
$db = new DB;


function _selectIssues() {
  return "SELECT
    a.id as id,title,status,description,
    UNIX_TIMESTAMP(created) as 'date',
    creator as 'creator_email',
    b.name as 'creator_name',
    UNIX_TIMESTAMP(assigned) as 'assigned',
    assignee as 'assignee_email',
    c.name as 'assignee_name',
    UNIX_TIMESTAMP(deadline) as 'deadline',
    message_id,
    tags
    FROM issues a
      LEFT JOIN users b ON a.creator = b.email
      LEFT JOIN users c ON a.assignee = c.email";
}