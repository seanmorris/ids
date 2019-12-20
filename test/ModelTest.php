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

		$tables = $this->package->tables();

		foreach($tables as $dbName => $tables)
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

		$this->package::setTables([
			'main' => [
				'Foobar', 'Foozle', 'IdsRelationship'
				, 'Octopus', 'Tentacle'
				, 'Grocery', 'GroceryList'
			]
		]);

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

		$this->package->applySchema(TRUE);
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
			$modelClass::clearCache();

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
				$modelClass::clearCache();

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
			$modelClass::clearCache();

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

	public function testOctopus()
	{
		\SeanMorris\Ids\Test\Model\Octopus::fill(5, function($index, $instance) {

		    $instance->consume([
		        'tentacleA' => new \SeanMorris\Ids\Test\Model\Tentacle
		        , 'tentacleB' => new \SeanMorris\Ids\Test\Model\Tentacle
		        , 'tentacleC' => new \SeanMorris\Ids\Test\Model\Tentacle
		        , 'tentacleD' => new \SeanMorris\Ids\Test\Model\Tentacle
		        , 'tentacleE' => new \SeanMorris\Ids\Test\Model\Tentacle
		        , 'tentacleF' => new \SeanMorris\Ids\Test\Model\Tentacle
		        , 'tentacleG' => new \SeanMorris\Ids\Test\Model\Tentacle
		        , 'tentacleH' => new \SeanMorris\Ids\Test\Model\Tentacle
		    ]);

		    $instance->save();

		    return $instance;
		});

		\SeanMorris\Ids\Test\Model\Octopus::clearCache();

		$testModulo = [1,2,3,4,5,6,7,0];

		\SeanMorris\Ids\Test\Model\Octopus::map(function($instance) use($testModulo){

			$tentacleId= [
				$instance->tentacleA->id
				, $instance->tentacleB->id
				, $instance->tentacleC->id
				, $instance->tentacleD->id
				, $instance->tentacleE->id
				, $instance->tentacleF->id
				, $instance->tentacleG->id
				, $instance->tentacleH->id
			];

			foreach($testModulo as $i => $testModulus)
			{
				$this->assertTrue(
					$testModulus === $tentacleId[$i]  % 8
					, 'Incorrect tentacle loaded.'
				);

				$this->assertTrue(
					$tentacleId[$i] > ($instance->id - 1) * 8
					, 'Incorrect tentacle loaded.'
				);

				$this->assertTrue(
					$tentacleId[$i] <= $instance->id * 8
					, 'Incorrect tentacle loaded.'
				);
			}
		});
	}

	public function testGroceries()
	{
		\SeanMorris\Ids\Test\Model\GroceryList::fill(10, function($index, $instance) {

			$instance->consume([
				'groceries' => \SeanMorris\Ids\Test\Model\Grocery::fill(10)
			]);

			$instance->save();

			return $instance;
		});

		\SeanMorris\Ids\Test\Model\Grocery::clearCache();
		\SeanMorris\Ids\Test\Model\GroceryList::clearCache();

		\SeanMorris\Ids\Test\Model\GroceryList::map(function($instance) {

			$groceries = $instance->getSubjects('groceries');

			foreach($groceries as $i => $grocery)
			{
				$this->assertTrue(
					$grocery->id > ($instance->id - 1 ) * 10
					, 'Incorrect grocery loaded.'
				);

				$this->assertTrue(
					$grocery->id <= $instance->id  * 10
					, 'Incorrect grocery loaded.'
				);
			}
		});
	}
}
