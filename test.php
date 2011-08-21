<?php

include 'MyORM/DB.php';
include 'MyORM/DBO.php';

$sql = "
    CREATE TABLE IF NOT EXISTS `note` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `text` text COLLATE utf8_unicode_ci NOT NULL,
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_by` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
";


class Note extends MyORM_DBO {
    protected static $table = 'note';
}


$conf = array(
    'host' => 'localhost',
    'name' => 'pkeane',
    'user' => 'username',
    'pass' => 'password',
);
$db = new MyORM_DB($conf);


#print_r($note->getFieldNames());

$note = new Note($db);
$note->text = 'my first note';
$note->created = date(DATE_ATOM);
$note->created_by = 'peter';
$note->insert();


$note = new Note($db);
$note->text = 'my second note';
$note->created = date(DATE_ATOM);
$note->created_by = 'peter';
$note->insert();


$note = new Note($db);
$note->text = 'my third note';
$note->created = date(DATE_ATOM);
$note->created_by = 'peter';
$note->insert();


$notes = new Note($db);
foreach ($notes->findAll() as $n)  {
    print $n->text."\n";
    $n->delete();
}
