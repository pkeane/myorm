//<?php
//from http://www.lornajane.net/posts/2009/PHP-5.3-Feature-Late-Static-Binding

class Record {
   
    protected static $tableName = 'base';

    public static function getTableName() {
        return self::$tableName;
    }
}
 
class User extends Record {
    protected static $tableName = 'users';
}
 

User::getTableName();  // returns "base"
 
/*
With PHP 5.3, the static keyword has been implemented to allow us to get the value of the class the code is actually executing inside rather than where it was inherited from. We simply replace the "self" in our Record class with "static":
 */

class Record {

    protected static $tableName = 'base';

    public static function getTableName() {
        return static::$tableName;
    }
}
 
User::getTableName(); // returns "users"

//n.b. You can also now use get_called_class() which saves you even needing the static member variable. 
