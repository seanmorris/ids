<?php
namespace SeanMorris\Ids\Disk;
class Directory extends File
{
	protected
		$eod = NULL;

	public function __construct($fileName, $originalName = NULL)
	{
		parent::__construct($fileName, $originalName);

		if(substr($this->name, -1, 1) !== '/')
		{
			$this->name .= '/';
		}

		if(substr($this->originalName, -1, 1) !== '/')
		{
			$this->originalName .= '/';
		}
	}

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

	public function create($name = NULL, $permissions = 0777, $recursive = FALSE)
	{
		$name = (string)$name;
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

	public function file($name)
	{
		return new \SeanMorris\Ids\Disk\File(
			$this->name . $name
		);
	}

	public function dir($name)
	{
		return new \SeanMorris\Ids\Disk\Directory(
			$this->name . $name
		);
	}

	public function delete()
	{
		rmdir($this->name);
	}

	public function has($sub)
	{
		if(!is_object($sub))
		{
			$sub = $this->file($sub);

			if(!$sub->check())
			{
				return FALSE;
			}
		};

		if(substr($sub->name, 0, strlen($this->name)) == $this->name)
		{
			if(is_dir($sub->name))
			{
				return new Directory($sub->name . '/');
			}
			return new File($sub->name);
		}

		return FALSE;
	}

	public function isWritable()
	{
		
	}

	public function write(){}
	public function slurp(){}
	public function copy($newFileName){}
}
