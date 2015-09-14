<?php
namespace SeanMorris\Ids\Storage\Disk;
class Directory extends File
{
	protected
		$eod = NULL;

	public function eof()
	{
		return $this->eod;
	}

	public function read()
	{
		list($reset) = func_get_args() + [FALSE];

		if(!$this->readHandle || $reset)
		{
			$this->eod = FALSE;
			$this->readHandle = opendir($this->name);
		}

		$filename = readdir($this->readHandle);

		if($filename === FALSE)
		{
			$this->eod = true;
			return FALSE;
		}

		if(in_array($filename, ['.', '..']))
		{
			return $this->read();
		}		

		$filename = realpath($this->name . $filename);

		if(is_dir($filename))
		{
			$file = new Directory($filename . '/');
		}
		else
		{
			$file = new File($filename);
		}

		return $file;
	}

	public function create($name, $permissions = 0777, $recursive = FALSE)
	{
		$class = get_class();

		if(isset($this) && is_a($this, $class))
		{
			$name = $this->name() . $name;
		}

		if(!file_exists($name) && !mkdir($name, $permissions, $recursive))
		{
			return false;			
		}

		return new $class($name);
	}

	public function write()
	{
	}

	public function delete()
	{
		rmdir($this->name);
	}

	public function slurp()
	{
	}

	public function copy($newFileName)
	{
	}
}