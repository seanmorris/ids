<?php
namespace SeanMorris\Ids;

use SeanMorris\Ids\Inject\FactoryMethod;
use traverible, Exception, Reflection, ReflectionClass;

$___extensions = 0;

CONST IDS_INJECT_SPACE = '___';

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

			if(ctype_upper($property[0]))
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
				else if(is_object($propertyClass) && $propertyClass instanceof WrappedMethod)
				{
					$this->$property = $propertyClass;
				}
				else if(class_exists($propertyClass))
				{
					$this->$property = new $propertyClass;
				}
				else
				{
					throw new Exception(sprintf(
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
					throw new Exception(sprintf(
						'Error: invalid classname provided: %s'
						, $propertyClass
					));
				}
			}
		}
	}

	public static function ___inject($injections)
	{

	}

	public static function inject($injections, $alias = '')
	{
		global $___extensions;

		if($alias && !preg_match(static::$___classMatch, $alias))
		{
			return FALSE;
		}

		$baseClass = get_called_class();

		$hashName  = '_' . sha1($baseClass);
		$hashSpace = implode('\\', [IDS_INJECT_SPACE, 'HashedClasses']);
		$baseHash  = implode('\\', [IDS_INJECT_SPACE, $hashName]);
		$subHash   = sprintf('%s_%d', $hashName, $___extensions++);
		$longHash  = implode('\\', [$hashSpace, $subHash]);

		if(!class_exists($baseHash))
		{
			class_alias($baseClass, $baseHash);
		}

		$longName = NULL;

		if($alias)
		{
			[$vendor, $restOfClass] = mb_split('\\\\', $alias, 2);

			$aliasSpaceParts = [];

			if($vendor === IDS_INJECT_SPACE)
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

			if($topSpace === IDS_INJECT_SPACE)
			{
				$longName   = $alias;
			}
			else
			{
				throw new Exception(sprintf(
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

		$reflection =  new ReflectionClass($baseClass);
		$properties =  $reflection->getProperties();
		$injections += static::$___injections;

		$placeholders = [];

		$suffixes = [];

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
				$modifiers = implode(' ', Reflection::getModifierNames($propMeta[ $property->name ][ 'mask' ]));

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
			, $baseHash
			, implode(' ', $placeholders)
			, implode(' ', $suffixes)
		);

		if($alias)
		{
			class_alias($alias, $longHash);
		}

		foreach($injections as $property => $injection)
		{
			if(is_object($injection) && $injection instanceof WrappedMethod)
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
				if(is_string($injection)
					&& class_exists($injection)
					&& ctype_lower($property[0])
					&& !is_a($injection, WrappedMethod::CLASS, TRUE)
				){
					$longName::$$property = new $injection;

					continue;
				}

				$longName::$$property = $injection;
			}
		}

		$longName::$___injections = $injections;

		static::___inject($injections);

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
			else if(is_object(static::$___injections[$property])
				&& static::$___injections[$property] instanceof WrappedMethod
			){
				$instance = static::$___injections[$property];
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
		$reflection = new ReflectionClass($originalClass);

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
