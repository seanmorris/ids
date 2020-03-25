<?php
namespace SeanMorris\Ids;

$___extensions = 0;

trait Injectable
{
	protected $___instances  = [];

	protected static
		$___classMatch   = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/'
		, $___propMeta   = []
		, $___injections = []
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

			if(ctype_upper($property))
			{
				$this->$property = $propertyClass;
				continue;
			}

			if(isset(static::$___injections[$property]) && !$this->$property)
			{
				$propertyClass = static::$___injections[$property];

				if(is_object($propertyClass) && $propertyClass instanceof FactoryMethod)
				{
					$this->$property = $propertyClass($this);
				}
				else if(class_exists($propertyClass))
				{
					$this->$property = new $propertyClass;
				}
				else
				{
					throw new \Exception(sprintf(
						'Error: invalid classname provided: %s'
						, $propertyClass
					));
				}

				continue;
			}

			if($meta[ 'type' ] && !$meta[ 'null' ])
			{
				$propertyClass = $meta[ 'type' ];

				if(class_exists($propertyClass))
				{
					$this->$property = new $propertyClass;
				}
				else
				{
					throw new \Exception(sprintf(
						'Error: invalid classname provided: %s'
						, $propertyClass
					));
				}
			}
		}
	}

	public static function inject(array $injections = [], $alias = '')
	{
		global $___extensions;

		if($alias && !preg_match(static::$___classMatch, $alias))
		{
			return FALSE;
		}

		$baseClass = get_called_class();

		// $injectSpace = \SeanMorris\Ids\Settings::read('injectSpace') ?: '___';
		$injectSpace = '___';

		$hashSpace = $injectSpace . '\\HashedClasses';

		$baseAlias = $hashSpace . '\\_' . sha1($baseClass);
		$subHash   = sprintf('%s_%d', $baseAlias, $___extensions++);

		$longHash  = sprintf('%s\\%s', $hashSpace, $subHash);

		if(!class_exists($baseAlias))
		{
			class_alias($baseClass, $baseAlias);
		}

		$longName = NULL;

		if($alias)
		{
			[$vendor, $restOfClass] = mb_split('\\\\', $alias, 2);

			$aliasSpaceParts = [];

			if($vendor === $injectSpace)
			{
				$topSpace  = $vendor;
				$classname  = $restOfClass;

				$splitAt    = mb_strrpos($alias, "\\");
				$aliasSpace = substr($alias, 0, $splitAt);
				$shortAlias = substr($alias, $splitAt + 1);
			}
			else
			{
				[$package, $topSpace, $classname] = mb_split('\\\\', $restOfClass, 3);

				$splitAt    = mb_strrpos($classname, "\\");
				$subSpace   = substr($classname, 0, $splitAt);
				$shortAlias = substr($classname, $splitAt);

				$aliasSpaceParts = [$vendor, $package, $topSpace, $subSpace];

				while($aliasSpaceParts && !$aliasSpaceParts[ count($aliasSpaceParts) - 1 ])
				{
					array_pop($aliasSpaceParts);
				}

				$aliasSpace = implode('\\', $aliasSpaceParts);
			}

			if($topSpace === $injectSpace)
			{
				$longName   = $alias;
			}
			else
			{
				throw new \Exception(sprintf(
					'Error: Classname is not in an injected namespace: %s'
					, $alias
				));
			}
		}
		else
		{
			$aliasSpace = $hashSpace;
			$shortAlias = $subHash;
			$longName   = $longHash;
		}

		$propMeta = [];

		$reflection =  new \ReflectionClass($baseClass);
		$properties =  $reflection->getProperties();
		$injections += static::$___injections;

		$placeholders = [];

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

			if(array_key_exists($property->name, $injections)
				&& $propMeta[ $property->name ][ 'mask' ] & 1
			){
				$modifiers = implode(' ', \Reflection::getModifierNames($propMeta[ $property->name ][ 'mask' ]));

				$placeholders[] = sprintf(
					'%s $%s;'
					, $modifiers
					, $property->name
				);
			}
		}

		$placeholders[] = 'protected static $___injections = [];';
		$placeholders[] = sprintf(
			'protected static $___propMeta = %s;'
			, var_export($propMeta, true)
		);

		static::generateClass(
			$aliasSpace
			, $shortAlias
			, $baseAlias
			, implode(' ', $placeholders)
		);

		if($alias && !class_exists($alias))
		{
			class_alias($alias, $longHash);
		}

		foreach($injections as $property => $injection)
		{
			if(is_object($injection) && $injection instanceof FactoryMethod)
			{
				// $injection = $injection($this);
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

			if(isset($propMeta[ $property ]) && $propMeta[ $property ][ 'mask' ] & 1)
			{
				$alias::$$property = $injection;
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
			if(is_object(static::$___injections[$property])
				&& static::$___injections[$property] instanceof FactoryMethod
			){
				$instance = (static::$___injections[$property])($this);
			}
			else if(class_exists(static::$___injections[$property]))
			{
				$instance = new static::$___injections[$property];
			}
			else
			{
				throw new \Exception(sprintf(
					'Invalid classname provided as injection: "%s"'
					, static::$___injections[$property]
				));
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

	protected static function cloneClass($originalClass, $cloneTo)
	{
		$reflection = new \ReflectionClass($originalClass);

		if($reflection->isUserDefined())
		{
			class_alias($originalClass, $cloneTo);
			return $cloneTo;
		}

		$cloneSplit = mb_strrpos($cloneTo, "\\");
		$cloneSpace = mb_substr($cloneTo, 0, $cloneSplit);
		$cloneName  = mb_substr($cloneTo, $cloneSplit + 1);

		static::generateClass(
			$cloneSpace
			, $cloneName
			, $originalClass
		);

		return $cloneTo;
	}

	protected static function generateClass($space, $name, $base, $body = NULL)
	{
		eval(sprintf(
			'namespace %s; class %s extends \%s {%s}'
			, $space
			, $name
			, $base
			, $body
		));

		return $space . '\\' . $name;
	}
}
