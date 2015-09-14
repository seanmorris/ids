<?php
namespace SeanMorris\Ids\Test;
class PackageTest extends \UnitTestCase
{
	public function setUp()
	{
		$this->database = \SeanMorris\Ids\Database::get('main');
		$package =$this->package = \SeanMorris\Ids\Package::get('SeanMorris\Ids');
		$package::setTables(['main' => ['Foobar', 'Foozle']]);
	}

	public function testApplySchema()
	{
		$package = \SeanMorris\Ids\Package::get('SeanMorris\Ids');

		$testSchemaFile = new \SeanMorris\Ids\Storage\Disk\File(
			$package->globalDir() . 'testApplySchema.json'
		);

		$schemaFile = $testSchemaFile->copy($package->globalDir() . 'schema.json');

		$package->applySchema();
		
		foreach($package->tables() as $dbName => $tables)
		{
			$db = \SeanMorris\Ids\Database::get($dbName);

			$showTables = $db->prepare('SHOW TABLES');
			$showTables->execute();

			$existingTables = [];

			while($table = $showTables->fetchColumn())
			{
				$existingTables[$table] = $table;
			}

			foreach ($tables as $table)
			{
				$this->assertTrue(
					isset($existingTables[$table])
					, sprintf('Failed to create table %s', $table)
				);
			}
		}
	}

	public function testStoreSchema()
	{
		// $this->package->storeSchema();
	}
}