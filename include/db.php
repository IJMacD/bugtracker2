<?php

define("DB_HOST", "localhost");
define("DB_NAME", "bugtracker");
define("DB_USER", "bugtracker");
define("DB_PASS", "il3ii388i5");

define("USER_TIMEOUT", 30);

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
      else if ($options['status'] == "overdue") {
        $where = " WHERE status = 'open' AND deadline != '0000-00-00 00:00:00' AND deadline < FROM_UNIXTIME(".time().")";
      }
      else {
        $where = " WHERE status = ?";
        $values[] = $options['status'];
      }
    }

    $order = " GROUP BY a.id"
      ." ORDER BY status DESC,"
      ." deadline != '0000-00-00 00:00:00' AND deadline < NOW() DESC,"
      ." assignee_email = '' DESC,"
      ." deadline = '0000-00-00 00:00:00' ASC,"
      ." deadline ASC,"
      ." created ASC";

    $stmt = $this->db->prepare($this->_selectIssues() . $where . $order);

    $stmt->execute($values);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  function getIssue($id) {

    $stmt = $this->db->prepare($this->_selectIssues() . " WHERE a.id = ?");

    $stmt->execute(array($id));

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  function insertIssue($user, $fields) {
      $stmt = $this->db->prepare("INSERT INTO issues (title, description, creator) VALUES (?,?,?)");

      $stmt->execute(array(
        $fields['title'],
        $fields['description'],
        $fields['creator']
      ));

      $id = $this->db->lastInsertId();

      if(isset($fields['tags'])){
        $this->insertTags($id, $fields['tags']);
      }

      return $id;
  }

  function updateIssue($user, $id, $fields) {
      $placeholders = array();
      $values = array();
      $valid_fields = array("title", "description", "creator", "created", "status", "assignee", "assigned", "deadline", "message_id");

      foreach($fields as $name => $value) {
        if (in_array($name, $valid_fields)) {
          $placeholders[] = "$name = ?";
          $values[] = $value;
        }
      }

      if(count($values) > 0) {
        $values[] = $id;
        $stmt = $this->db->prepare("UPDATE issues SET ".implode(",", $placeholders)." WHERE id = ?");
        $stmt->execute($values);
      }

      if(isset($fields['tags'])) {
        $stmt = $this->db->prepare("DELETE FROM tags WHERE issue_id = ?");
        $stmt->execute(array($id));

        $this->insertTags($id, $fields['tags']);
      }
  }

  function insertTags($id, $tags) {
    $tags = explode(",", $tags);

    $stmt = $this->db->prepare("INSERT INTO tags (issue_id, tag) VALUES (?,?)");

    foreach($tags as $tag) {
      $tag = trim($tag);

      if($tag) {
        $stmt->execute(array($id, $tag));
      }
    }
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

  function getTags() {
    $stmt = $this->db->query("SELECT `tag`, COUNT(*) as 'count' FROM `tags` GROUP BY `tag` ORDER BY `count` DESC");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /* ==============================
   *  Login
   * ==============================
   */

  /**
    * confirmUserPass - Checks whether or not the given
    * username is in the database, if so it checks if the
    * given password is the same password in the database
    * for that user. If the user doesn't exist or if the
    * passwords don't match up, it returns `false`.
    * On success it returns `true`.
    */
  function confirmUserPass($username, $password){
    $stmt = $this->db->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute(array($username));
    $hash = $stmt->fetchColumn();

    return password_verify($password, $hash);
  }

  /**
    * confirmUserID - Checks whether or not the given
    * username is in the database, if so it checks if the
    * given userid is the same userid in the database
    * for that user. If the user doesn't exist or if the
    * userids don't match up, it returns an error code.
    * On success it returns `true`.
    */
  function confirmUserID($username, $userid){
    $stmt = $this->db->prepare("SELECT COUNT(*) FROM activeusers WHERE username = ? AND userid = ?");
    $stmt->execute(array($username, $userid));
    $count = $stmt->fetchColumn();

    return $count > 0;
  }

  /**
  * usernameTaken - Returns true if the username has
  * been taken by another user, false otherwise.
  */
  function usernameTaken($username){
    $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(array($username));
    $count = $stmt->fetchColumn();

    return $count > 0;
  }

  /**
  * addNewUser - Inserts the given (username, password)
  * info into the database. Appropriate user level is set.
  * Returns true on success, false otherwise.
  */
  function addNewUser($username, $password, $name){
    $stmt = $this->db->prepare("INSERT INTO users (email, name, password) VALUES (?,?,?)");
    $result = $stmt->execute(array($username, $name, password_hash($password, PASSWORD_DEFAULT)));

    return $result;
  }

  /**
  * updateUserField - Updates a field, specified by the field
  * parameter, in the user's row of the database.
  */
  function updateUserField($username, $field, $value){
    $stmt = $this->db->prepare("UPDATE users SET `$field` = ? WHERE email = ?");
    $result = $stmt->execute(array($value, $username));

    return $result;
  }

  /**
  * setUserId - Updates userid in the user's row of the database.
  */
  function updateUserID($username, $value){
    $stmt = $this->db->prepare("UPDATE activeusers SET `userid` = ? WHERE email = ?");
    $result = $stmt->execute(array($value, $username));

    return $result;
  }

  /**
  * getUserInfo - Returns the result array from a mysql
  * query asking for all information stored regarding
  * the given username. If query fails, NULL is returned.
  */
  function getUserInfo($username){
    $stmt = $this->db->prepare("SELECT *,email as 'username' FROM users WHERE email = ?");
    $stmt->execute(array($username));

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  /**
  * addActiveUser - Updates username's last active timestamp
  * in the database, and also adds him to the table of
  * active users, or updates timestamp if already there.
  */
  function addActiveUser($username, $userid, $time){
    $stmt = $this->db->prepare("REPLACE INTO activeusers (username, userid, `timestamp`) VALUES (?,?,FROM_UNIXTIME(?))");
    $result = $stmt->execute(array($username, $userid, $time));

    return $result;
  }

  /* removeActiveUser */
  function removeActiveUser($username, $userid = false){
    $sql = "DELETE FROM activeusers WHERE username = ?";
    $values = array($username);

    if ($userid !== false) {
      $sql .= " AND userid = ?";
      $values[] = $userid;
    }

    $stmt = $this->db->prepare($sql);
    $stmt->execute($values);
  }

  /* removeInactiveUsers */
   function removeInactiveUsers(){
    $this->db->exec("DELETE FROM activeusers WHERE `timestamp` < " . (time() - USER_TIMEOUT * 60));
  }

  private function _selectIssues() {
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
      GROUP_CONCAT(d.tag SEPARATOR ', ') as 'tags'
      FROM issues a
        LEFT JOIN users b ON a.creator = b.email
        LEFT JOIN users c ON a.assignee = c.email
        LEFT JOIN tags d ON a.id = d.issue_id";
  }
}

// Singleton
$db = new DB;
