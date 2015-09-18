<?php
namespace SeanMorris\Ids\Test;
class ModelTest extends \UnitTestCase
{
	public function testCreate()
	{
		$createModel = new \SeanMorris\Ids\Test\Model\Foozle;
		$createModel->consume(['value' => '42']);
		$createModel->create();

		$this->assertTrue(
			$createModel->id
			, 'Model failed to save.'
		);

		$this->modelId = $createModel->id;
	}

	public function testLoad()
	{
		$modelClass = '\SeanMorris\Ids\Test\Model\Foozle';
		$modelLoader = $modelClass::loadById($this->modelId);

		$loadedModels = $modelLoader();

		$this->assertTrue(
			count($loadedModels)>0
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

		$loadOneModel = $modelClass::loadOneById($this->modelId);

		$this->assertIsa(
			$loadOneModel
			, $modelClass
			, sprintf(
				'No models loaded for %s::loadOneById.'
				, $modelClass
			)
		);

		$modelGenerator = $modelClass::generateOneById($this->modelId);

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
