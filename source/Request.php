<?php
namespace SeanMorris\Ids;
class Request
{
	protected
		$uri
		, $host
		, $scheme
		, $port
		, $path
		, $get
		, $post
		, $files
		, $context
		, $switches
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

		if(!$this->switches)
		{
			$this->switches = [];
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
			}
			$this->uri = $this->path()->pathString();
		}

		return $this->uri;
	}

	public function path()
	{
		if(!$this->path)
		{
			$url = parse_url($this->uri());
			Log::debug($url, $this, $_SERVER);
			$args = explode('/', $url['path']);
			$args && $args[0] || array_shift($args);
			$this->path = new Path(...$args);
		}
		
		return $this->path;
	}

	public function host()
	{
		if(!$this->host && isset($_SERVER['HTTP_HOST']))
		{
			$this->host = $_SERVER['HTTP_HOST'];
		}

		return $_SERVER['HTTP_HOST'];
	}

	public function scheme()
	{
		if(!$this->scheme)
		{
			$this->scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
				? 'https://'
				: 'http://'
			);
		}

		return $this->scheme;
	}

	public function params()
	{
		return $this->files()
			+ $this->post()
			+ $this->get()
			+ $this->switches();
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

			$file = new \SeanMorris\Ids\Disk\File(
				$fileDef['tmp_name']
				, $fileDef['name']
			);

			$files[$fieldName] = $file;

		}

		return $files;
	}

	public function switches(...$args)
	{
		if(!$args)
		{
			return $this->switches;
		}

		if(count($args) == 1)
		{
			return isset($this->switches[$args[0]])
				? $this->switches[$args[0]]
				: NULL;
		}

		while($name = array_shift($args))
		{
			if(!$args)
			{
				return $name;
			}
			else if(isset($this->switches[$name]))
			{
				return $this->switches[$name];
			}
		}
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