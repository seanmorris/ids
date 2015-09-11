<?php
namespace SeanMorris\Ids\Storage\Disk;
class File
{
	protected
		$name
		, $originalName
		, $exists
		, $readHandle
		, $writeHandle
	;

	public function __construct($fileName, $originalName = NULL)
	{
		$this->name = $fileName;
		$this->originalName = $originalName;
		
		if($this->check())
		{
			// $this->content = $this->slurp();
		}
	}

	public function check()
	{
		return $this->exists = file_exists($this->name);
	}

	public function name()
	{
		return $this->name;
	}

	public function originalName()
	{
		return $this->originalName;
	}

	public function read($bytes = 1024, $reset = FALSE)
	{
		if(!$this->exists)
		{
			if(!$this->check())
			{
				return;
			}
		}

		if($reset)
		{
			$this->readHandle = fopen($this->name, 'r');
		}

		if(!$this->readHandle || $reset)
		{
			$this->readHandle = fopen($this->name, 'r');
		}

		return fread($this->readHandle, $bytes);
	}

	public function write($data, $append = TRUE)
	{
		if(!$this->writeHandle)
		{
			$this->writeHandle = fopen($this->name, $append ? 'a' : 'w');
		}

		return fwrite($this->writeHandle, $data);
	}

	public function slurp()
	{
		return file_get_contents($this->name);
	}

	public function copy($newFileName)
	{
		$newFile = new static($newFileName);
		$newFile->write(NULL, FALSE);

		while($chunk = $this->read(1024))
		{
			$newFile->write($chunk);
		}

		$newFile->check();

		return $newFile;
	}
}