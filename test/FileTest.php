<?php
namespace SeanMorris\Ids\Test;
class FileTest extends \UnitTestCase
{
	protected $package, $globalDir, $localDir, $testData, $testFilename, $testFilepath;

	public function setUp()
	{
		$this->package = \SeanMorris\Ids\Package::get('SeanMorris\Ids');
		$this->globalDir = $this->package->globalDir();
		$this->localDir = $this->package->localDir();
		$this->testData = "abcdefghijklmnop";
		$this->testFilename = 'testFile.txt';
		$this->testFilepath = $this->localDir . $this->testFilename;

	}

	public function testWrite()
	{
		$appendData = "12345678";

		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);

		if($file->check())
		{
			$file->delete();
		}

		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);

		$file->write($this->testData, FALSE);

		$this->assertEqual(
			$file->slurp()
			, $this->testData
			, 'Write failed for ' . $this->testFilepath
		);

		$file->write($appendData);
		$this->assertEqual(
			$file->slurp()
			, $this->testData . $appendData
			, 'Append failed for ' . $this->testFilepath
		);

		$file->write($this->testData, FALSE);
		$this->assertEqual(
			$file->slurp()
			, $this->testData
			, 'Truncate failed for ' . $this->testFilepath
		);
	}

	public function testRead()
	{
		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);
		$data = str_split(file_get_contents($this->testFilepath));

		while(!$file->eof())
		{
			$byte = array_shift($data);
			$testByte = $file->read(1);

			$this->assertEqual($byte, $testByte, 'Read failed for ' . $this->testFilepath);
		}

		$data = str_split(file_get_contents($this->testFilepath));

		$testByte = $file->read(0, TRUE);

		while(!$file->eof())
		{
			$byte = array_shift($data);
			$testByte = $file->read(1);

			$this->assertEqual($byte, $testByte, 'Re-read failed for ' . $this->testFilepath);
		}
	}

	public function testBasename()
	{
		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);

		$this->assertEqual(
			$file->basename()
			, $this->testFilename
			, 'Basename failed for ' . $this->testFilepath
		);
	}

	public function testDelete()
	{
		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);

		$file->delete();

		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);

		$this->assertFalse($file->check(), 'Delete failed for ' . $this->testFilepath);

		$this->assertFalse(file_exists($this->testFilepath), 'Delete failed for ' . $this->testFilepath);
	}

	public function testAge()
	{
		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);

		if($file->check())
		{
			$file->delete();
		}

		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);

		$file->write();

		sleep(5);

		$this->assertTrue(
			$file->age() >= 5
			, 'Age failed for ' . $this->testFilepath
		);
	}

	public function testSubtract()
	{
		$file = \SeanMorris\Ids\Disk\File::open($this->testFilepath);
		$dir  = dirname($this->testFilepath);
	}
}
