<?php

include('./latin2utf8.php');

$database = "wp_test"; // e.g.
$username = "jonny";   // e.g.
$password = "root";    // e.g.

$latin2Utf8 = new Latin2Utf8($database, $username, $password);
//$latin2Utf8->addSearchReplace("develop.local", "www.develop.de");
$latin2Utf8->updateTables(false);
