<?php
/**
 * Session.php
 *
 * The Session class is meant to simplify the task of keeping
 * track of logged in users and also guests.
 *
 * Written by: Jpmaster77 a.k.a. The Grandmaster of C++ (GMC)
 * Last Updated: June 15, 2011 by Ivan Novak
 */

define("COOKIE_EXPIRE", 60*60*24*100);  //100 days by default
define("COOKIE_PATH", "/");  //Available in whole domain

include(__DIR__."/db.php");
include(__DIR__."/form.php");

class Session
{
   var $username;     //Username given on sign-up
   var $userid;       //Random value generated on current login
   var $userlevel;    //The level to which the user pertains
   var $time;         //Time user was last active (page loaded)
   var $logged_in;    //True if user is logged in, false otherwise
   var $userinfo = array();  //The array holding all user info
   var $url;          //The page url current being viewed
   var $referrer;     //Last recorded site page viewed
   var $user;           // Array with "email" => ..., "name" => ...

   /**
    * Note: referrer should really only be considered the actual
    * page referrer in process.php, any other time it may be
    * inaccurate.
    */
   /* Class constructor */
   function Session(){
      $this->time = time();
      $this->startSession();
   }

   /**
    * startSession - Performs all the actions necessary to
    * initialize this session object. Tries to determine if the
    * the user has logged in already, and sets the variables
    * accordingly. Also takes advantage of this page load to
    * update the active visitors tables.
    */
   function startSession(){
      global $db;  //The database connection
      session_start();   //Tell PHP to start the session

      /* Determine if user is logged in */
      $this->logged_in = $this->checkLogin();

      /**
       * Clear username
       */
      if(!$this->logged_in){
         $this->username = "";
         $this->userlevel = 0;
         $this->user = null;
      }
      /* Update users last active timestamp */
      else{
         $db->addActiveUser($this->username, $this->userid, $this->time);
      }

      /* Remove inactive visitors from database */
      $db->removeInactiveUsers();

      /* Set referrer page */
      if(isset($_SESSION['url'])){
         $this->referrer = $_SESSION['url'];
      }else{
         $this->referrer = "/";
      }

      /* Set current url */
      $this->url = $_SESSION['url'] = $_SERVER['REQUEST_URI'];
   }

   /**
    * checkLogin - Checks if the user has already previously
    * logged in, and a session with the user has already been
    * established. Also checks to see if user has been remembered.
    * If so, the database is queried to make sure of the user's
    * authenticity. Returns true if the user has logged in.
    */
   function checkLogin(){
      global $db;  //The database connection

      /* Check if user has been remembered */
      if(isset($_COOKIE['cookname']) && isset($_COOKIE['cookid'])){
         $this->username = $_SESSION['username'] = $_COOKIE['cookname'];
         $this->userid   = $_SESSION['userid']   = $_COOKIE['cookid'];
      }

      /* Username and userid have been set and not guest */
      if(isset($_SESSION['username']) && isset($_SESSION['userid']) &&
         $_SESSION['username'] != ""){

         /* Confirm that username and userid are valid */
         if(!$db->confirmUserID($_SESSION['username'], $_SESSION['userid'])){
            /* Variables are incorrect, user not logged in */
            unset($_SESSION['username']);
            unset($_SESSION['userid']);
            return false;
         }

         /* User is logged in, set class variables */
         $this->userinfo  = $db->getUserInfo($_SESSION['username']);
         $this->username  = $this->userinfo['username'];
         $this->userid    = $_SESSION['userid'];
         $this->user = array(
          "email" => $this->userinfo['email'],
          "name" => $this->userinfo['name'],
        );
        //  $this->userlevel = $this->userinfo['userlevel'];

        //  /* auto login hash expires in three days */
        //  if($this->userinfo['hash_generated'] < (time() - (60*60*24*3))){
        //  	/* Update the hash */
        //      $db->updateUserField($this->userinfo['username'], 'hash', $this->generateRandID());
        //      $db->updateUserField($this->userinfo['username'], 'hash_generated', time());
        //  }

         return true;
      }
      /* User not logged in */
      else{
         return false;
      }
   }

   /**
    * login - The user has submitted his username and password
    * through the login form, this function checks the authenticity
    * of that information in the database and creates the session.
    * Effectively logging in the user if all goes well.
    */
   function login($subuser, $subpass, $subremember){
      global $db, $form;  //The database and form object

      /* Username error checking */
      $field = "username";  //Use field name for username
      if(!$subuser || strlen($subuser = trim($subuser)) == 0){
         $form->setError($field, "* Username not entered");
      }

      /* Password error checking */
      $field = "password";  //Use field name for password
      if(!$subpass){
         $form->setError($field, "* Password not entered");
      }

      /* Return if form errors exist */
      if($form->num_errors > 0){
         return false;
      }

      /* Checks that username is in database and password is correct */
      $subuser = stripslashes($subuser);
      $result = $db->confirmUserPass($subuser, $subpass);

      /* Check error codes */
      if(!$result){
         $field = "username";
         $form->setError($field, "* Username or Password not correct");
         $field = "password";
         $form->setError($field, "* Username or Password not correct");
      }

      /* Return if form errors exist */
      if($form->num_errors > 0){
         return false;
      }

      /* Username and password correct, register session variables */
      $this->userinfo  = $db->getUserInfo($subuser);
      $this->username  = $_SESSION['username'] = $this->userinfo['username'];
      $this->userid    = $_SESSION['userid']   = $this->generateRandID();
      $this->user = array(
        "email" => $this->userinfo['email'],
        "name" => $this->userinfo['name'],
      );
    //   $this->userlevel = $this->userinfo['userlevel'];

      /* Insert userid into database and update active users table */
      $db->addActiveUser($this->username, $this->userid, $this->time);

      /**
       * This is the cool part: the user has requested that we remember that
       * he's logged in, so we set two cookies. One to hold his username,
       * and one to hold his random value userid. It expires by the time
       * specified. Now, next time he comes to our site, we will
       * log him in automatically, but only if he didn't log out before he left.
       */
      if($subremember){
         setcookie("cookname", $this->username, time()+COOKIE_EXPIRE, COOKIE_PATH);
         setcookie("cookid",   $this->userid,   time()+COOKIE_EXPIRE, COOKIE_PATH);
      }

      /* Login completed successfully */
      return true;
   }

   /**
    * logout - Gets called when the user wants to be logged out of the
    * website. It deletes any cookies that were stored on the users
    * computer as a result of him wanting to be remembered, and also
    * unsets session variables and demotes his user level.
    */
   function logout(){
      global $db;  //The database connection

      /**
       * Delete cookies - the time must be in the past.
       */
      if(isset($_COOKIE['cookname']) && isset($_COOKIE['cookid'])){
         setcookie("cookname", "", time()-1, COOKIE_PATH);
         setcookie("cookid",   "", time()-1, COOKIE_PATH);
      }

      /* Unset PHP session variables */
      unset($_SESSION['username']);
      unset($_SESSION['userid']);

      /* Reflect fact that user has logged out */
      $this->logged_in = false;

      /**
       * Remove from active users table.
       */
      $db->removeActiveUser($this->username);

      /* Set user level to guest */
      $this->username  = "";
      $this->userlevel = 0;
      $this->user = null;
   }

   /**
    * isAdmin - Returns true if currently logged in user is
    * an administrator, false otherwise.
    */
   function isAdmin(){
      return ($this->userlevel == ADMIN_LEVEL ||
              $this->username  == ADMIN_NAME);
   }

   /**
    * Determine if user can edit issues
    */
   function canEdit() {
     return $this->logged_in && $this->userinfo['can_edit_issues'];
   }

   /**
    * generateRandID - Generates a string made up of randomized
    * letters (lower and upper case) and digits and returns
    * the md5 hash of it to be used as a userid.
    */
   function generateRandID(){
      return md5($this->generateRandStr(16));
   }

   /**
    * generateRandStr - Generates a string made up of randomized
    * letters (lower and upper case) and digits, the length
    * is a specified parameter.
    */
   function generateRandStr($length){
      $randstr = "";
      for($i=0; $i<$length; $i++){
         $randnum = mt_rand(0,61);
         if($randnum < 10){
            $randstr .= chr($randnum+48);
         }else if($randnum < 36){
            $randstr .= chr($randnum+55);
         }else{
            $randstr .= chr($randnum+61);
         }
      }
      return $randstr;
   }

   function cleanInput($post = array()) {
        foreach($post as $k => $v){
            $post[$k] = trim(htmlspecialchars($v));
        }
        return $post;
    }
};

/**
 * Initialize session object - This must be initialized before
 * the form object because the form uses session variables,
 * which cannot be accessed unless the session has started.
 */
$session = new Session;
// /* Initialize form object */
$form = new Form;
