<?php

class MyORM_DBO implements IteratorAggregate
{
    private $fields = array(); 
    protected static $table = 'base';
    protected $limit;
    protected $order_by;
    protected $qualifiers = array();
    public $bind = array();
    public $config;
    public $db;
    public $sql;
    public $primary_key = array();
    public $auto_increment;

    function __construct($db)
    {
        $this->db = $db;
        foreach( $db->listColumns(static::$table) as $key ) {
            $this->fields[ $key ] = null;
        }
        foreach ($db->getPrimaryKey(static::$table) as $pk) {
            //allows composite pk
            $this->primary_key[] = $pk;
        }
        $this->auto_increment = $db->getAutoIncrement(static::$table);
    }

    public function setDefaults()
    {
        $defaults = $this->db->getDefaults(static::$table);
        foreach( $this->db->listColumns(static::$table) as $key ) {
            if (null == $this->fields[ $key ]) {
                $this->fields[ $key ] = $defaults[$key];
            }
        }
    }

    public function __get( $key )
    {
        if ( array_key_exists( $key, $this->fields ) ) {
            return $this->fields[ $key ];
        }
        //automatically call accessor method is it exists
        $classname = get_class($this);
        $method = 'get'.ucfirst($key);
        if (method_exists($classname,$method)) {
            return $this->{$method}();
        }	
    }

    public function __set( $key, $value )
    {
        if ( array_key_exists( $key, $this->fields ) ) {
            $this->fields[ $key ] = $value;
            return true;
        }
        return false;
    }

    private function _dbGet() {
        try {
            return $this->db->getDbh();
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
    }

    function getFieldNames() {
        return array_keys($this->fields);
    }

    function hasMember($key)
    {
        if ( array_key_exists( $key, $this->fields ) ) {
            return true;
        } else {
            return false;
        }
    }

    function setLimit($limit)
    {
        $this->limit = $limit;
    }

    function orderBy($ob)
    {
        $this->order_by = $ob;
    }

    function addWhere($field,$value,$operator)
    {
        if ( 
            array_key_exists( $field, $this->fields) &&
            in_array(strtolower($operator),array('is not','is','ilike','like','not ilike','not like','=','!=','<','>','<=','>='))
        ) {
            $this->qualifiers[] = array(
                'field' => $field,
                'value' => $value,
                'operator' => $operator
            );
        } else {
            throw new Exception('addWhere problem');
        }
    }

    function insert()
    { 
        $dbh = $this->db->getDbh();
        $this->setDefaults();
        $auto_inc = null;
        foreach( array_keys( $this->fields ) as $field )
        {
            if ($this->auto_increment == $field) {
                $auto_inc = $field;
            } else {
                $fields []= $field;
                $inserts []= ":$field";
                $bind[":$field"] = $this->fields[ $field ];
            }
        }
        $field_set = join( ", ", $fields );
        $insert = join( ", ", $inserts );
        //static::$table string is NOT tainted
        $sql = "INSERT INTO ".static::$table. 
            " ( $field_set ) VALUES ( $insert )";
        $sth = $dbh->prepare( $sql );
        if (! $sth) {
            $error = $db->errorInfo();
            throw new Exception("problem on insert: " . $error[2]);
            exit;
        }
        if ($sth->execute($bind)) {
            if ($auto_inc) {
                $last_id = $dbh->lastInsertId();
                $this->$auto_inc = $last_id;
                return $last_id;
            } else {
                return;
            }
        } else { 
            $error = $sth->errorInfo();
            throw new Exception("could not insert: " . $error[2]);
        }
    }

    function getMethods()
    {
        $class = new ReflectionClass(get_class($this));
        return $class->getMethods();
    }

    function findOne()
    {
        $this->setLimit(1);
        $set = $this->find()->fetchAll();
        if (count($set)) {
            return $set[0];
        }
        return false;
    }

    function findAll()
    {
        $set = array();
        $iter = $this->find();
        foreach ($iter as $it) {
            $set[] = clone($it);
        }
        return $set;
    }

    function __toString()
    {
        $members = '';
        $ac = $this->auto_increment;

        foreach ($this->fields as $key => $value) {
            $members .= "$key: $value\n";
        }
        $out = static::$table." ($this->ac)--\n$members\n";
        return $out;
    }


    function find()
    {
        //finds matches based on set fields (omitting auto_inc)
        //returns an iterator
        $dbh = $this->db->getDbh();
        $sets = array();
        $bind = array();
        $limit = '';
        foreach( array_keys( $this->fields ) as $field ) {
            if (isset($this->fields[ $field ])) {
                $sets []= "$field = :$field";
                $bind[":$field"] = $this->fields[ $field ];
            }
        }
        if (isset($this->qualifiers)) {
            //work on this
            foreach ($this->qualifiers as $qual) {
                $f = $qual['field'];
                $op = $qual['operator'];
                //allows is to add 'is null' qualifier
                if ('null' == $qual['value']) {
                    $v = $qual['value'];
                } else {
                    $v = $dbh->quote($qual['value']);
                }
                $sets[] = "$f $op $v";
            }
        }
        $where = join( " AND ", $sets );
        if ($where) {
            $sql = "SELECT * FROM ".static::$table. " WHERE ".$where;
        } else {
            $sql = "SELECT * FROM ".static::$table;
        }
        if (isset($this->order_by)) {
            $sql .= " ORDER BY $this->order_by";
        }
        if (isset($this->limit)) {
            $sql .= " LIMIT $this->limit";
        }

        $sth = $dbh->prepare( $sql );
        if (!$sth) {
            throw new PDOException('cannot create statement handle');
        }

        $sth->setFetchMode(PDO::FETCH_INTO,$this);
        $sth->execute($bind);
        //NOTE: PDOStatement implements Traversable. 
        //That means you can use it in foreach loops 
        //to iterate over rows:
        // foreach ($thing->find() as $one) {
        //     print_r($one);
        // }
        return $sth;
    }

    function findCount()
    {
        $dbh = $this->db->getDbh();
        $sets = array();
        $bind = array();
        foreach( array_keys( $this->fields ) as $field ) {
            if (isset($this->fields[ $field ]) 
                && ($this->auto_incrememnt != $field)) {
                    $sets []= "$field = :$field";
                    $bind[":$field"] = $this->fields[ $field ];
                }
        }
        if (isset($this->qualifiers)) {
            //work on this
            foreach ($this->qualifiers as $qual) {
                $f = $qual['field'];
                $op = $qual['operator'];
                //allows is to add 'is null' qualifier
                if ('null' == $qual['value']) {
                    $v = $qual['value'];
                } else {
                    $v = $dbh->quote($qual['value']);
                }
                $sets[] = "$f $op $v";
            }
        }
        $where = join( " AND ", $sets );
        if ($where) {
            $sql = "SELECT count(*) FROM ".static::$table. " WHERE ".$where;
        } else {
            $sql = "SELECT count(*) FROM ".static::$table;
        }
        $sth = $dbh->prepare( $sql );
        if (!$sth) {
            throw new PDOException('cannot create statement handle');
        }
        $log_sql = $sql;
        foreach ($bind as $k => $v) {
            $log_sql = preg_replace("/$k/","'$v'",$log_sql,1);
        }
        $sth->execute($bind);
        return $sth->fetchColumn();
    }

    function update()
    {
        $dbh = $this->db->getDbh();
        foreach( $this->fields as $key => $val) {
            $fields[]= $key." = ?";
            $values[]= $val;
        }

        $where_set = array();
        foreach ($this->primary_key as $pk) {
            $where_set[] = $pk." =?"; 
            $values[] = $this->$pk;
        }

        $set = join( ",", $fields );
        $sql = "UPDATE {$this->{'table'}} SET $set WHERE ".join(' AND ',$where_set);

        $sth = $dbh->prepare( $sql );
        return $sth->execute($values);
    }

    function delete()
    {
        $dbh = $this->db->getDbh();
        $where_set = array();
        foreach ($this->primary_key as $pk) {
            $where_set[] = $pk." =?"; 
            $values[] = $this->$pk;
        }
        $dbh = $this->db->getDbh();
        $sql = 'DELETE FROM '.static::$table.' WHERE '.join(' AND ',$where_set);
        $sth = $dbh->prepare($sql);
        return $sth->execute($values);
    }

    //implement SPL IteratorAggregate:
    //now simply use 'foreach' to iterate 
    //over object properties
    public function getIterator()
    {
        return new ArrayObject($this->fields);
    }

    public function asArray()
    {
        foreach ($this as $k => $v) {
            $my_array[$k] = $v;
        }
        return $my_array;
    }
}
