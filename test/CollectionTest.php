<?php
namespace SeanMorris\Ids\Test;

use \stdClass, \Datetime;
use \___\StdCollection, \___\DatetimeCollection, \UnitTestCase;

use \SeanMorris\Ids\Collection, \SeanMorris\Ids\WrappedMethod;

Collection::of(stdClass::class, StdCollection::class);
Collection::of(Datetime::class, DatetimeCollection::class);

class CollectionTest extends UnitTestCase
{
	public function testAcceptReject()
	{
		$stdCollection  = new StdCollection;
		$dateCollection = new DatetimeCollection;

		$std  = (object) [ 'x' => 'y' ];
		$date = new \Datetime;

		$this->assertFalse(
			$stdCollection->has($std)
			, 'StdCollection incorrectly claims to contain object.'
		);

		$stdCollection->add($std);

		$this->assertTrue(
			$stdCollection->has($std)
			, 'StdCollection incorrectly claims to not contain object.'
		);


		$this->assertFalse(
			$dateCollection->has($date)
			, 'DatetimeCollection incorrectly claims to contain object.'
		);

		$dateCollection->add($date);

		$this->assertTrue(
			$dateCollection->has($date)
			, 'DatetimeCollection incorrectly claims to not contain object.'
		);

		try {
			$stdCollection->add($date);
			$this->fail('StdCollection did not throw Exception when provided with Datetime object.');
		}
		catch (\UnexpectedValueException $exception)
		{
			$this->pass();
		}
		catch (\Exception $exception)
		{
			$this->fail('StdCollection did not throw UnexpectedValueException when provided with Datetime object.');
		}

		try {
			$stdCollection->add('fail!');
			$this->fail('StdCollection did not throw Exception when provided with scalar value.');
		}
		catch (\UnexpectedValueException $exception)
		{
			$this->pass();
		}
		catch (\Exception $exception)
		{
			$this->fail('StdCollection did not throw UnexpectedValueException when provided with scalar value.');
		}

		try {
			$dateCollection->add($std);
			$this->fail('DatetimeCollection did not throw Exception when provided with stdClass object.');
		}
		catch (\UnexpectedValueException $exception)
		{
			$this->pass();
		}
		catch (\Exception $exception)
		{
			$this->fail('DatetimeCollection did not throw UnexpectedValueException when provided with stdClass object.');
		}

		try {
			$dateCollection->add('fail!');
			$this->fail('DatetimeCollection did not throw Exception when provided with scalar value.');
		}
		catch (\UnexpectedValueException $exception)
		{
			$this->pass();
		}
		catch (\Exception $exception)
		{
			$this->fail('DatetimeCollection did not throw UnexpectedValueException when provided with scalar value.');
		}
	}

	public function testCount()
	{
		$numbers = new StdCollection;

		foreach(range(0,9) as $i)
		{
			$numbers->add((object)(['i' => $i]));

			$this->assertEqual(
				$numbers->count()
				, $i + 1
				, sprintf(
					'StdCollection did not return correct count after adding %d items.'
					, $i + 1
				)
			);
		}
	}

	public function testMapSimple()
	{
		$numbers = new StdCollection;

		foreach(range(0,3) as $i)
		{
			$numbers->add((object)(['i' => $i]));
		}

		$doubled = $numbers->map(function($item, $rank) {
			$result = clone $item;

			$result->i *= 2;

			return $result;
		});

		$this->assertEqual(
			$numbers->count()
			, $doubled->count()
			, 'Simple map operation returned incorrect number of results.'
		);

		foreach($doubled as $original => $item)
		{
			$this->assertTrue(
				$item->i === $original->i * 2
				, 'Simple map operation returned incorrect results.'
			);
		}
	}

	public function testMapNonNullable()
	{
		$numbers = new StdCollection;

		foreach(range(0,3) as $k => $v)
		{
			$numbers->add((object)(['i' => $v]));
		}

		$doubled = $numbers->map(function($item, $rank) : stdClass {
			$result = clone $item;

			$result->i *= 2;

			return $result;
		});

		$this->assertEqual(
			$numbers->count()
			, $doubled->count()
			, 'Non-nullable map operation returned incorrect number of results.'
		);

		foreach($doubled as $original => $item)
		{
			$this->assertTrue(
				$item->i === $original->i * 2
				, 'Non-nullable map operation returned incorrect results.'
			);
		}
	}

	public function testMapNullable()
	{
		$numbers = new StdCollection;

		foreach(range(0,3) as $i)
		{
			$numbers->add((object)(['i' => $i]));
		}

		$doubled = $numbers->map(function($item, $rank) : ?stdClass {
			if($item->i > 1)
			{
				return NULL;
			}

			$result = clone $item;
			$result->i *= 2;

			return $result;
		});

		$count = 0;

		foreach($doubled as $original => $item)
		{
			$this->assertTrue(
				$item->i === $original->i * 2
				, 'Nullable map operation returned incorrect results.'
			);

			$count++;
		}

		$this->assertEqual(
			$count, 2
			, 'Nullable map operation returned incorrect number of results.'
		);
	}

	public function testMapToInt()
	{
		$numbers = new StdCollection;

		foreach(range(0,3) as $i)
		{
			$numbers->add((object)(['i' => $i]));
		}

		$doubled = $numbers->map(function($item, $rank) : int {
			return $item->i * 2;
		});

		$this->assertEqual(
			$numbers->count()
			, count($doubled)
			, 'Non-nullable map operation returned incorrect number of results.'
		);

		foreach($doubled as $original => $item)
		{
			$this->assertTrue(
				$original->i * 2 === $item
				, 'Map to int operation returned incorrect results.'
			);
		}
	}

	public function testMapIntNullable()
	{
		$numbers = new StdCollection;

		foreach(range(0,3) as $i)
		{
			$numbers->add((object)(['i' => $i]));
		}

		$doubled = $numbers->map(function($item, $rank) : ?int {

			if($item->i > 1)
			{
				return NULL;
			}
			return $item->i * 2;
		});

		$count = 0;

		foreach($doubled as $original => $item)
		{
			$this->assertTrue(
				$original->i * 2 === $item
				, 'Nullable map to int operation returned incorrect results.'
			);

			$count++;
		}

		$this->assertEqual(
			$count, 2
			, 'Nullable map operation returned incorrect number of results.'
		);
	}

	public function testFilter()
	{
		$numbers = new StdCollection;

		foreach(range(0,99) as $i)
		{
			$numbers->add((object)(['i' => $i]));
		}

		$tens = $numbers->filter(function($n){
			return $n->i % 10 === 0;
		});

		$count = 0;

		foreach($tens as $ten)
		{
			$this->assertTrue(
				$ten->i % 10 === 0
				, 'Filter operation returned incorrect results.'
			);

			$count++;
		}

		$this->assertEqual(
			$count, 10
			, 'Filter operation returned incorrect number of results.'
		);
	}

	public function testReduce()
	{
		$numbers = new StdCollection;

		foreach(array_fill(0,10,10) as $i)
		{
			$numbers->add((object)(['i' => $i]));
		}

		$sum = $numbers->reduce(function($a, $b){

			$c = $b->i;

			if(isset($a))
			{
				$c += $a;
			}

			return $c;

		});

		$this->assertEqual(
			$sum, 100
			, 'Reduce operation returned incorrect result.'
		);
	}

	public function testLookup()
	{
		$TripleLetters = StdCollection::inject([
			'lookup' => WrappedMethod::wrap(function($key){
				return (object) [ 'letters' => $key . $key . $key];
			})
		]);

		$tripleLetters = new $TripleLetters;

		$a = $TripleLetters::lookup('A');
		$b = $TripleLetters::lookup('B');
		$c = $TripleLetters::lookup('C');
		$d = $TripleLetters::lookup('D');

		$tripleLetters->add($a);
		$tripleLetters->add($a, $b, $c);

		$aa = $tripleLetters['A'];
		$bb = $tripleLetters['B'];
		$cc = $tripleLetters['C'];
		$dd = $tripleLetters['D'];

		$aaa = $tripleLetters['A'];
		$bbb = $tripleLetters['B'];
		$ccc = $tripleLetters['C'];
		$ddd = $tripleLetters['D'];

		$this->assertTrue(
			$a->letters === 'AAA'
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$b->letters === 'BBB'
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$c->letters === 'CCC'
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$d->letters === 'DDD'
			, 'Lookup operation returned incorrect result.'
		);

		$this->assertTrue(
			$a === $aa
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$b === $bb
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$c === $cc
			, 'Lookup operation returned incorrect result.'
		);

		$this->assertTrue(
			$aa === $aaa
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$bb === $bbb
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$cc === $ccc
			, 'Lookup operation returned incorrect result.'
		);

		$this->assertTrue(
			$dd === FALSE
			, 'Lookup operation returned incorrect result.'
		);
		$this->assertTrue(
			$ddd === FALSE
			, 'Lookup operation returned incorrect result.'
		);

		$i = 0;

		foreach($tripleLetters as $key => $value)
		{
			if($i++ > 10)
			{
				break;
			}
		}

		$this->assertTrue(
			$i === 3
			, 'Populated lookup-collection returned incorrect number of results.'
		);
	}

	public function testTag()
	{
	}
}
