<?php

include('./latin2utf8.php');

$latin2Utf8 = new Latin2Utf8("wp_test", "jonny", "root");
//$latin2Utf8->addSearchReplace("develop.local", "www.develop.de");
$latin2Utf8->updateTables(false);
