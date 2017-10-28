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
		return $this->get ?? [];
	}

	public function post()
	{
		return $this->post ?? [];
	}

	public function method()
	{
		return $_SERVER['REQUEST_METHOD'] ?? NULL;
	}

	public function files()
	{
		$files = [];

		$organizedFiles = [];

		foreach($_FILES as $fieldName => $fileDef)
		{
			if(!$fileDef['tmp_name'])
			{
				continue;
			}

			if(!is_scalar($fileDef['tmp_name']))
			{
				$elementNames = ['name', 'tmp_name', 'error', 'size', 'type'];

				foreach($elementNames as $elementName)
				{
					array_walk_recursive($fileDef[$elementName], function(&$element) use($elementName){
						$element = [$elementName => $element];
					});					
				}

				$newDef = [];

				foreach($fileDef as $oldKey => $frags)
				{
					$newDef = array_replace_recursive($newDef, $frags);
				}

				$organizedFiles[$fieldName] = $newDef;
			}
			else
			{
				$organizedFiles[$fieldName] = $fileDef;
			}
		}

		$findFiles = function(&$f, $function) use(&$findFiles)
		{
			foreach($f as &$ff)
			{
				if(isset($ff['tmp_name']))
				{
					$ff = $function($ff);
				}
				else
				{
					$ff = $findFiles($ff, $function);
				}
			}

			return $f;
		};

		$organizedFiles = $findFiles($organizedFiles, function($file){
			return new \SeanMorris\Ids\Disk\File(
				$file['tmp_name']
				, $file['name']
			);
		});

		return $organizedFiles;
	}

	public function switches(...$args)
	{
		if(!$args)
		{
			return $this->switches ?? [];
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