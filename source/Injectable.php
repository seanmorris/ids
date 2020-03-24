<?php
namespace SeanMorris\Ids;
trait Injectable
{
	protected $___instances  = [];

	protected static
		$___injections   = []
		, $___extensions = []
		, $___propMeta   = []
		, $___propTypes  = []
		, $___classMatch = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/'
	;

	public function __construct()
	{
		$this->initInjections();
	}

	protected function initInjections()
	{
		foreach(static::$___propMeta as $property => $meta)
		{
			if(($meta[ 'mask' ] ?? 0) & 1)
			{
				continue;
			}

			if(isset(static::$___injections[$property]) && !$this->$property)
			{
				$propertyClass = static::$___injections[$property];

				$this->$property = new $propertyClass;

				continue;
			}

			if($meta[ 'type' ] && !$meta[ 'null' ] && !$meta[ 'internal' ])
			{
				$propertyClass = $meta[ 'type' ];

				$this->$property = new $propertyClass;
			}
		}
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

		$longHash  = sprintf('%s\\%s', $hashSpace, $subHash);

		if(!class_exists($baseAlias))
		{
			class_alias($baseClass, $baseAlias);
		}

		if($alias)
		{
			$splitAt    = mb_strpos($alias, "\\");
			$aliasSpace = mb_substr($alias, 0, $splitAt);
			$shortAlias = mb_substr($alias, $splitAt + 1);
			$longName   = $alias;
		}
		else
		{
			$aliasSpace = $hashSpace;
			$shortAlias = $subClass;
			$longName   = $longHash;
		}

		$propMeta = [];

		$reflection = new \ReflectionClass($baseClass);
		$properties = $reflection->getProperties();

		foreach($properties as $property)
		{
			$type = method_exists($property, 'getType')
				? $property->getType()
				: NULL;

			$propMeta[ $property->name ] = [
				'mask'       => $property->getModifiers()
				, 'type'     => $type ? $type->getName()    : NULL
				, 'internal' => $type ? $type->isBuiltin()  : NULL
				, 'null'     => $type ? $type->allowsNull() : NULL
			];
		}

		$injections += static::$___injections;

		eval(sprintf(
			<<<'ENDTEMPLATE'
			namespace %s; class %s extends \%s {
				protected static $___injections = [], $___propMeta = %s;
			}
			ENDTEMPLATE
			, $aliasSpace
			, $shortAlias
			, $baseAlias
			, var_export($propMeta, true)
		));

		if($alias && !class_exists($alias))
		{
			class_alias($alias, $longHash);
		}

		foreach($injections as $property => $injection)
		{
			if(function_exists($injection))
			{
				$injection = $injection();
			}
			else
			{
				if(is_object($injection))
				{
					$injections[$property] = $injection = get_class($injection);
				}

				if(!class_exists($injection))
				{
					unset($injections[$property]);
				}
			}
		}

		$longName::$___injections = $injections;

		return $longName;
	}

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

		if(isset(static::$___injections[$property])
			&& !($this->___instances[$property] ?? FALSE)
		){
			if(function_exists(static::$___injections[$property]))
			{
				$instance = (static::$___injections[$property])();
			}
			else if(class_exists(static::$___injections[$property]))
			{
				$instance = new static::$___injections[$property];
			}
			else
			{
				//ERROR STATE
			}

			$this->___instances[$property] = $instance;

			return $this->___instances[$property];
		}

		trigger_error(sprintf(
			'Undefined property: %s::$%s'
			, get_called_class()
			, $property
		), E_USER_NOTICE);
	}
}
