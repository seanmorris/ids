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

		$linkerClass::set($testKey, $testValue);

		$linkerClass::link();

		$packageData = $linkerClass::get($testKey, $package);

		$this->assertEqual($packageData, $testValue);

		$globalData = $linkerClass::get($testKey);

		$this->assertEqual($globalData[$package], $testValue);

		$flatData = $linkerClass::get($testKey, TRUE);

		$this->assertEqual($flatData, $testValue);
	}
}
