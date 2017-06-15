<?php

require_once("./include/db.php");

$notify = $db->getIssueHistory(3);

if (PHP_SAPI !== "cli") {
    echo '<pre>';
}

var_dump($notify);
