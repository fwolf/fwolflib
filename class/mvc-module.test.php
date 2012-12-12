<?php
/**
 * Test - MVC Module class
 *
 * @package		fwolflib
 * @subpackage	class.test
 * @copyright	Copyright 2012, Fwolf
 * @author      Fwolf <fwolf.aide+fwolflib.class.test@gmail.com>
 * @since		2012-12-10
 */


// Define like this, so test can run both under eclipse and web alone.
// {{{
if (! defined('SIMPLE_TEST')) {
	define('SIMPLE_TEST', 'simpletest/');
	require_once(SIMPLE_TEST . 'autorun.php');
}
// Then set output encoding
//header('Content-Type: text/html; charset=utf-8');
// }}}

// Require library define file which need test
require_once(dirname(__FILE__) . '/fwolflib.php');
require_once(dirname(__FILE__) . '/adodb.php');
require_once(dirname(__FILE__) . '/mvc-module.php');
require_once(dirname(__FILE__) . '/../func/ecl.php');
require_once(dirname(__FILE__) . '/../func/request.php');
require_once(dirname(__FILE__) . '/../func/uuid.php');


class TestModule extends UnitTestCase {

	/**
	 * Module object
	 * @var	object
	 */
	protected $oModule = NULL;


	/**
	 * Constructor
	 */
	public function __construct () {
		$this->oModule = new ModuleTest();

		// Define dbprofile
		$this->oModule->SetCfg('dbprofile', array(
			'type'	=> 'mysqli',
			'host'	=> 'localhost',
			'user'	=> 'test',
			'pass'	=> '',
			'name'	=> 'test',
			'lang'	=> 'utf-8',
		));
		$this->oModule->oDb;
	} // end of func __construct


	function TestDbDiff () {
		// Create test table
		$this->oModule->oDb->Execute('
			CREATE TABLE t1 (
				uuid	CHAR(36) NOT NULL,
				i		INTEGER NULL DEFAULT 0,
				s		VARCHAR(20) NULL,
				d		DATETIME NULL,
				PRIMARY KEY (uuid, i)
			);
		');
		$this->oModule->oDb->Execute('
			CREATE TABLE t2 (
				uuid	CHAR(36) NOT NULL,
				i		INTEGER NULL DEFAULT 0,
				s		VARCHAR(20) NULL,
				d		DATETIME NULL,
				PRIMARY KEY (uuid)
			);
		');


		// Test Adodb::GetDataByPk()
		$uuid = Uuid();
		$this->oModule->oDb->Execute('
			INSERT INTO t1
			VALUES ("' . $uuid . '", 12, "blah"
				, "' . date('Y-m-d H:i:s') . '")
		');
		$this->assertEqual(12, $this->oModule->oDb->GetDataByPk(
			't1', $uuid, 'i', 'uuid'));
		$this->assertEqual(array('i' => 12, 's' => 'blah')
			, $this->oModule->oDb->GetDataByPk(
			't1', array($uuid, 12), ' i , s ,'));


		// Write data using DbDiff()
		$uuid = Uuid();

		// New array has few PK
		$ar_new = array(
			'uuid'	=> $uuid,
//			'i'		=> mt_rand(0, 100),
			's'		=> RandomString(10),
			'd'		=> date('Y-m-d H:i:s'),
		);
		$ar_diff = $this->oModule->DbDiff(array('t1' => $ar_new));
		$this->assertEqual(-2, $ar_diff['code']);

		// Normal new array
		$ar_new = array(
			'uuid'	=> $uuid,
			'i'		=> mt_rand(0, 100),
			's'		=> RandomString(10),
			'd'		=> date('Y-m-d H:i:s'),
		);
		$ar_diff = $this->oModule->DbDiff(array('t1' => $ar_new));
		$this->assertEqual($ar_diff['diff']['t1'][0]['mode'], 'INSERT');
		$this->assertEqual(count($ar_diff['diff']['t1'][0]['pk']), 2);
		$this->assertEqual(count($ar_diff['diff']['t1'][0]['col']), 2);
		$ar_diff = $this->oModule->DbDiff(array('t2' => $ar_new));
		$this->assertEqual($ar_diff['diff']['t2'][0]['mode'], 'INSERT');
		$this->assertEqual(count($ar_diff['diff']['t2'][0]['pk']), 1);
		$this->assertEqual(count($ar_diff['diff']['t2'][0]['col']), 3);

		// New array has only PK
		$ar_new = array(
			'uuid'	=> $uuid,
			'i'		=> mt_rand(0, 100),
		);
		$ar_diff = $this->oModule->DbDiff(array('t1' => $ar_new));
		$this->assertEqual($ar_diff['diff']['t1'][0]['mode'], 'INSERT');
		$this->assertEqual(count($ar_diff['diff']['t1'][0]['pk']), 2);
		$this->assertEqual(count($ar_diff['diff']['t1'][0]['col']), 0);
		$ar_new = array(
			'uuid'	=> $uuid,
		);
		$ar_diff = $this->oModule->DbDiff(array('t2' => $ar_new));
		$this->assertEqual($ar_diff['diff']['t2'][0]['mode'], 'INSERT');
		$this->assertEqual(count($ar_diff['diff']['t2'][0]['pk']), 1);
		$this->assertEqual(count($ar_diff['diff']['t2'][0]['col']), 0);


		// Clean up
		$this->oModule->oDb->Execute('
			DROP TABLE t1;
		');
		$this->oModule->oDb->Execute('
			DROP TABLE t2;
		');
    } // end of func TestDbDiff


} // end of class TestModule


class ModuleTest extends Module {


	/**
	 * Constructor
	 */
	public function __construct () {
		parent::__construct();

	} // end of func __construct


	/**
	 * Connect to db, using func defined in include file, check error here.
	 *
	 * <code>
	 * $s = array(type, host, user, pass, name, lang);
	 * type is mysql/sybase_ase etc,
	 * name is dbname to select,
	 * lang is db server charset.
	 * </code>
	 *
	 * Useing my extended ADODB class now, little difference when new object.
	 * @var array	$dbprofile	Server config array
	 * @return object			Db connection object
	 */
	protected function DbConn ($dbprofile) {
		$conn = new Adodb($dbprofile);
		$conn->Connect();

		if (0 !=$conn->ErrorNo()) {
			// Display error
			$s = 'ErrorNo: ' . $conn->ErrorNo() . "<br />\nErrorMsg: " . $conn->ErrorMsg();
			return NULL;
		}
		else
			return $conn;
	} // end of func DbConn


	public function Init () {
		parent::Init();

		return $this;
	} // end of func Init


} // end of class ModuleTest


// Change output charset in this way.
// {{{
$s_url = GetSelfUrl(false);
$s_url = substr($s_url, strrpos($s_url, '/') + 1);
if ('mvc-module.test.php' == $s_url) {
	$test = new TestModule();
	$test->run(new HtmlReporter('utf-8'));
}
// }}}
?>
