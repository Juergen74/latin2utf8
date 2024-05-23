# latin2utf8
Convert latin1/windows-1252 chars to utf8 in mysql database  
  
Earlier versions of wordpress were using latin-1 as collation for the mysql database, which sometimes leads to problems when moving sites.
If you see a lot of chars like "Ã¤" or "Ã¶" instead of "ä" and "ö" – your most likely a German... :)
  
This helper class loops through all your tables and replaces them with the apropriate utf8 "umlaut". Before you run this, you should change all your tables and column collation to something like "utf8mb4_unicode_520_ci" (the standard for wordpress), which can be easily done with phpMyAdmin.  
  
Bonus feature: You can add your own "search" and "replace" strings with "addSearchReplace("search", "replace").  
Wordpress sometimes is using serealized data. This class will recalculate those serealized strings automatically if there is any search & replace happening, that normally would corrupt this data.  
  
As long as "updateTables(false)" is set to false – nothing will happen, it will only produce a nice preview of all characters found, that will be replaced.  
Set "updateTables(true)" to run the conversion.  
  
Recommendation: Be carfull and only use it on a backup of your database in local development environment! :)  
  
Use this class like  
  
`include('./latin2utf8.php');  
  
$latin2Utf8 = new Latin2Utf8("wp_test", "jonny", "root");  
//$latin2Utf8->addSearchReplace("develop.local", "www.develop.de");  
$latin2Utf8->updateTables(false);`
