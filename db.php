<?php

define("DB_HOST", "localhost");
define("DB_NAME", "bugtracker");
define("DB_USER", "bugtracker");
define("DB_PASS", "il3ii388i5");

function dbConnect() {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $db;
}

function dbGetIssues($db) {

  $stmt = $db->query("SELECT
    a.id as id,title,status,description,
    UNIX_TIMESTAMP(created) as 'date',
    creator as 'creator_email',b.name as 'creator_name',
    UNIX_TIMESTAMP(assigned) as 'assigned',
    assignee as 'assignee_email',c.name as 'assignee_name',
    UNIX_TIMESTAMP(deadline) as 'deadline',
    d.tags as 'tags'
    FROM issues a
      LEFT JOIN users b ON a.creator = b.email
      LEFT JOIN users c ON a.assignee = c.email
      LEFT JOIN (SELECT GROUP_CONCAT(tag SEPARATOR ',') as tags,message_id FROM tags GROUP BY message_id) d ON a.id = d.message_id
    ORDER BY status DESC, created ASC");

  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dbGetIssue($db, $id) {

  $stmt = $db->prepare("SELECT
    a.id as id,title,status,description,
    UNIX_TIMESTAMP(created) as 'date',
    creator as 'creator_email',b.name as 'creator_name',
    UNIX_TIMESTAMP(assigned) as 'assigned',
    assignee as 'assignee_email',c.name as 'assignee_name',
    UNIX_TIMESTAMP(deadline) as 'deadline',
    d.tags as 'tags'
    FROM issues a
      LEFT JOIN users b ON a.creator = b.email
      LEFT JOIN users c ON a.assignee = c.email
      LEFT JOIN (SELECT GROUP_CONCAT(tag SEPARATOR ',') as tags,message_id FROM tags GROUP BY message_id) d ON a.id = d.message_id
    WHERE a.id = ?");

  $stmt->execute(array($id));

  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function dbInsertIssue($db, $user, $fields) {
    $stmt = $db->prepare("INSERT INTO issues (title, description, creator) VALUES (?,?,?)");

    $stmt->execute(array($fields['title'], $fields['description'], $fields['creator']));

    $data = serialize($fields);
    dbInsertIssueHistory($db->lastInsertId(), $fields['creator'], "CREATE", $data);
}

function dbUpdateIssue($db, $user, $id, $fields) {
    $placeholders = array();
    $values = array();
    foreach($fields as $name => $value) {
        $placeholders[] = "$name = ?"; // TODO: Fix SQL injection
        $values[] = $value;
    }
    $values[] = $id;
    $stmt = $db->prepare("UPDATE issues SET ".implode(",", $placeholders)." WHERE id = ?");
    $stmt->execute($values);

    $data = serialize($fields);
    dbInsertIssueHistory($db, $user, $id, "UPDATE", $data);
}

function dbGetIssueHistory($db, $id) {
    $stmt = $db->prepare("SELECT
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

function dbInsertIssueHistory($db, $user, $id, $type, $value) {
    $stmt = $db->prepare("INSERT INTO history (issue_id, user, type, value) VALUES (?, ?, ?, ?)");
    $stmt->execute(array($id, $user, $type, $value));
}

function dbGetUsers($db) {
  $stmt = $db->query("SELECT
    email, name
    FROM users
    ORDER BY email");

  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dbGetUser($db, $email) {
  $stmt = $db->prepare("SELECT
    email, name
    FROM users
    WHERE email = ?");
  $stmt->execute(array($email));

  return $stmt->fetch(PDO::FETCH_ASSOC);
}
