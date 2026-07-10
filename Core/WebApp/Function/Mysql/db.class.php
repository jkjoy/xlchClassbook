<?php
class DB {
	var $link = null;
	var $driver = 'mysql';
	var $lastError = '';

	function __construct($db_host, $db_user = '', $db_pass = '', $db_name = '', $db_port = '', $driver = 'mysql'){
		if(is_array($db_host)){
			$info = $db_host;
			$driver = isset($info['Type']) ? $info['Type'] : (isset($info['Driver']) ? $info['Driver'] : 'mysql');
			$db_host = isset($info['Ip']) ? $info['Ip'] : '';
			$db_user = isset($info['Username']) ? $info['Username'] : '';
			$db_pass = isset($info['Password']) ? $info['Password'] : '';
			$db_name = isset($info['Database']) ? $info['Database'] : '';
			$db_port = isset($info['Port']) ? $info['Port'] : '';
		}

		$this->driver = strtolower($driver ?: 'mysql');
		if($this->driver == 'sqlite'){
			$this->connect_sqlite($db_name);
		}else{
			$this->connect_mysql($db_host, $db_user, $db_pass, $db_name, $db_port);
		}
	}

	function connect_mysql($db_host, $db_user, $db_pass, $db_name, $db_port){
		$this->link = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
		if (!$this->link) {
			$this->lastError = mysqli_connect_error();
			return false;
		}

		mysqli_query($this->link,"set sql_mode = ''");
		mysqli_query($this->link,"set character set 'utf8'");
		mysqli_query($this->link,"set names 'utf8'");
		return true;
	}

	function connect_sqlite($db_name){
		if(!class_exists('PDO')){
			$this->lastError = 'PDO is not available';
			return false;
		}
		if(strpos($db_name, DIRECTORY_SEPARATOR) === false && strpos($db_name, '/') === false && defined('RootDir')){
			$db_name = RootDir . 'data/' . $db_name;
		}else if(!preg_match('/^([a-zA-Z]:)?[\/\\\\]/', $db_name) && defined('RootDir')){
			$db_name = RootDir . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $db_name);
		}
		$dir = dirname($db_name);
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		try{
			$this->link = new PDO('sqlite:' . $db_name);
			$this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->link->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$this->link->exec('PRAGMA foreign_keys = ON');
			return true;
		}catch(Exception $e){
			$this->lastError = $e->getMessage();
			$this->link = null;
			return false;
		}
	}

	function is_sqlite(){
		return $this->driver == 'sqlite';
	}

	function fetch($q){
		if(!$q) return false;
		if($this->is_sqlite()){
			return $q->fetch(PDO::FETCH_ASSOC);
		}
		return mysqli_fetch_assoc($q);
	}

	function get_row($q){
		$result = $this->query($q);
		return $this->fetch($result);
	}

	function count($q){
		$result = $this->query($q);
		if(!$result) return 0;
		if($this->is_sqlite()){
			$count = $result->fetch(PDO::FETCH_NUM);
		}else{
			$count = mysqli_fetch_array($result);
		}
		return $count ? $count[0] : 0;
	}

	function query($q){
		if(!isset($GLOBALS['SqlQueryNumber'])){
			$GLOBALS['SqlQueryNumber'] = 0;
		}
		$GLOBALS['SqlQueryNumber']++;
		if($this->is_sqlite()){
			$q = $this->sqlite_sql($q);
			if($q === '') return true;
			try{
				return $this->link->query($q);
			}catch(Exception $e){
				$this->lastError = $e->getMessage() . ' SQL: ' . $q;
				return false;
			}
		}
		$result = mysqli_query($this->link,$q);
		if(!$result) $this->lastError = mysqli_error($this->link);
		return $result;
	}

	function escape($str){
		if($this->is_sqlite()){
			return substr($this->link->quote($str), 1, -1);
		}
		return mysqli_real_escape_string($this->link,$str);
	}

	function insert($q){
		if($this->query($q))
			return $this->insert_id();
		return false;
	}

	function insert_id(){
		if($this->is_sqlite()){
			return $this->link->lastInsertId();
		}
		return mysqli_insert_id($this->link);
	}

	function affected(){
		if($this->is_sqlite()) return 0;
		return mysqli_affected_rows($this->link);
	}

	function insert_array($table,$array){
		$keys = array_keys($array);
		$values = [];
		foreach($array as $value){
			$values[] = $this->escape($value);
		}
		$q = "INSERT INTO `$table`";
		$q .=" (`".implode("`,`",$keys)."`) ";
		$q .=" VALUES ('".implode("','",$values)."') ";

		if($this->query($q))
			return $this->insert_id();
		return false;
	}

	function error(){
		if($this->is_sqlite()){
			return $this->lastError;
		}
		$error = mysqli_error($this->link);
		$errno = mysqli_errno($this->link);
		return '['.$errno.'] '.$error;
	}

	function close(){
		if($this->is_sqlite()){
			$this->link = null;
			return true;
		}
		return mysqli_close($this->link);
	}

	private function sqlite_sql($sql){
		$sql = trim($sql);
		if($sql === '') return '';
		if(preg_match('/^(set sql_mode|set character set|set names)/i', $sql)) return '';
		if(preg_match('/^TRUNCATE\s+(.+)$/i', $sql, $m)){
			return 'DELETE FROM ' . $m[1];
		}

		$sql = preg_replace('/date_sub\s*\(\s*now\s*\(\s*\)\s*,\s*interval\s+([0-9]+)\s+(hour|day|minute|second)\s*\)/i', "datetime('now','-$1 $2','localtime')", $sql);
		$sql = preg_replace('/\bnow\s*\(\s*\)/i', "datetime('now','localtime')", $sql);
		$sql = preg_replace('/\brand\s*\(\s*\)/i', 'random()', $sql);
		$sql = str_replace('&&', 'AND', $sql);
		$sql = preg_replace('/if\s*\(([^,]+),\s*0\s*,\s*1\s*\)/i', 'CASE WHEN $1 THEN 0 ELSE 1 END', $sql);

		if(preg_match('/^\s*INSERT\s+INTO\s+(`?[A-Za-z0-9_]+`?)\s+set\s+(.+);?\s*$/is', $sql, $m)){
			$assignments = $this->split_assignments($m[2]);
			$columns = [];
			$values = [];
			foreach($assignments as $assignment){
				if(preg_match('/^\s*(`?[A-Za-z0-9_]+`?)\s*=\s*(.+)\s*$/s', $assignment, $am)){
					$columns[] = $am[1];
					$values[] = rtrim(trim($am[2]), ';');
				}
			}
			if($columns){
				return $this->sqlite_quote_mysql_strings('INSERT INTO ' . $m[1] . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')');
			}
		}
		return $this->sqlite_quote_mysql_strings($sql);
	}

	private function split_assignments($sql){
		$parts = [];
		$buffer = '';
		$quote = '';
		$escape = false;
		$depth = 0;
		$length = strlen($sql);
		for($i = 0; $i < $length; $i++){
			$char = $sql[$i];
			if($escape){
				$buffer .= $char;
				$escape = false;
				continue;
			}
			if($char === '\\'){
				$buffer .= $char;
				$escape = true;
				continue;
			}
			if($quote){
				if($char === $quote) $quote = '';
				$buffer .= $char;
				continue;
			}
			if($char === '\'' || $char === '"'){
				$quote = $char;
				$buffer .= $char;
				continue;
			}
			if($char === '('){
				$depth++;
				$buffer .= $char;
				continue;
			}
			if($char === ')' && $depth > 0){
				$depth--;
				$buffer .= $char;
				continue;
			}
			if($char === ',' && $depth === 0){
				$parts[] = trim($buffer);
				$buffer = '';
				continue;
			}
			$buffer .= $char;
		}
		if(trim($buffer) !== '') $parts[] = trim($buffer);
		return $parts;
	}

	private function sqlite_quote_mysql_strings($sql){
		$return = '';
		$length = strlen($sql);
		for($i = 0; $i < $length; $i++){
			$char = $sql[$i];
			if($char === '`' || $char === '\''){
				$quote = $char;
				$return .= $char;
				$i++;
				for(; $i < $length; $i++){
					$return .= $sql[$i];
					if($sql[$i] === '\\' && $i + 1 < $length){
						$i++;
						$return .= $sql[$i];
						continue;
					}
					if($sql[$i] === $quote){
						break;
					}
				}
				continue;
			}
			if($char === '"'){
				$value = '';
				$i++;
				for(; $i < $length; $i++){
					$c = $sql[$i];
					if($c === '\\' && $i + 1 < $length){
						$n = $sql[$i + 1];
						if($n === '"' || $n === '\'' || $n === '\\'){
							$value .= $n;
						}else if($n === '0'){
							$value .= "\0";
						}else{
							$value .= '\\' . $n;
						}
						$i++;
						continue;
					}
					if($c === '"'){
						break;
					}
					$value .= $c;
				}
				$return .= '\'' . str_replace('\'', '\'\'', $value) . '\'';
				continue;
			}
			$return .= $char;
		}
		return $return;
	}
}
?>
