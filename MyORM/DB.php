<?php

class MyORM_DB {

	private $host;
	private $name;
	private $user;
	private $pass;
	public $dbh;

	public function __construct($config)
	{ 
		$this->host = $config['host'];
		$this->name = $config['name'];
		$this->user = $config['user'];
		$this->pass = $config['pass'];
	}

	public function getDbh()
	{
		if ($this->dbh) {
			return $this->dbh;
		}
		$driverOpts = array();
		$dsn = "mysql:host=".$this->host.";dbname=".$this->name;
		try {
			$this->dbh = new PDO($dsn, $this->user, $this->pass, $driverOpts);
			$this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
			//http://netevil.org/blog/2006/apr/using-pdo-mysql
			$this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
		} catch (PDOException $e) {
			throw new  PDOException('connect failed: ' . $e->getMessage());
		}
		return $this->dbh;
	}

	public function listTables()
	{
		$dbh = $this->getDbh();
		$sth = $dbh->prepare("SHOW TABLES");
		$sth->execute();
		return ($sth->fetchAll(PDO::FETCH_COLUMN));
	}	

	public function getRowCount($table)
	{
		$dbh = $this->getDbh();
		$sql = "SELECT count(*) FROM `$table`"; 
		$sth = $dbh->prepare($sql);
		$sth->execute();
		return $sth->fetchColumn();
	}

	public function getPrimaryKey($table)
	{
		$dbh = $this->getDbh();
		$sql = "SELECT k.COLUMN_NAME
			FROM information_schema.table_constraints t
			LEFT JOIN information_schema.key_column_usage k
			USING(constraint_name,table_schema,table_name)
			WHERE t.constraint_type='PRIMARY KEY'
			AND t.table_schema=DATABASE()
			AND t.table_name='$table'";
		$sth = $dbh->prepare($sql);
		$sth->execute();
		return ($sth->fetchAll(PDO::FETCH_COLUMN));
	}

	public function listColumns($table)
	{
		$dbh = $this->getDbh();
		$sql = "SHOW FIELDS FROM $table";
		$sth = $dbh->prepare($sql);
		$sth->execute();
		return ($sth->fetchAll(PDO::FETCH_COLUMN));
	}	

	public function getMetadata($table)
	{
		$dbh = $this->getDbh();
		$sql = "SELECT column_name, data_type, character_maximum_length, is_nullable,column_default
			FROM information_schema.columns 
			WHERE table_name = '$table'";
		$sth = $dbh->prepare($sql);
		$sth->execute();
		return ($sth->fetchAll(PDO::FETCH_ASSOC));
	}	

	public function getDefaults($table)
	{
		$dbh = $this->getDbh();
		$sql = "SELECT column_name,column_default
			FROM information_schema.columns 
			WHERE table_name = '$table'";
		$sth = $dbh->prepare($sql);
		$sth->execute();
		$defaults = array();
		foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$defaults[$row['column_name']] = $row['column_default'];
		}
		return $defaults;
	}	

	public function getAutoIncrement($table)
	{
		$dbh = $this->getDbh();
		$sql = "SELECT column_name 
			FROM information_schema.columns 
			WHERE table_name = '$table'
			AND extra = 'auto_increment'";
		$sth = $dbh->prepare($sql);
		$sth->execute();
		return ($sth->fetch(PDO::FETCH_COLUMN));
	}	

	public function getSchemaJson()
	{
		$schema = array();
		foreach ($this->listTables() as $table) {
			$schema[$table] = array();
			foreach ($this->getMetadata($table) as $col) {
				$schema[$table][] = $col;
			}
		}
		return json_encode($schema);
	}	

	public function getSchema()
	{
		$tables = array();
		foreach ($this->listTables() as $table) {
			$t = '';
			$t2 = '';
			$t .= "CREATE TABLE `$table` (\n";
			foreach ($this->getMetadata($table) as $col) {
				if ('id' != $col['column_name']) {
					$cols = array();
					if ('character varying' == $col['data_type']) {
						$col['data_type'] = 'varchar('.$col['character_maximum_length'].')';
					}
					$t2 .= "`{$col['column_name']}` {$col['data_type']} default NULL,\n";
				} else {
					$t .= "`id` int(11) NOT NULL auto_increment,\n";
				}
			}
			$t2 .= "PRIMARY KEY (`id`)\n) ENGINE=MyISAM DEFAULT CHARSET=utf8;\n";
			$tables[] = $t.$t2;
		}
		return join("\n",$tables);
	}
}
