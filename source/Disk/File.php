<?php
namespace SeanMorris\Ids\Disk;
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

	public function parent()
	{
		return new Directory(dirname($this->name));
	}

	public function basename()
	{
		return basename($this->name);
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

		if($data instanceof static)
		{
			while($d = $data->read())
			{
				$return = fwrite($this->writeHandle, $d);
			}
		}
		else
		{
			$return = fwrite($this->writeHandle, $data);
		}
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
		return (string)$this->name;
	}

	public function subtract($dir)
	{
		if(is_string($dir))
		{
			$dir = new Directory($dir);
		}

		if($dir->name === substr($this->name, 0, strlen($dir->name)))
		{
			return substr($this->name, strlen($dir->name));
		}

		return FALSE;
	}

	public function age()
	{
		if(!$this->check())
		{
			return PHP_INT_MAX;
		}
		return time()-filectime($this->name);
	}
}
