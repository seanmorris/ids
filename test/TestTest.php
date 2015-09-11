<?php
chdir(dirname(__FILE__));
$composer = require '../source/init.php';
class TestingTestCase extends UnitTestCase
{
	public function setUp()
	{
	}

	public function testEquals()
	{
	}

	public function tearDown()
	{
	}
}
$test = new TestingTestCase('Testing Unit Test');
$test->run(new TextReporter());