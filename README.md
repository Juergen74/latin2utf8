# latin2utf8
Convert latin1/windows-1252 chars to utf8 in mysql database

Use this class like

include('./latin2utf8.php');

$latin2Utf8 = new Latin2Utf8("wp_test", "jonny", "root");
//$latin2Utf8->addSearchReplace("develop.local", "www.develop.de");
$latin2Utf8->updateTables(false);
