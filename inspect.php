<?php

include 'MyORM/DB.php';
include 'MyORM/DBO.php';

$conf = array(
	'host' => 'localhost',
	'name' => 'name',
	'user' => 'user',
	'pass' => 'pass',
);
$db = new MyORM_DB($conf);

foreach ($db->listTables() as $tab) {
	$rc = $db->getRowCount($tab);
	print $tab." ".$rc."\n";
	foreach ($db->listColumns($tab) as $col) {
		print "\t".$col."\n";
	}
}
