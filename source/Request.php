<?php
namespace SeanMorris\Ids;
class Request
{
	protected
		$uri
		, $path
		, $get
		, $post
		, $files
		, $context
	;

	public function __construct($vars = [])
	{
		$this->consume($vars);

		if(!$this->uri)
		{
			$this->uri = $this->uri();
		}

		if(!$this->get)
		{
			$this->get = $_GET;
		}

		if(!$this->post)
		{
			$this->post = $_POST;
		}

		if(!$this->files)
		{
			$this->files = $_FILES;
		}

		if(!$this->context)
		{
			$this->context = [];
		}
	}

	public function copy($vars = [])
	{
		$new = clone $this;
		$new->uri = null;
		$new->consume($vars);

		return $new;
	}

	protected function consume($vars)
	{
		foreach($this as $name => $var)
		{
			if(isset($vars[$name]))
			{
				$this->$name = $vars[$name];
			}
		}
	}

	public function uri()
	{
		if($this->uri === NULL)
		{
			if(!$this->path)
			{
				$this->uri = $_SERVER['REQUEST_URI'];
				$this->path();
			}

			$this->uri = $this->path->pathString();	
		}

		return $this->uri;
	}

	public function path()
	{
		if(!$this->path)
		{
			$url = parse_url($this->uri());
			$args = array_filter(explode('/', $url['path']));
			$this->path = new Path(...$args);
		}
		
		return $this->path;
	}

	public function params()
	{
		return static::files() + static::post() + static::get();
	} 

	public function get()
	{
		return $this->get;
	}

	public function post()
	{
		return $this->post;
	}

	public function files()
	{
		$files = [];

		foreach($_FILES as $fieldName => $fileDef)
		{
			if(!$fileDef['tmp_name'])
			{
				continue;
			}

			$file = new \SeanMorris\Ids\Storage\Disk\File($fileDef['tmp_name'], $fileDef['name']);

			$files[$fieldName] = $file;

		}

		return $files;
	}

	public function &context()
	{
		return $this->context;
	}

	public function contextGet($name)
	{
		if(isset($this->context[$name]))
		{
			return $this->context[$name];
		}
	}

	public function contextSet($name, $value)
	{
		$this->context[$name] = $value;
	}
}