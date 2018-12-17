<?php
namespace SeanMorris\Ids\Test;
class FileTest extends \UnitTestCase
{
	public function setUp()
	{
		$this->package = \SeanMorris\Ids\Package::get('SeanMorris\Ids');
		$this->globalDir = $this->package->globalDir();
		$this->localDir = $this->package->localDir();
		$this->testData = "abcdefghijklmnop";
		$this->testFilename = $this->localDir . 'testFile.txt';
		
	}

	public function testWrite()
	{
		$appendData = "12345678";

		$file = new \SeanMorris\Ids\Disk\File($this->testFilename);

		$file->write($this->testData, FALSE);
		$this->assertEqual(
			$file->slurp()
			, $this->testData
			, 'Write failed for ' . $this->testFilename
		);

		$file->write($appendData);
		$this->assertEqual(
			$file->slurp()
			, $this->testData . $appendData
			, 'Append failed for ' . $this->testFilename
		);

		$file->write($this->testData, FALSE);
		$this->assertEqual(
			$file->slurp()
			, $this->testData
			, 'Truncate failed for ' . $this->testFilename
		);
	}

	public function testRead()
	{
		$file = new \SeanMorris\Ids\Disk\File($this->testFilename);
		$data = str_split(file_get_contents($this->testFilename));

		while(!$file->eof()) 
		{
			$byte = array_shift($data);
			$testByte = $file->read(1);

			$this->assertEqual($byte, $testByte, 'Read failed for ' . $this->testFilename);
		}

		$data = str_split(file_get_contents($this->testFilename));

		$testByte = $file->read(0, TRUE);

		while(!$file->eof()) 
		{
			$byte = array_shift($data);
			$testByte = $file->read(1);

			$this->assertEqual($byte, $testByte, 'Re-read failed for ' . $this->testFilename);
		}
	}

	public function testDelete()
	{
		$file = new \SeanMorris\Ids\Disk\File($this->testFilename);

		$file->delete();

		$this->assertFalse(file_exists($this->testFilename), 'Delete failed for ' . $this->testFilename);
	}
}