<?php
namespace SeanMorris\Ids\Test;
class LinkerTest extends \UnitTestCase
{
	public function testLinker()
	{
		$namespace = 'SeanMorris\Ids';
		$package = strtolower($namespace);
		$linkerClass = $namespace . '\Linker';
		$testKey = 'Test@' . time();
		$testValue = ['testValue', 'blah'];

		$linkerClass::link();

		$linkerClass::set($testKey, $testValue);

		$returnValue = $linkerClass::get($testKey);

		$this->assertEqual($testValue, $returnValue);
	}
}
