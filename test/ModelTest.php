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
			$modelLoader = $modelClass::loadById($this->modelIds[$modelClass]);
			$loadedModels = $modelLoader();

			$this->assertTrue(
				count($loadedModels) > 0
				, sprintf(
					'No models loaded for %s::loadOneById.'
					, $modelClass
				)
			);

			$loadModel = array_shift($loadedModels);

			$this->assertIsa(
				$loadModel
				, $modelClass
				, 'Model failed to load.'
			);

			$loadOneModel = $modelClass::loadOneById($this->modelIds[$modelClass]);

			$this->assertIsa(
				$loadOneModel
				, $modelClass
				, sprintf(
					'No models loaded for %s::loadOneById.'
					, $modelClass
				)
			);

			$modelGenerator = $modelClass::generateOneById($this->modelIds[$modelClass]);

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
						'No models loaded for %s::loadOneById.'
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
			$newValue = 43;
			$modelClass = '\SeanMorris\Ids\Test\Model\Foozle';
		
			$model = $modelClass::loadOneById($this->modelIds[$modelClass]);

			$model->consume(['value' => $newValue]);
			$model->save();

			$model = $modelClass::loadOneById($this->modelIds[$modelClass]);

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
			$model = $modelClass::loadOneById($this->modelIds[$modelClass]);

			$model->delete();

			$modelClass::clearCache();

			$model = $modelClass::loadOneById($this->modelIds[$modelClass]);

			$this->assertFalse(
				$model
				, 'Model delete failed.'
			);
		}
	}
}
