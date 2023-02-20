<?php
namespace SeanMorris\Ids\Test;
class DirectoryTest extends \UnitTestCase
{
	protected $package, $globalDir, $localDir, $testFilename;

	public function setUp()
	{
		$this->package = \SeanMorris\Ids\Package::get('SeanMorris\Ids');
		$this->globalDir = $this->package->globalDir();
		$this->localDir = $this->package->localDir();
		$this->testFilename = $this->localDir;// . 'testDir/';
	}

	public function testCreate()
	{
		$directory = new \SeanMorris\Ids\Disk\Directory($this->testFilename);

		$testName = 'testing';
		$testDir = $directory->create($testName);

		$this->assertIsA(
			$testDir
			, '\SeanMorris\Ids\Disk\Directory'
			, sprintf(
				"Create directory failed:\n%s/%s/"
				, $this->testFilename
				, $testName
			)
		);

		/*
		$testName2 = $this->testFilename . 'testing2';
		$testDirStatic = $directory::create($testName2);

		$this->assertIsA(
			$testDirStatic
			, '\SeanMorris\Ids\Disk\Directory'
			, sprintf(
				"Static create directory failed:\n%s"
				, $this->testFilename
				, $testName2
			)
		);
		*/
	}

	public function testRead()
	{
		$testInvoluter = function($directory) use(&$testInvoluter)
		{
			while($file = $directory->read())
			{
				$this->assertIsA(
					$file
					, '\SeanMorris\Ids\Disk\File'
					, sprintf(
						"File object not found:\n%s"
						, print_r($file, 1)
					)
				);

				if($file instanceof \SeanMorris\Ids\Disk\Directory)
				{
					$testInvoluter($file);
				}
			}
		};

		$directory = new \SeanMorris\Ids\Disk\Directory($this->testFilename);

		$testInvoluter($directory);
	}

	public function testDelete()
	{
		$directory = new \SeanMorris\Ids\Disk\Directory($this->testFilename);

		$testName = 'testing';
		$testDir = $directory->create($testName);

		$testDir->delete();

		$this->assertFalse(
			$testDir->check()
			, 'Delete failed for '
				. $this->testFilename
				. $testName
		);

		/*
		$testName2 = $this->testFilename . 'testing2';
		$testDirStatic = $directory::create($testName2);

		$testDirStatic->delete();

		$this->assertFalse($testDirStatic->check()
			, 'Delete failed for '
				. $this->testFilename
				. $testName2
		);
		*/
	}
}
