<?php
namespace SeanMorris\Ids;
class Request
{
	protected
		$uri
		, $method
		, $host
		, $scheme
		, $port
		, $path
		, $get
		, $post
		, $files
		, $handle
		, $headers
		, $context
		, $switches
		, $responseBuffer
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

		if(!$this->headers)
		{
			$this->headers = [];
		}

		if(!$this->responseBuffer)
		{
			$this->responseBuffer = fopen('php://output', 'w');
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
				return $_SERVER['REQUEST_URI'];
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

	public function get($key = NULL)
	{
		if($key)
		{
			return $this->get[$key] ?? NULL;
		}

		return $this->get ?? [];
	}

	public function post($key = NULL)
	{
		if($key)
		{
			return $this->post[$key] ?? NULL;
		}

		return $this->post ?? [];
	}

	public function fraw()
	{
		if(!$this->handle)
		{
			$this->handle = fopen('php://input', 'r');
		}

		return $this->handle;
	}

	public function getResponseBuffer()
	{
		return $this->responseBuffer;
	}

	public function fread($length)
	{
		$handle = $this->fraw();

		return fread($handle, $length);
	}

	public function fgets()
	{
		$handle = $this->fraw();

		return fgets($handle);
	}

	public function fslurp()
	{
		return file_get_contents('php://input');
	}

	public function read()
	{
		$headers     = $this->headers();
		$contentType = $this->headers('Content-Type');
		$handle      = $this->fraw();

		$contentTypeSplit = explode(';', $contentType);
		$contentType = $contentTypeSplit ? $contentTypeSplit[0] : '';

		switch($contentType)
		{
			case 'text/csv':
				$parser = new \SeanMorris\Ids\Api\Input\Csv($handle, $headers);
				break;

			case 'text/tsv':
				$parser = new \SeanMorris\Ids\Api\Input\Tsv($handle, $headers);
				break;

			case 'text/json':
				$parser = new \SeanMorris\Ids\Api\Input\Json($handle, $headers);
				break;

			case 'text/xml':
				$parser = new \SeanMorris\Ids\Api\Input\Xml($handle, $headers);
				break;

			case 'text/yaml':
				$parser = new \SeanMorris\Ids\Api\Input\Yaml($handle, $headers);
				break;

			// case 'multipart/form-data':
			// 	$parser = new \SeanMorris\Ids\Api\Input\FormData($handle, $headers);
			// 	break;

			case 'text/plain':
			default:
				$parser = new \SeanMorris\Ids\Api\Input\Plain($handle, $headers);
				break;
		}

		foreach($parser->pump() as $key => $input)
		{
			yield $key => $input;
		}
	}

	public function method()
	{
		if($this->method)
		{
			return $this->method;
		}

		return $this->method = $_SERVER['REQUEST_METHOD'] ?? NULL;
	}

	public function files($file = null)
	{
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

		if($organizedFiles[$file] ?? FALSE)
		{
			return $file === null ? $organizedFiles : $organizedFiles[$file];
		}

		return $organizedFiles;
	}

	public function headers($name = NULL)
	{
		if(!$this->headers)
		{
			$headers = getallheaders();

			foreach($headers as $_name => $value)
			{
				$this->headers[ucwords(strtolower($_name), '-')] = $value;
			}
		}

		return $name !== NULL
			? ($this->headers[$name] ?? NULL)
			: ($this->headers ?? []);
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
