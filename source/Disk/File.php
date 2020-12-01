<?php
namespace SeanMorris\Ids\Disk;

use \BadMethodCallException;

class File
{
	protected static $open;

	protected
		$name
		, $realName
		, $originalName
		, $exists
		, $readHandle
		, $writeHandle
		, $closed = false
	;

	public static function open($filename)
	{
		return new static($filename);
	}

	public function close()
	{
		if(isset(static::$open[$this->name]))
		{
			unset(static::$open[$this->name]);
		}

		$this->closed = true;

		$this->readHandle  && fclose($this->readHandle);
		$this->writeHandle && fclose($this->writeHandle);
	}

	public function __construct($fileName, $originalName = NULL)
	{
		$this->name = $this->realName = $fileName;
		$this->originalName = $originalName ?? $this->name;
	}

	public function check()
	{
		$this->exists = file_exists($this->name);

		if($this->exists)
		{
			$this->realName = realpath($this->name);
		}

		return $this->exists;
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
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		if(!$this->check())
		{
			return TRUE;
		}

		return $this->readHandle
			&& feof($this->readHandle);
	}

	public function read()
	{
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

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
			$this->readHandle = fopen($this->realName, 'r');
		}

		if(!$this->readHandle || $reset)
		{
			$this->readHandle = fopen($this->realName, 'r');
		}

		if($bytes)
		{
			return fread($this->readHandle, $bytes);
		}
	}

	public function write()
	{
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		list($data, $append) = func_get_args() + [NULL, TRUE];

		if(!$this->writeHandle || !$append)
		{
			$this->writeHandle = fopen($this->realName, $append ? 'a' : 'w');
		}

		if($data instanceof static)
		{
			while($d = $data->read())
			{
				return fwrite($this->writeHandle, $d);
			}
		}
		else
		{
			return fwrite($this->writeHandle, $data);
		}
	}

	public function delete()
	{
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		unlink($this->realName);

		$this->close();
	}

	public function slurp()
	{
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		return file_get_contents($this->realName);
	}

	public function copy($newFileName)
	{
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		$newFile = new static($newFileName);
		$newFile->originalName = $this->name;
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
		return (string) $this->name;
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
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		if(!$this->check())
		{
			return PHP_INT_MAX;
		}

		return time() - filectime($this->realName);
	}

	public function handle()
	{
		if($this->closed)
		{
			throw new BadMethodCallException(sprintf(
			'Cannot call "%s" on CLOSED instace of "%s" (%s).'
				, __FUNCTION__
				, __CLASS__
				, get_called_class()
			));
		}

		$this->read(0,0);

		return $this->readHandle;
	}
}
