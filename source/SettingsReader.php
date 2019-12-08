<?php
namespace SeanMorris\Ids;
class SettingsReader implements \Iterator, \ArrayAccess
{
	protected $root, $names = [], $i = 0;

	public function __construct($root, $names)
	{
		$names = array_values(array_unique(array_map(function($name) {
			$parts = explode('_', $name);

			return $parts[1] ?? $name;

		}, $names)));

		$keys = array_flip($names);

		[$this->root, $this->names, $this->keys] = [$root, $names, $keys];
	}

	public function __get($name)
	{
		return Settings::read($this->root, strtoupper($name));
	}

	public function current()
	{
		return Settings::read($this->root, $this->names[ $this->i ]);
	}

	public function key()
	{
		return strtolower($this->names[ $this->i ]);
	}

	public function next()
	{
		return $this->i +=1;
	}

	public function rewind()
	{
		return $this->i = 0;
	}

	public function valid()
	{
		return $this->i >= 0 && $this->i < count($this->names);
	}

	public function offsetExists($name)
	{
		return isset($this->$keys[$name]);
	}

	public function offsetGet($name)
	{
		if(!isset($this->names[ $name ]))
		{
			return NULL;
		}

		return Settings::read($this->root, $this->names[ $name ]);
	}

	public function offsetSet($name, $value)
	{
		$this->keys[$name] = count($this->names);
		$this->names[]     = $name;
	}

	public function offsetUnset($name)
	{
		unset($this->names[$this->keys[$name]]);

		$this->keys = array_flip($this->names);
	}
}
