<?php
namespace SeanMorris\Ids\Test;
class PathTest extends \UnitTestCase
{
	protected
		$elements = ['a','b','c']
	;

	public function testPathConsume()
	{
		$path = new \SeanMorris\Ids\Path(...$this->elements);
		$count = 0;

		while($node = $path->consumeNode())
		{
			$spentPath = $path->getSpentPath();

			$this->assertTrue(
				$spentPath->done()
				, 'Spent Paths not marked as done.'
			);
			
			$this->assertEqual(
				$node, $this->elements[$count]
				, 'Incorrect node returned.'
			);

			$count++;
		}

		$this->assertEqual(
			$count
			, count($this->elements)
			, 'Number of nodes consumed is incorrect.'
		);

		$this->assertTrue(
			$path->done(),
			'Path is not yet done.'
		);

		$count--;

		while($path->unconsumeNode())
		{
			$this->assertEqual(
				$path->getNode(), $this->elements[$count],
				'Incorrect node returned.'
			);

			$count--;
		}

		$this->assertFalse(
			$path->done(),
			'Path should not be done.'
		);

		$path->reset();

		$count = 0;

		while($node = $path->consumeNode())
		{
			$spentPath = $path->getSpentPath();

			$this->assertTrue(
				$spentPath->done()
				, 'Spent Paths not marked as done.'
			);
			
			$this->assertEqual(
				$node, $this->elements[$count]
				, 'Incorrect node returned.'
			);

			$count++;
		}
	}

	public function testAppendPath()
	{
		$append = ['d', 'e', 'f'];
		$path = new \SeanMorris\Ids\Path(...$this->elements);
		
		while($node = $path->consumeNode())
		{
			// Consume the entire path
		}
		
		$appendedPath = $path->append(...$append);
		$count = 0;

		while($node = $appendedPath->consumeNode())
		{
			$spentPath = $appendedPath->getSpentPath();

			$this->assertTrue(
				$spentPath->done()
				, 'Spent Paths not marked as done.'
			);
			
			$this->assertEqual(
				$node, $append[$count]
				, 'Incorrect node returned.'
			);

			$count++;
		}
	}

	public function testPathAliases()
	{
		$path = new \SeanMorris\Ids\Path(...$this->elements);
		$path->consumeNode();
		$path->setAlias('test');
		// var_dump($path, $path->getAliasedPath());
	}
}