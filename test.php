<?php

require_once("db.php");

$db = dbConnect();
$notify = dbGetIssueHistory($db, 3);

echo '<pre>';
var_dump($notify);
echo '</pre>';
