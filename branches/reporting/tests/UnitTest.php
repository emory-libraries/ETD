<?
if (! defined('SIMPLE_TEST')) {
    define('SIMPLE_TEST', 'simpletest/');
}
require_once(SIMPLE_TEST . 'unit_tester.php');
require_once(SIMPLE_TEST . 'reporter.php');

class UnitTest extends UnitTestCase
{
	protected function loadDB($sqlFile)
	{
		$db = Zend_Registry::get('db');
		
		$sql = file_get_contents($sqlFile);
		
		$stmt = $db->query($sql);
	}	
}
?>