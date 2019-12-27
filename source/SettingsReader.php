<?php
namespace SeanMorris\Ids;
class SettingsReader implements \Iterator, \ArrayAccess
{
	protected $root, $keys, $names = [], $i = 0, $isArray = false;

	public function __construct($root, $names)
	{
		$names = array_values(array_unique(array_map(function($name) {
			$parts = explode('_', $name);

			return $parts[1] ?? $name;

		}, $names)));

		$keys = array_flip($names);

		[$this->root, $this->names, $this->keys] = [$root, $names, $keys];

		$this->isArray = $root[ strlen($root) -1 ] === '_';

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

	public function __isset($name)
	{
		return isset($this->keys[strtoupper($name)]);
	}

	public function offsetExists($name)
	{
		return isset($this->keys[strtoupper($name)]);
	}

	public function offsetGet($name)
	{
		if(!isset($this->keys[strtoupper($name)]))
		{
			return NULL;
		}

		return Settings::read($this->root, strtoupper($name));
	}

	public function offsetSet($name, $value)
	{
		if(array_key_exists(strtoupper($name), $this->keys))
		{
			return;
		}

		$this->keys[strtoupper($name)] = count($this->names);

		$this->names[] = strtoupper($name);
	}

	public function offsetUnset($name)
	{
		if(!array_key_exists(strtoupper($name), $this->keys))
		{
			return;
		}

		unset($this->names[$this->keys[strtoupper($name)]]);

		$this->keys = array_flip($this->names);
	}

	public function dumpStruct()
	{
		$r = [];

		foreach($this as $k=>$v)
		{
			if(method_exists($v, __FUNCTION__))
			{
				$r[$k] = $v->{__FUNCTION__}();
				continue;
			}

			$r[$k] = $v;
		}

		return $r;
	}
}
