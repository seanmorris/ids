<?php
namespace SeanMorris\Ids;
trait Injectable
{
	protected $___instances  = [];

	protected static
		$___injections   = []
		, $___extensions = 0
		, $___propMasks  = []
		, $___classMatch = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/'
	;

	public function __get($property)
	{
		if(!isset(static::$___injections))
		{
			return;
		}

		if($this->___instances[$property] ?? FALSE)
		{
			return $this->___instances[$property];
		}

		if(isset(static::$___injections[$property]) && !($this->___instances[$property] ?? FALSE))
		{
			$instance = new static::$___injections[$property];

			$this->___instances[$property] = $instance;

			return $this->___instances[$property];
		}

		trigger_error(sprintf(
			'Undefined property: %s::$%s'
			, get_called_class()
			, $property
		), E_USER_NOTICE);
	}

	public static function inject(array $injections = [], $alias = '')
	{
		if($alias && !preg_match(static::$___classMatch, $alias))
		{
			return FALSE;
		}

		$injectSpace = \SeanMorris\Ids\Settings::read('injectSpace');

		$baseClass = get_called_class();
		$hashSpace = $injectSpace . '\\_\\HashedClasses';

		$baseAlias = $hashSpace . '\\_' . sha1($baseClass);
		$subHash   = sprintf('%s_%d', $baseAlias, static::$___extensions++);

		$longName = sprintf('%s\\%s', $hashSpace, $subHash);

		if(!(static::$___propMasks[$baseClass] ?? NULL))
		{
			$reflection = new \ReflectionClass($baseClass);
			$properties = $reflection->getProperties();

			foreach($properties as $property)
			{
				static::$___propMasks[ $baseAlias ][ $property->name ] = $property->getModifiers();
			}
		}

		if($alias)
		{
			$splitAt    = mb_strpos($alias, "\\");
			$aliasSpace = mb_substr($alias, 0, $splitAt);
			$shortAlias = mb_substr($alias, $splitAt + 1);

			$classDef = sprintf(
				'namespace %s; class %s extends \%s {};'
				, $aliasSpace
				, $shortAlias
				, $baseAlias
			);
		}
		else
		{
			$classDef = sprintf(
				'namespace %s; class %s extends \%s {};'
				, $hashSpace
				, $subClass
				, $baseAlias
			);
		}

		if(!class_exists($baseAlias))
		{
			class_alias($baseClass, $baseAlias);
		}

		eval($classDef);

		if($alias)
		{
			class_alias($alias, $longName);
		}

		foreach($injections as $name => &$injection)
		{
			if(is_object($injection))
			{
				$injection = get_class($injection);
			}

			$longName::$___injections[$name] =& $injection;

			if((static::$___propMasks[ $baseAlias ][ $name ] ?? 0) & 1)
			{
				$longName::$name = $injection;
			}
		}

		return $longName;
	}
}
