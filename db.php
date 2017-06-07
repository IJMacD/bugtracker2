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

function dbInsertIssue($db, $fields) {
    $stmt = $db->prepare("INSERT INTO issues (title, description, creator) VALUES (?,?,?)");

    $stmt->execute(array($fields['title'], $fields['description'], $fields['creator']));
}

function dbUpdateIssue($db, $id, $fields) {
    $placeholders = array();
    $values = array();
    foreach($fields as $name => $value) {
        $placeholders[] = "$name = ?";
        $values[] = $value;
    }
    $values[] = $id;
    $stmt = $db->prepare("UPDATE issues SET ".implode(",", $placeholders)." WHERE id = ?");
    $stmt->execute($values);
}

function dbGetUsers($db) {
  $stmt = $db->query("SELECT
    email, name
    FROM users
    ORDER BY email");

  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
