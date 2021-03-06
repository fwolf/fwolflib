<?php
// Set include path in __construct
//require_once('adodb/adodb.inc.php');
require_once(dirname(__FILE__) . '/fwolflib.php');
require_once(dirname(__FILE__) . '/sql_generator.php');
require_once(dirname(__FILE__) . '/../func/ecl.php');
require_once(dirname(__FILE__) . '/../func/string.php');


/**
 * Extended ADODB class
 *
 * Include all ADODB had, and add a little others.
 *
 * Piror use this class' method and property, if the get/set/call target
 * is not exists, use original ADODB's, this can be done by php's mechematic
 * of overload __call __get __set.
 *
 * 这似乎是extend ADODB的一种比较好的方式，比官方网站文档上给的按不同数据库来继承子类的方式，
 * 我认为要更方便一些。缺点是没有对RecordSet对象进行处理。
 *
 * Adodb for Sybase bug:
 * Affected_Rows() in windows 2003 不可用，httpd 进程会出错中止
 *
 * 执行sql查询的系列更改中，限定系统/HTML/PHP使用$sSysCharset指定的编码，涉及函数列在__call()中，
 * 但一些通过数组等其它方式传递参数的ADODB方法仍然无法通过这种方式实现sql编码自动转换。
 *
 * 执行返回的数据还是需要转码的，不过返回数据的种类太多，放在应用中实现更简单一些，这里不自动执行，
 * 只提供一个EncodingConvert方法供用户调用。
 *
 * @package		fwolflib
 * @subpackage	class
 * @copyright	Copyright 2008-2012, Fwolf
 * @author		Fwolf <fwolf.aide+fwolflib.class@gmail.com>
 * @since		2008-04-08
 */
class Adodb extends Fwolflib {

	/**
	 * Real ADODB connection object
	 * @var object
	 */
	protected $__conn = null;

	/**
	 * Db profile
	 * @var array
	 */
	public $aDbProfile = null;

	/**
	 * Table schema
	 *
	 * array(
	 * 	col ->	// ADOFieldObject Object, not Array !
	 * 		[name] => ts
	 * 		[max_length] => -1
	 * 		[type] => timestamp
	 * 		[scale] =>
	 * 		[not_null] => 1
	 * 		[primary_key] =>
	 * 		[auto_increment] =>
	 * 		[binary] =>
	 * 		[unsigned] =>
	 * 		[zerofill] =>
	 * 		[has_default] => 1
	 * 		[default_value] => CURRENT_TIMESTAMP
	 * 	)
	 * )
	 * @var array
	 */
	public $aMetaColumn = array();

	/**
	 * Table column name array, index is upper case of column name
	 *
	 * eg: array(
	 * 	'COLUMN' => 'column',
	 * )
	 * @var	array
	 */
	public $aMetaColumnName = array();

	/**
	 * Primary key columns of table
	 *
	 * array(
	 * 	tbl_name -> 'col_pk',
	 * 	tbl_name -> array(pk_col1, pk_col2),
	 * )
	 * @var	array
	 */
	public $aMetaPrimaryKey = array();

	/**
	 * Sql generator object
	 * @var object
	 */
	protected $oSg;

	/**
	 * Error msg
	 * @var	string
	 */
	public $sErrorMsg = '';

	/**
	 * System charset
	 *
	 * In common, this is your php script/operation system charset
	 * @var string
	 */
	public $sSysCharset = 'utf8';


	/**
	 * construct
	 *
	 * <code>
	 * $dbprofile = array(type, host, user, pass, name, lang);
	 * type is mysql/sybase_ase etc,
	 * name is dbname to select,
	 * lang is db server charset.
	 * </code>
	 * @var param	array	$dbprofile
	 * @var param	string	$path_adodb		Include path of original ADODB
	 */
	public function __construct ($dbprofile, $path_adodb = '') {
		parent::__construct();

		// Include original adodb lib
		if (empty($path_adodb))
			$path_adodb = 'adodb/adodb.inc.php';
		require_once($path_adodb);

		$this->aDbProfile = $dbprofile;
		$this->__conn = ADONewConnection($dbprofile['type']);

		// Sql generator object
		$this->oSg = new SqlGenerator($this);
	} // end of class __construct


	/**
	 * Overload __call, redirect method call to adodb
	 *
	 * @var string	$name	Method name
	 * @var array	$arg	Method argument
	 * @global	int	$i_db_query_times
	 * @return mixed
	 */
	public function __call ($name, $arg) {
		// Before call, convert $sql encoding first
		if ($this->sSysCharset != $this->aDbProfile['lang']) {
			// Method list by ADODB doc order
			// $sql is the 1st param
			if (in_array($name, array('Execute',
									  'CacheExecute',
									  'SelectLimit',
									  'CacheSelectLimit',
									  'Prepare',
									  'PrepareSP',
									  'GetOne',
									  'GetRow',
									  'GetAll',
									  'GetCol',
									  'CacheGetOne',
									  'CacheGetRow',
									  'CacheGetAll',
									  'CacheGetCol',
									  'GetAssoc',
									  'CacheGetAssoc',
									  'ExecuteCursor',
									)))
				$arg[0] = mb_convert_encoding($arg[0], $this->aDbProfile['lang'], $this->sSysCharset);

			// $sql is the 2nd param
			if (in_array($name, array('CacheExecute',
									  'CacheSelectLimit',
									  'CacheGetOne',
									  'CacheGetRow',
									  'CacheGetAll',
									  'CacheGetCol',
									  'CacheGetAssoc',
									)))
				$arg[1] = mb_convert_encoding($arg[1], $this->aDbProfile['lang'], $this->sSysCharset);
		}

		// Count db query times
		// Use global var so multi Adodb object can be included in count.
		//	(Done in func now)
		// Use standalone func to can be easy extend by sub class.
		if (in_array($name, array(
			'Execute', 'SelectLimit', 'GetOne', 'GetRow', 'GetAll',
			'GetCol', 'GetAssoc', 'ExecuteCursor'
			)))
			$this->CountDbQueryTimes();

		return call_user_func_array(array($this->__conn, $name), $arg);
	} // end of func __call


	/**
	 * Overload __get, redirect method call to adodb
	 *
	 * @param string	$name
	 * @return mixed
	 */
	public function __get ($name) {
		return $this->__conn->$name;
	} // end of func __get


	/**
	 * Overload __set, redirect method call to adodb
	 *
	 * @param string	$name
	 * @param mixed		$val
	 */
	public function __set ($name, $val) {
		$this->__conn->$name = $val;
	} // end of func __set


	/**
}
	 * Connect, Add mysql 'set names utf8'
	 *
	 * <code>
	 * Obmit params(dbprofile was set in __construct):
	 * param $argHostname		Host to connect to
	 * param $argUsername		Userid to login
	 * param $argPassword		Associated password
	 * param $argDatabaseName	database
	 * </code>
	 * @param $forcenew			Force new connection
	 * @return boolean
	 */
	public function Connect ($forcenew = false) {
		// Mysqli doesn't allow port in host, grab it out and set
		if ('mysqli' == strtolower($this->__conn->databaseType)) {
			$ar = array();
			$i = preg_match('/:(\d+)$/', $this->aDbProfile['host'], $ar);
			if (0 < $i) {
				$this->__conn->port = $ar[1];
				$this->aDbProfile['host'] = preg_replace('/:(\d+)$/', ''
					, $this->aDbProfile['host']);
			}
		}


		try {
			// Disable error display tempratory
			$s = ini_get("display_errors");
			ini_set("display_errors", "0");

			// Sybase will echo 'change to master' warning msg
			// :THINK: Will this problem solved if we drop default
			// database master from sa user ?
			if ($this->IsDbSybase()) {
				$rs = $this->__conn->Connect($this->aDbProfile['host'],
										 $this->aDbProfile['user'],
										 $this->aDbProfile['pass'],
										 $this->aDbProfile['name'],
										 $forcenew);
			}
			else {
				$rs = $this->__conn->Connect($this->aDbProfile['host'],
										 $this->aDbProfile['user'],
										 $this->aDbProfile['pass'],
										 $this->aDbProfile['name'],
										 $forcenew);
			}

			// Recover original error display setting
			ini_set("display_errors", $s);

			if (empty($rs)) {
				throw new Exception('Db connect fail, please check php errorlog.', -1);
			}
		} catch (Exception $e) {
			// Get error trace message
			$i_ob = ob_get_level();
			if (0 != $i_ob)
				$s_t = ob_get_clean();

			ob_start();
			adodb_backtrace($e->getTrace());
			$s_trace = ob_get_clean();

			if (0 != $i_ob) {
				// Maybe cause error if output handle of ob_start used before
				ob_start();
				echo $s_t;
			}


			// Log error
			$s_trace = "======== Adodb db connect error\n"
				. str_replace('&nbsp;', '>', strip_tags($s_trace))
				. $this->ErrorMsg() . "\n";
			$this->sErrorMsg = $s_trace;
			error_log($s_trace);

/*
			// Print error
			$this->sErrorMsg = 'Error, code '
				. $e->getCode()
				. ', msg: ' . $e->getMessage();

			// Log error
			$s_trace = str_replace('&nbsp;', '>', strip_tags($s_trace));
			error_log('');
			error_log('======== Adodb db connect error');
			error_log("\n" . $s_trace);
			error_log($this->sErrorMsg);
			//error_log('');
*/

			//var_dump($e);
			//echo $e;
			if (0 != $i_ob) {
				ob_end_flush();
			}
			//exit();
			return false;
		}

		// 针对mysql 4.1以上，UTF8编码的数据库，需要在连接后指定编码
		// Can also use $this->aDbProfile['type']
		// mysql, mysqli
		if ($this->IsDbMysql()) {
			$this->__conn->Execute('set names "' . str_replace('utf-8', 'utf8', $this->aDbProfile['lang']) . '"');
		}

		//return $rs;
		return true;
	} // end of func Connect


	/**
	 * Count how many db query have executed
	 *
	 * This function can be extend by subclass if you want to count on multi db objects.
	 *
	 * Can't count in Adodb::property, because need display is done by Controler,
	 * which will call View, but Adodb is property of Module,
	 * so we can only use global vars to save this value.
	 * @global	int	$i_db_query_times
	 */
	protected function CountDbQueryTimes () {
		global $i_db_query_times;
		$i_db_query_times ++;
	} // end of func CountDbQueryTimes


	/**
	 * Delete rows by condition user given
	 *
	 * @param	string	$tbl
	 * @param	string	$cond	Condition, can be where, having etc, raw sql string, not null.
	 * @return	int		-1 error/0 not found/N > 0 number of rows
	 */
	public function DelRow ($tbl, $cond) {
		$cond = trim($cond);
		if (empty($cond))
			return -1;
		$this->PExecute($this->GenSql(array(
			'DELETE' => $tbl,
			)) . ' ' . $cond);
		if (0 != $this->ErrorNo())
			// Execute error
			return -1;
		else
			return $this->Affected_Rows();
	} // end of func DelRow


	/**
	 * Convert recordset(simple array) or other string
	 * from db encoding to system encoding
	 *
	 * Use recursive mechanism, beware of loop hole.
	 * @param mixed	&$s	Source to convert
	 * @return mixed
	 */
	public function EncodingConvert (&$s) {
		if (is_array($s) && !empty($s)) {
			foreach ($s as &$val)
				$this->EncodingConvert($val);
			unset($val);
		}

		if (is_string($s)) {
			if ($this->sSysCharset != $this->aDbProfile['lang'])
				$s = mb_convert_encoding($s, $this->sSysCharset, $this->aDbProfile['lang']);
		}
		return $s;
	} // end of func EncodingConvert


	/**
	 * Convert data encoding
	 * from system(usually utf-8) to db
	 *
	 * Use recursive mechanism, beware of loop hole.
	 * @param mixed	&$s	Source to convert
	 * @return mixed
	 */
	public function EncodingConvertReverse (&$s) {
		if (is_array($s) && !empty($s)) {
			foreach ($s as &$val)
				$this->EncodingConvertReverse($val);
			unset($val);
		}

		if (is_string($s)) {
			if ($this->sSysCharset != $this->aDbProfile['lang'])
				$s = mb_convert_encoding($s, $this->aDbProfile['lang'], $this->sSysCharset);
		}
		return $s;
	} // end of func EncodingConvertReverse


	/**
	 * Generate SQL then exec it
	 *
	 * @param	array	$ar_sql		Same as GenSql()
	 * @return	object
	 * @see	GenSql()
	 */
	public function ExecuteGenSql ($ar_sql) {
		return $this->Execute($this->GenSql($ar_sql));
	} // end of func ExecuteGenSql


	/**
	 * Find name of timestamp column of a table
	 *
	 * @param	$tbl	Table name
	 * @return	string
	 */
	public function FindColTs ($tbl) {
		$ar_col = $this->GetMetaColumn($tbl);
		if (empty($ar_col))
			return '';

		if ($this->IsDbSybase()) {
			// Sybase's timestamp column must be lower cased
			// Can name as others, but name as 'timestamp' will auto got )timestamp) type.
			/*
			if (isset($ar_col['timestamp']))
				return 'timestamp';
			else
				return '';
			*/
			// New way:
			// http://bbs.chinaunix.net/archiver/tid-930729.html
			$rs = $this->ExecuteGenSql(array(
				'SELECT' => array(
					'name'		=> 'a.name',
					'length' 	=> 'a.length',
					'usertype'	=> 'a.usertype',
					'type'		=> 'b.name',
					),
				'FROM'	=> array(
					'a' => 'syscolumns',
					'b' => 'systypes'
					),
				'WHERE' => array(
					"a.id = object_id('$tbl')",
					'a.type = b.type',
					'a.usertype = b.usertype',
					'b.name = "timestamp"',		// Without this line, can retrieve sybase's col info
					),
				));
			if (!empty($rs) && 0 < $rs->RowCount())
				return $rs->fields['name'];
			else
				return '';
			//select a.name,a.length,a.usertype,b.name AS type from syscolumns a ,systypes b
			//where id = object_id('ztb_yh') and a.type=b.type and a.usertype = b.usertype

		}
		elseif ($this->IsDbMysql()) {
			// Check 'type'
			foreach ($ar_col as $k => $v)
				if (isset($v->type) && 'timestamp' == $v->type)
					return $k;
		}
		else {
			die("FindColTs not implemented!\n");
		}
	} // end of func FindColTs


	/**
	 * Generate SQL statement
	 *
	 * User should avoid use SELECT/UPDATE/INSERT/DELETE simultaneously.
	 *
	 * Generate order by SQL statement format order.
	 *
	 * UPDATE/INSERT/DELETE is followed by [TBL_NAME],
	 * so need not use FROM.
	 * @param array	$ar_sql	Array(select=>..., from=>...)
	 * @return string
	 * @see	SqlGenerator
	 */
	public function GenSql ($ar_sql) {
		// Changed to use SqlGenerator
		if (!empty($ar_sql)) {
			return $this->oSg->GetSql($ar_sql);
		}
		else
			return '';
	} // end of func GenSql


	/**
	 * Generate SQL statement for Prepare
	 *
	 * value -> ? or :name, and quote chars removed
	 * @param	array	$ar_sql	Same as GenSql()
	 * @return	string
	 * @see	GenSql()
	 * @see	SqlGenerator
	 */
	public function GenSqlPrepare ($ar_sql) {
		if (!empty($ar_sql))
			return $this->oSg->GetSqlPrepare($ar_sql);
		else
			return '';
	} // end of func GenSqlPrepare


	/**
	 * Get data from single table using PK
	 *
	 * $m_pk, $col, $col_pk support string split by ',' or array, like:
	 * 1. 'val'
	 * 2. 'val1, val2'
	 * 3. array('val1', 'val2')
	 *
	 * '*' can be used for $col, means all cols in table, this way can't use
	 * cache, not recommend.
	 *
	 * Notice: $col must indexed by number start from 0.
	 *
	 * Also, this function can be used to retrieve data from a table with
	 * single unique index, by assigning $col_pk to non-PK column.
	 *
	 * @param	string	$s_tbl
	 * @param	mixed	$m_pk			PK value
	 * @param	mixed	$col			Cols need to retrieve.
	 * @param	mixed	$col_pk			PK column name, NULL to auto get.
	 * @return	mixed					Single/array, NULL if error occur.
	 */
	public function GetDataByPk ($s_tbl, $m_pk, $col = NULL, $col_pk = NULL) {
		// Treat PK col
		if (empty($col_pk)) {
			$col_pk = $this->GetMetaPrimaryKey($s_tbl);
		}

		// PK and col name all convert to array
		if (!is_array($m_pk)) {
			if (is_string($m_pk))
				$m_pk = StrToArray($m_pk, ',');
			else
				$m_pk = array($m_pk);
		}
		if (!is_array($col_pk)) {
			if (is_string($col_pk))
				$col_pk = StrToArray($col_pk, ',');
			else
				$col_pk = array($col_pk);
		}

		// $col_pk need to be array same count with $m_pk
		if (count($m_pk) != count($col_pk)) {
			$this->Log('PK value and column not match.', 4);
			return NULL;
		}

		// Treat col
		if (empty($col))
			$col = '*';
		if ('*' == $col)
			// Drop uppercased index
			$col = array_values($this->GetMetaColumnName($s_tbl));
		if (!is_array($col)) {
			if (is_string($col))
				// String split by ',', style 'col AS col_alias' allowed
				$col = StrToArray($col, ',');
			else
				$col = array($col);
		}

		// $m_pk, $col, $col_pk all converted to array

		// Retrieve from db
		$ar_sql = array(
			'SELECT'	=> $col,
			'FROM'		=> $s_tbl,
			'LIMIT'		=> 1,
		);
		while (!empty($m_pk)) {
			$s_col_pk = array_shift($col_pk);
			$ar_sql['WHERE'][] = $s_col_pk . ' = '
				. $this->QuoteValue($s_tbl, $s_col_pk, array_shift($m_pk));
			unset($s_col_pk);
		}
		$rs = $this->ExecuteGenSql($ar_sql);
		$ar_rs = array();
		if (!empty($rs) && !$rs->EOF) {
			$ar_rs = $rs->GetRowAssoc(false);
		}

		// Return value
		if (empty($ar_rs))
			return NULL;
		else {
			if (1 == count($ar_rs))
				return array_pop($ar_rs);
			else
				return $ar_rs;
		}
	} // end of func GetDataByPk


	/**
	 * Get table schema
	 *
	 * @param	string	$table
	 * @param	boolean	$forcenew	Force to retrieve instead of read from cache
	 * @return	array
	 * @see $aMetaColumn
	 */
	public function GetMetaColumn ($table, $forcenew = false) {
		if (!isset($this->aMetaColumn[$table]) || (true == $forcenew)) {
			$this->aMetaColumn[$table] = $this->MetaColumns($table);

			// Convert columns to native case
			$col_name = $this->GetMetaColumnName($table);
			// $col_name = array(COLUMN => column), $c is UPPER CASED
			foreach ($this->aMetaColumn[$table] as $c => $ar) {
				$this->aMetaColumn[$table][$col_name[$c]] = $ar;
				unset($this->aMetaColumn[$table][$c]);
			}
			// Fix: sybase db display timestamp column as varbinary
			if ($this->IsDbSybase()) {
				$s = $this->FindColTs($table);
				if (!empty($s))
					$this->aMetaColumn[$table][$s]->type = 'timestamp';
			}
			//print_r($this->aMetaColumn);
		}
		return $this->aMetaColumn[$table];
	} // end of func GetMetaColumn


	/**
	 * Get table column name
	 *
	 * @param	string	$table
	 * @param	boolean	$forcenew	Force to retrieve instead of read from cache
	 * @return	array
	 * @see $aMetaColumnName
	 */
	public function GetMetaColumnName ($table, $forcenew = false) {
		if (!isset($this->aMetaColumnName[$table]) || (true == $forcenew)) {
			$this->aMetaColumnName[$table] = $this->MetaColumnNames($table);
		}
		return $this->aMetaColumnName[$table];
	} // end of func GetMetaColumnName


	/**
	 * Get primary key column of a table
	 *
	 * @param	string	$table
	 * @param	boolean	$forcenew	Force to retrieve instead of read from cache
	 * @return	mixed	Single string value or array when primary key contain multi columns.
	 * @see $aMetaPrimaryKey
	 */
	public function GetMetaPrimaryKey ($table, $forcenew = false) {
		if (!isset($this->aMetaPrimaryKey[$table]) || (true == $forcenew)) {
			// Find using Adodb first
			$ar = $this->MetaPrimaryKeys($table);
			if (false == $ar || empty($ar)) {
				// Adodb not support, find by hand
				// Sybase
				// 	keys1、keys2、keys3的描述不清，应该是：
				//	select name ,keycnt
				//		,index_col(YourTableName,indid,1)   --主键中的第一列
				//		,index_col(YourTableName,indid,2)   --主键中的第二列，如果有的话
				//	from   sysindexes
				//	where   status   &   2048=2048
				//		and   id=object_id(YourTableName)
				// 主键涉及的列的数量在keycnt中。如果主键索引不是簇集索引（由status中的0x10位决定）的话，则为keycnt-1。
				// http://topic.csdn.net/t/20030117/17/1369396.html
				// 根据这种方法，目前好像只能用于主键包含三个以下字段的情况？
				// 已测试过主键包含两个字段的情况下能取出来
				/*
				 select name, keycnt, index_col('sgqyjbqk', indid, 1)
				, index_col('sgqyjbqk', indid, 2)
				, index_col('sgqyjbqk', indid, 3)
				from sysindexes
				where status & 2048 = 2048
					and id = object_id('sgqyjbqk')
				 */
				if ($this->IsDbSybase()) {
					$rs = $this->PExecuteGenSql(array(
						'select' => array('name', 'keycnt',
							'k1' => "index_col('$table', indid, 1)",
							'k2' => "index_col('$table', indid, 2)",
							'k3' => "index_col('$table', indid, 3)",
							),
						'from'	=> 'sysindexes',
						'where' => array(
							'status & 2048 = 2048 ',
							"id = object_id('$table')",
							)
						));
					if (true == $rs && 0 < $rs->RowCount()) {
						// Got
						$ar = array($rs->fields['k1']);
						if (!empty($rs->fields['k2']))
							$ar[] = $rs->fields['k2'];
						if (!empty($rs->fields['k3']))
							$ar[] = $rs->fields['k3'];
					}
					else {
						// Table have no primary key
						$ar = '';
					}
				}
			}

			// Convert columns to native case
			if (!empty($ar)) {
				$col_name = $this->GetMetaColumnName($table);
				// $col_name = array(COLUMN => column), $c is UPPER CASED
				foreach ($ar as $idx => &$col) {
					if ($col != $col_name[strtoupper($col)]) {
						unset($ar[$idx]);
						$ar[] = $col_name[strtoupper($col)];
					}
				}
				unset($col);
			}

			if (is_array($ar) && 1 == count($ar))
				// Only 1 primary key column
				$ar = $ar[0];

			// Set to cache
			if (!empty($ar))
				$this->aMetaPrimaryKey[$table] = $ar;
		}
		if (isset($this->aMetaPrimaryKey[$table]))
			return $this->aMetaPrimaryKey[$table];
		else
			return '';
	} // end of func GetMetaPrimaryKey


	/**
	 * Get rows count by condition user given
	 *
	 * @param	string	$tbl
	 * @param	string	$cond	Condition, can be where, having etc, raw sql string.
	 * @return	int		-1: error/N >= 0: number of rows
	 */
	public function GetRowCount ($tbl, $cond = '') {
		$rs = $this->PExecute($this->GenSql(array(
			'SELECT' => array('c' => 'count(1)'),
			'FROM'	=> $tbl,
			)) . ' ' . $cond);
		if (false == $rs || 0 != $this->ErrorNo()
				|| 0 == $rs->RowCount())
			// Execute error
			return -1;
		else
			return $rs->fields['c'];
	} // end of func GetRowCount


	/**
	 * Get delimiter between SQL for various db
	 *
	 * @return	string
	 */
	public function GetSqlDelimiter () {
		if ($this->IsDbMysql())
			return ";\n";
		elseif ($this->IsDbSybase())
			return "\n";
		else {
			$this->Log('GetSqlDelimiter() for this kind of db not implement.'
				, 5);
			return "\n";
		}
	} // end of func GetSqlDelimiter


	/**
	 * Get SQL: begin transaction
	 *
	 * @return	string
	 */
	public function GetSqlTransBegin () {
		if ($this->IsDbMysql())
			return 'START TRANSACTION' . $this->GetSqlDelimiter();
		else
			return 'BEGIN TRANSACTION' . $this->GetSqlDelimiter();
	} // end of func GetSqlTransBegin


	/**
	 * Get SQL: commit transaction
	 *
	 * @return	string
	 */
	public function GetSqlTransCommit () {
		return 'COMMIT' . $this->GetSqlDelimiter();
	} // end of func GetSqlTransCommit


	/**
	 * Get SQL: rollback transaction
	 *
	 * @return	string
	 */
	public function GetSqlTransRollback () {
		return 'ROLLBACK' . $this->GetSqlDelimiter();
	} // end of func GetSqlTransRollback


	/**
	 * If current db is a mysql db.
	 *
	 * @return	boolean
	 */
	public function IsDbMysql () {
		return ('mysql' == substr($this->__conn->databaseType, 0, 5));
	} // end of func IsDbMysql


	/**
	 * If current db is a sybase db.
	 *
	 * @return	boolean
	 */
	public function IsDbSybase () {
		return ('sybase' == substr($this->aDbProfile['type'], 0, 6));
	} // end of func IsDbSybase


	/**
	 * Is timestamp column's value is unique
	 *
	 * @return	boolean
	 */
	public function IsTsUnique () {
		if ('sybase' == $this->IsDbSybase())
			return true;
		else
			// Mysql
			return false;
	} // end of func IsTsUnique


	/**
	 * Prepare and execute sql
	 *
	 * @param	string	$sql
	 * @param	array	$inputarr	Optional parameters in sql
	 * @return	object
	 */
	public function PExecute ($sql, $inputarr = false) {
		$stmt = $this->Prepare($sql);
		$this->BeginTrans();
		$rs = $this->Execute($stmt, $inputarr);
		if (0 != $this->ErrorNo()) {
			// Log to error log file
			error_log('ErrorNo: ' . $this->ErrorNo()
				. "\nErrorMsg: " . $this->ErrorMsg()
				);
			$this->RollbackTrans();
			return -1;
		}
		$this->CommitTrans();
		return $rs;
	} // end of PExecute


	/**
	 * Generate, prepare and exec SQL
	 *
	 * @param	array	$ar_sql		Same as GenSql()
	 * @param	array	$inputarr	Optional parameters in sql
	 * @return	object
	 * @see	GenSql()
	 */
	public function PExecuteGenSql ($ar_sql, $inputarr = false) {
		return $this->PExecute($this->GenSqlPrepare($ar_sql), $inputarr);
	} // end of func PExecuteGenSql


	/**
	 * Smarty quote string in sql, by check columns type
	 *
	 * @param	string	$table
	 * @param	string	$column
	 * @param	mixed	$val
	 * @return	string
	 */
	public function QuoteValue ($table, $column, $val) {
		$this->GetMetaColumn($table);
		if (!isset($this->aMetaColumn[$table][$column]->type)) {
			error_log("Column to quote not exists($table.$column).\n");
			// Return quoted value for safety
			$val = stripslashes($val);
			return $this->qstr($val, false);
		}

		//print_r($this->aMetaColumn[$table][$column]);
		$type = $this->aMetaColumn[$table][$column]->type;
		//var_dump($type);
		if (in_array($type, array(
				'bigint',
				'bit',
				'decimal',
				'double',
				'float',
				'int',
				'intn', // Sybase - tinyint
				'mediumint',
				'numeric',
				'numericn',	// Sybase - numeric
				'real',
				'smallint',
				'tinyint',
			)))
			// Need not quote, output directly
			return $val;
		// Sybase timestamp
		//elseif ($this->IsDbSybase() && 'varbinary' == $type && 'timestamp' == $column)
		elseif ($this->IsDbSybase() && 'timestamp' == $type)
			return '0x' . $val;
		else {
			// Need quote, use db's quote method
			$val = stripslashes($val);
			return $this->qstr($val, false);
		}
	} // end of func GenSqlQuote


	/**
	 * If a table exists in db ?
	 *
	 * @param	string	$tbl
	 * @return	boolean
	 */
	public function TblExists ($tbl) {
		if ($this->IsDbSybase()) {
			$sql = "select count(1) as c from sysobjects where name = '$tbl' and type = 'U'";
			$rs = $this->Execute($sql);
			return (0 != $rs->fields['c']);
		}
		elseif ($this->IsDbMysql()) {
			$sql = "SHOW TABLES LIKE '$tbl'";
			$rs = $this->Execute($sql);
			return (0 != $rs->RowCount());
		}
		else {
			// :NOTICE: Un-tested method
			$sql = "select 1 from $tbl";
			$rs = $this->Execute($sql);
			return (0 == $this->ErrorNo());
		}
	} // end of func TblExists


	/**
	 * Smart write data row(s) to table
	 *
	 * Will auto check row existence, and decide to use INSERT or UPDATE,
	 * so PRIMARY KEY column must include in $data array.
	 * Also, table must have primary key defined.
	 * @param	string	$tbl	Table which rows to write to
	 * @param	array	$data	Row(s) data, only one row(1-dim array, index is column name)
	 * 							or some rows(2-dim array, index layer 1 MUST be number and
	 * 							will not write to db).
	 * @param	string	$mode	A auto detect/U update/I insert, ignore case.
	 * 							If you assign some rows, it's better not to set this to 0,
	 * 							because it will only detect by the FIRST row data.
	 * @return	int		Number of inserted or updated rows, -1 means some error,
	 * 					0 and upper are normal result.
	 */
	public function Write ($tbl, $data, $mode = 'A') {
		// Find primary key column first
		$pk = $this->GetMetaPrimaryKey($tbl);

		// Convert single row data to multi row mode
		if (!isset($data[0]))
			$data = array(0 => $data);
		// Convert primary key to array if it's single string now
		if (!is_array($pk))
			$pk = array(0 => $pk);

		// Columns in $data
		$ar_cols = array_keys($data[0]);
		// Check if primary key is assigned in $data
		$b_data_ok = true;
		foreach ($pk as $key)
			if (!in_array($key, $ar_cols))
				$b_data_ok = false;
		// If no primary key column in $data, return -1
		if (false == $b_data_ok)
			return -1;

		$mode = strtoupper($mode);
		// Consider mode if user not assigned
		if ('A' == $mode) {
			$s_where = ' WHERE ';
			foreach ($pk as $key)
				$s_where .= " $key = " . $this->QuoteValue($tbl, $key, $data[0][$key])
					. ' AND ';
			$s_where = substr($s_where, 0, strlen($s_where) - 5);
			if (0 < $this->GetRowCount($tbl, $s_where))
				$mode = 'U';
			else
				$mode = 'I';
		}

		// Do batch update or insert, prepare stmt first
		$sql = '';
		if ('U' == $mode) {
			$ar_conf = array(
				'UPDATE' => $tbl,
				'LIMIT' => 1,
				);
			foreach ($pk as $key) {
				// Primary key need remove from 'SET' clause
				// Actual value will assign later, do quote then.
				// :NOTICE: Remember to put pk data to end of row data when assign,
				//	because where clause is after set clause.
				$ar_conf['WHERE'][] = "$key = "
					. $this->Param($key);
				unset($ar_cols[array_search($key, $ar_cols)]);
			}
			// Convert array $ar_cols with to prepare param
			$ar_set = array();
			foreach ($ar_cols as $key)
				$ar_set[$key] = $this->Param($key);
			// Fin, assign 'SET' clause
			$ar_conf['SET'] = $ar_set;
		}
		elseif ('I' == $mode) {
			$ar_set = array();
			foreach ($ar_cols as $key) {
				$ar_set[$key] = $this->Param($key);
			}
			$ar_conf = array(
				'INSERT' => $tbl,
				'VALUES' => $ar_set,
				);
		}
		$sql = $this->GenSqlPrepare($ar_conf);

		/* Treat moved to  SqlGenerator
		//$sql = $this->GenSql($ar_conf);
		// Remove duplicate ' in sql add by SqlGenerator,
		// Execute after Prepare will auto recoginize variant type and quote,
		// but notice, it's VAR TYPE and NOT DB COLUMN TYPE.
		// replaceQuote: The string used to escape quotes. Eg. double single-quotes for
		// Microsoft SQL, and backslash-quote for MySQL. Used by qstr.
		if ("''" == $this->replaceQuote)
			$s_quote = "'";
		else
			$s_quote = $this->replaceQuote;
		$sql = preg_replace(
			"/ {$s_quote}([\?\:\w\-_]+){$s_quote}([, ])/i",
			" $1$2", $sql);
		*/

		if (!empty($sql)) {
			// Do prepare
			$stmt = $this->Prepare($sql);
			// Execute
			if ('U' == $mode) {
				foreach ($data as &$row) {
					// Change pk's value position when update mode
					foreach ($pk as $key) {
						$v = $row[$key];
						unset($row[$key]);
						$row[$key] = $v;
					}
				}
				unset($row);
			}
			// Now, finanly, actual write data
			// Auto convert encoding ?
			// Use of prepare we must convert $data manually, because $data is not sql.
			$this->EncodingConvert($data);
			try {
				$this->Execute($stmt, $data);
			}
			catch (Exception $e) {
				// Show error message ?
				$this->RollbackTrans();
				return -1;
			}
			// Any error ?
			if (0 != $this->ErrorNo()) {
				// Log to error log file
				error_log('ErrorNo: ' . $this->ErrorNo()
					. "\nErrorMsg: " . $this->ErrorMsg()
					);
				$this->RollbackTrans();
				return -1;
			}
			else {
				$this->CommitTrans();
				return count($data);
			}
		}
		else
			return -1;
	} // end of func Write


} // end of class Adodb
?>
