<?php
namespace SeanMorris\Ids\Test;
class ModelTest extends \UnitTestCase
{
	protected
		$modelIds = []
		, $modelClasses = [
			'\SeanMorris\Ids\Test\Model\Foozle'
			, '\SeanMorris\Ids\Test\Model\Foobar'

		];

	public function setUp()
	{
		$this->database = \SeanMorris\Ids\Database::get('main');
		$this->package  = \SeanMorris\Ids\Package::get('SeanMorris\Ids');

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

		$testSchemaFile = new \SeanMorris\Ids\Disk\File(
			$this->package->packageDir()
			. 'test/data/testModelSchema.json'
		);

		$testSchemaFile->copy(
			$this->package->globalDir() . 'schema.json'
		);

		$this->package->applySchema(TRUE);
	}

	public function tearDown()
	{
		$testSchemaFile = new \SeanMorris\Ids\Disk\File(
			$this->package->globalDir() . '_schema.json'
		);

		$testSchemaFile->copy(
			$this->package->globalDir() . 'schema.json'
		);
	}

	public function testCreate()
	{
		foreach($this->modelClasses as $modelClass)
		{
			$createModel = new $modelClass;
			$createModel->consume(['value' => '42']);
			$createModel->create();

			$this->assertTrue(
				$createModel->id
				, sprintf(
					'Model of type %s failed to save.'
					, $modelClass
				)
			);

			$this->modelIds[$modelClass] = $createModel->id;
		}
	}

	public function testLoad()
	{
		foreach($this->modelClasses as $modelClass)
		{
			if(!$model = $modelClass::loadOne())
			{
				$models = $modelClass::fill(10, function($index, $instance) {
					$instance->consume(['value' => $index]);

					$instance->save();

					return $instance;
				});

				$modelClass::clearCache();
			}

			$modelLoader = $modelClass::load($this->modelIds[$modelClass]);

			$loadedModels = $modelLoader();

			$this->assertTrue(
				count($loadedModels) > 0
				, sprintf(
					'No models loaded for %s::loadOne.'
					, $modelClass
				)
			);

			$loadModel = array_shift($loadedModels);

			$this->assertIsa(
				$loadModel
				, $modelClass
				, sprintf(
					'Model failed to load for %s::load.'
					, $modelClass
				)
			);

			$loadOneModel = $modelClass::loadOne();

			$this->assertIsa(
				$loadOneModel
				, $modelClass
				, sprintf(
					'Model failed to load for %s::loadOne.'
					, $modelClass
				)
			);

			$modelGenerator = $modelClass::generate();

			$this->assertIsa(
				$modelGenerator
				, '\Closure'
				, 'Generate did not return callback.'
			);

			foreach($modelGenerator() as $model)
			{
				$this->assertIsa(
					$model
					, $modelClass
					, sprintf(
						'Model failed to load for %s::loadOne.'
						, $modelClass
					)
				);
			}
		}
	}

	public function testUpdate()
	{
		foreach($this->modelClasses as $modelClass)
		{
			if(!$model = $modelClass::loadOne())
			{
				$models = $modelClass::fill(10, function($index, $instance) {
					$instance->consume(['value' => $index]);

					$instance->save();

					return $instance;
				});

				$modelClass::clearCache();

				$model = $modelClass::loadOne();
			}

			$newValue = 42;

			$model->consume(['value' => $newValue]);
			$model->save();

			$this->assertEqual(
				$model->value
				, $newValue
				, 'Model update failed.'
			);
		}
	}

	public function testDelete()
	{
		foreach($this->modelClasses as $modelClass)
		{
			if(!$model = $modelClass::loadOne())
			{
				$models = $modelClass::fill(10, function($index, $instance) {
					$instance->consume(['value' => $index]);

					$instance->save();

					return $instance;
				});

				$modelClass::clearCache();

				$model = $modelClass::loadOne();
			}

			$model = $modelClass::loadOne();

			$model->delete();

			$modelClass::clearCache();

			$model = $modelClass::loadOneById($model);

			$this->assertFalse(
				$model
				, 'Model delete failed.'
			);
		}
	}

	public function octopusTest()
	{
		for($i = 0; $i < 10; $i++)
		{
			$octopus = new \SeanMorris\Ids\Test\Model\Octopus;

			var_dump($octopus);
		}
	}
}
