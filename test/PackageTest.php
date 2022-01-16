<?php
namespace SeanMorris\Ids\Test;
class PackageTest extends \UnitTestCase
{
	protected $setUp, $package;

	public function setUp()
	{
		$this->database = \SeanMorris\Ids\Database::get('main');
		$this->package  = \SeanMorris\Ids\Package::get('SeanMorris/Ids');

		foreach($this->package->tables() as $dbName => $tables)
		{
			$db = \SeanMorris\Ids\Database::get($dbName);

			foreach($tables as $table)
			{
				$dropTable = $db->prepare(sprintf(
					'DROP TABLE IF EXISTS %s'
					, $table
				));

				$dropTable->execute();
			}
		}

		$this->package::setTables([
			'main' => ['Foobar', 'Foozle']
		]);
	}

	public function tearDown()
	{
		$this->package = \SeanMorris\Ids\Package::get('SeanMorris/Ids');

		$testSchemaFile = \SeanMorris\Ids\Disk\File::open(
			$this->package->globalDir() . '_schema.json'
		);

		$testSchemaFile->copy(
			$this->package->globalDir() . 'schema.json'
		);
	}

	public function testApplySchema()
	{
		$testSchemaFile = \SeanMorris\Ids\Disk\File::open(
			$this->package->packageDir()
			. 'test/data/testApplySchema.json'
		);

		$schemaFile = $testSchemaFile->copy($this->package->globalDir() . 'schema.json');

		$this->package->applySchema(TRUE);

		foreach($this->package->tables() as $dbName => $tables)
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
		$testSchemaFile = \SeanMorris\Ids\Disk\File::open(
			$this->package->packageDir()
			. 'test/data/testApplySchema.json'
		);

		$testSchemaFile->copy(
			$this->package->globalDir() . 'schema.json'
		);

		$this->package->applySchema(TRUE);

		$this->package->storeSchema();

		$schemaFile = \SeanMorris\Ids\Disk\File::open(
			$this->package->globalDir() . 'schema.json'
		);

		\SeanMorris\Ids\Log::debug($schemaFile->slurp(), $testSchemaFile->slurp());

		$this->assertEqual(
			$schemaFile->slurp()
			, $testSchemaFile->slurp()
			, 'Unexpected output from storeSchema'
		);
	}

	public function testColumnAddApplySchema()
	{
		$this->package->storeSchema();

		$testSchemaFile = \SeanMorris\Ids\Disk\File::open(
			$this->package->packageDir()
			. 'test/data/testColumnAddSchema.json'
		);

		$testSchemaFile->copy(
			$this->package->globalDir() . 'schema.json'
		);

		$this->package->applySchema(TRUE);

		$db = \SeanMorris\Ids\Database::get('main');

		$sth = $db->prepare('SHOW COLUMNS FROM Foozle WHERE field LIKE ?');

		$sth->execute(['caption']);

		$this->assertTrue(
			$sth->fetchObject()
			, 'Failed to create column "caption" on table "Foozle"'
		);
	}

	public function testColumnRemoveApplySchema()
	{
		$this->package->storeSchema();

		$testSchemaFile = \SeanMorris\Ids\Disk\File::open(
			$this->package->packageDir()
			. 'test/data/testColumnRemoveSchema.json'
		);

		$testSchemaFile->copy(
			$this->package->globalDir() . 'schema.json'
		);

		$this->package->applySchema(TRUE);

		$db = \SeanMorris\Ids\Database::get('main');

		$sth = $db->prepare('SHOW COLUMNS FROM Foozle WHERE field LIKE ?');
		$sth->execute(['value']);

		$this->assertFalse(
			$sth->fetchObject()
			, 'Failed to delete column "value" on table "Foozle"'
		);
	}

	public function testDirectories()
	{
		$packageDir = $this->package->packageDir();
		$localDir = $this->package->localDir();
		$globalDir = $this->package->globalDir();
		$assetDir = $this->package->assetDir();

		$this->assertTrue(
			$packageDir->has( $this->package->localDir() )
			, 'Subdirectory detection failed for local directory'
		);

		$this->assertTrue(
			$packageDir->has( $this->package->globalDir() )
			, 'Subdirectory detection failed for global directory'
		);

		$this->assertTrue(
			$packageDir->has( $this->package->assetDir() )
			, 'Subdirectory detection failed for asset directory'
		);
	}
}
