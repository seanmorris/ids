<?php
namespace SeanMorris\Ids;
class SettingsReader implements \Iterator, \ArrayAccess
{
	protected $root, $keys, $names = [], $i = 0, $isArray = false;

	public function __construct($root, $names, $content = [])
	{
		$names = array_values(array_unique(array_map(function($name) {
			$parts = explode('_', $name);

			return $parts[1] ?? $name;

		}, $names)));

		$keys = array_flip($names);

		[$this->root, $this->names, $this->keys] = [$root, $names, $keys];

		if($length = strlen($root))
		{
			$this->isArray = $root[ $length - 1 ] === '_';
		}

		$this->content = $content;
	}

	public function __get($name)
	{
		$path   = explode('_', $this->root);
		$path[] = $name;

		return Settings::read(...$path);
	}

	#[\ReturnTypeWillChange]
	public function current()
	{
		$path   = explode('_', $this->root);
		$path[] = $this->names[ $this->i ];

		return Settings::read(...$path);
	}

	#[\ReturnTypeWillChange]
	public function key()
	{
		return strtolower($this->names[ $this->i ]);
	}

	#[\ReturnTypeWillChange]
	public function next()
	{
		return $this->i +=1;
	}

	#[\ReturnTypeWillChange]
	public function rewind()
	{
		return $this->i = 0;
	}

	#[\ReturnTypeWillChange]
	public function valid()
	{
		if($this->i >= 0 && $this->i < count($this->names))
		{
			return TRUE;
		}

		return FALSE;
	}

	public function __isset($name)
	{
		return isset($this->keys[strtoupper($name)]);
	}

	#[\ReturnTypeWillChange]
	public function offsetExists($name)
	{
		return isset($this->keys[$name]);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($name)
	{
		if(!isset($this->keys[$name]))
		{
			if(isset($this->content->$name))
			{
				if(!is_scalar($this->content->$name))
				{
					return new static(
						$this->root . '_' . $name
						, []
						, $this->content->$name
					);
				}

				return $this->content->$name;
			}
			return NULL;
		}

		$path   = explode('_', $this->root);
		$path[] = $name;

		return Settings::read(...$path);
	}

	#[\ReturnTypeWillChange]
	public function offsetSet($name, $value)
	{
		if(array_key_exists($name, $this->keys))
		{
			return;
		}

		$this->keys[$name] = count($this->names);

		$this->names[] = $name;
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset($name)
	{
		if(!array_key_exists($name, $this->keys))
		{
			return;
		}

		unset($this->names[$this->keys[$name]]);

		$this->keys = array_flip($this->names);
	}

	public function dumpStruct()
	{
		$r = [];

		foreach($this->content as $k=>$v)
		{
			if(is_object($v) && method_exists($v, __FUNCTION__))
			{
				$v = $v->{__FUNCTION__}();
			}

			if(is_object($v) || is_array($v))
			{
				$v = static::unwrap($v);
			}

			$r[$k] = $v;
		}

		foreach($this as $k=>$v)
		{
			if(is_object($v) && method_exists($v, __FUNCTION__))
			{
				$v = $v->{__FUNCTION__}();
			}

			if(is_object($v) || is_array($v))
			{
				$v = static::unwrap($v);
			}

			if(isset($r[$k]) && is_array($r[$k]))
			{
				$r[$k] = array_replace_recursive($r[$k], $v);
			}
			else
			{
				$r[$k] = $v;
			}

		}

		return $r;
	}

	protected static function unwrap($object)
	{
		$result = [];

		foreach($object as $k=>$v)
		{
			if(is_object($v) || is_array($v))
			{
				$v = static::unwrap($v);
			}

			$result[$k] = $v;
		}

		return $result;
	}
}
