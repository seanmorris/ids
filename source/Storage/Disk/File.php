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

	public function eof()
	{
		if(!$this->check())
		{
			return TRUE;
		}

		return $this->readHandle
			&& feof($this->readHandle);
	}

	public function read()
	{
		list($bytes, $reset) = func_get_args() + [1024, FALSE];

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

		if($bytes)
		{
			return fread($this->readHandle, $bytes);
		}
	}

	public function write()
	{
		list($data, $append) = func_get_args() + [NULL, TRUE];

		if(!$this->writeHandle || !$append)
		{
			$this->writeHandle = fopen($this->name, $append ? 'a' : 'w');
		}

		return fwrite($this->writeHandle, $data);
	}

	public function delete()
	{
		unlink($this->name);
	}

	public function slurp()
	{
		return file_get_contents($this->name);
	}

	public function copy($newFileName)
	{
		$newFile = new static($newFileName, $this->originalName);
		$newFile->write(NULL, FALSE);

		while($chunk = $this->read(1024))
		{
			$newFile->write($chunk);
		}

		$newFile->check();

		return $newFile;
	}

	public function __toString()
	{
		return $this->name;
	}
}