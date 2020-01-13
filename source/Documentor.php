<?php
namespace SeanMorris\Ids;
class Documentor
{
	public static function docs($namespace)
	{
		$classes = \SeanMorris\Ids\Meta::classes();

		$classes = array_filter($classes, function($class) use($namespace) {
			return substr($class, 0, strlen($namespace)) === $namespace;
		});

		$classDocs = [];

		foreach($classes as $className)
		{
			$classDocs[$className] = (object)[];

			$package = \SeanMorris\Ids\Package::fromClass($className);

			$reflection = new \ReflectionClass($className);

			$classDocs[$className]->doc = NULL;

			if($dockblock = $reflection->getDocComment())
			{
				$classDocs[$className]->doc = $dockblock;
			}

			$classDocs[$className]->parent = get_parent_class($className) ?: NULL;
			$classDocs[$className]->file   = $reflection->getFileName();

			$classDocs[$className]->final    = $reflection->isFinal();
			$classDocs[$className]->abstract = $reflection->isAbstract();
			$classDocs[$className]->iterable = $reflection->isIterable();

			$classDocs[$className]->lines = [
				$reflection->getStartLine()
				, $reflection->getEndLine()
			];

			$classDocs[$className]->constants  = [];
			$classDocs[$className]->properties = [];
			$classDocs[$className]->traits     = [];
			$classDocs[$className]->interfaces = [];
			$classDocs[$className]->methods    = [];

			$constants = $reflection->getReflectionConstants();

			foreach($constants as $constant)
			{
				$constantName = $constant->name;

				if(($classDocs[$className]->constants[$constantName] ?? 0)
					&& (!$classDocs[$className]->constants[$constantName]->overrides)
				){
					$classDocs[$className]->constants[$constantName]->overrides = $constClassName;
					continue;
				}
				else if($classDocs[$className]->constants[$constantName] ?? 0)
				{
					continue;
				}

				$classDocs[$className]->constants[$constantName] = (object) [
					'value'       => $constant->getValue()
					, 'class'     => $constant->getDeclaringClass()
					, 'overrides' => NULL
					, 'public'    => $constant->isPublic()
					, 'private'   => $constant->isPrivate()
					, 'protected' => $constant->isProtected()
				];
			}

			$defaults   = $reflection->getDefaultProperties();
			$properties = $reflection->getProperties();

			foreach($properties as $property)
			{
				$classDocs[$className]->properties[$property->name] = (object)[];

				$propertyDoc = $classDocs[$className]->properties[$property->name];

				$propertyDoc->doc = NULL;

				if($doc = $property->getDocComment())
				{
					$propertyDoc->doc = $doc;
				}

				$propertyClass = $property->getDeclaringClass();

				$propertyDoc->default   = $defaults[$property->name];
				$propertyDoc->static    = $property->isStatic();
				$propertyDoc->public    = $property->isPublic();
				$propertyDoc->private   = $property->isPrivate();
				$propertyDoc->protected = $property->isProtected();
				$propertyDoc->class     = $propertyClass->name;
				$propertyDoc->file      = $propertyClass->getFileName();
			}

			$traits  = $reflection->getTraits();
			$traitAliases = $reflection->getTraitAliases();

			foreach($traits as $trait)
			{
				$traitName = $trait->name;

				$classDocs[$className]->traits[$traitName] = (object)[];

				$traitDoc = $classDocs[$className]->traits[$traitName];

				$traitDoc->file = $trait->getFileName();

				$traitDoc->lines = [
					$trait->getStartLine()
					, $trait->getEndLine()
				];
			}

			$interfaces = $reflection->getInterfaces();

			foreach($interfaces as $interface)
			{
				$interfaceName = $interface->name;

				$classDocs[$className]->interfaces[$interfaceName] = (object)[];

				$interfaceDoc = $classDocs[$className]->interfaces[$interfaceName];

				$interfaceDoc->file = $interface->getFileName();

				$interfaceDoc->lines = [
					$interface->getStartLine()
					, $interface->getEndLine()
				];
			}

			$methods = $reflection->getMethods();

			foreach($methods as $method)
			{
				$methodName = $method->name;

				$classDocs[$className]->methods[$methodName] = (object)[];

				$methodDoc = $classDocs[$className]->methods[$methodName];

				$methodDoc->doc = NULL;

				if($doc = $method->getDocComment())
				{
					$methodDoc->doc = $doc;
				}

				$methodFile = new Disk\File($method->getFileName());

				$prototype = NULL;

				try{
					$prototype = $method->getPrototype();
				}
				catch(\Exception $e){}
				finally {
					$prototype = $prototype ?? NULL;
				}

				$methodDoc->static     = $method->isStatic();
				$methodDoc->final      = $method->isFinal();
				$methodDoc->abstract   = $method->isAbstract();
				$methodDoc->public     = $method->isPublic();
				$methodDoc->private    = $method->isPrivate();
				$methodDoc->protected  = $method->isProtected();
				$methodDoc->variadic   = $method->isVariadic();
				$methodDoc->returnType = $method->getReturnType();
				$methodDoc->reference  = $method->returnsReference();
				$methodDoc->generator  = $method->isGenerator();
				$methodDoc->class      = $method->getDeclaringClass()->name;
				$methodDoc->file       = $methodFile->subtract(IDS_ROOT);
				$methodDoc->original   = isset($traitAliases[$methodName])
					? $traitAliases[$methodName]
					: NULL;
				$methodDoc->overrides  = $prototype
					? [$prototype->class, $prototype->name]
					: NULL;

				$methodDoc->lines     = [
					$method->getStartLine()
					, $method->getEndLine()
				];

				// $methodDoc->body = $method::export($methodDoc->class, $methodName);

				$parameters = $method->getParameters();

				$methodDoc->parameters = [];

				foreach($parameters as $parameter)
				{
					$methodDoc->parameters[$parameter->name] = (object)[
						'position'     => $parameter->getPosition()
						, 'optional'   => $parameter->isOptional()
						, 'variadic'   => $parameter->isVariadic()
						, 'reference'  => $parameter->isPassedByReference()
						, 'type'       => $parameter->getType()
						, 'array'      => $parameter->isArray()
						, 'null'       => $parameter->allowsNull()
						, 'hasDefault' => $parameter->isDefaultValueAvailable()
						, 'default'    => $parameter->isDefaultValueAvailable()
							? $parameter->getDefaultValue()
							: NULL
						, 'constant'   => $parameter->isDefaultValueAvailable()
							? $parameter->getDefaultValueConstantName()
							: NULL
						, 'callable'   => $parameter->isCallable()
					];
				}
			}
		}

		return $classDocs;
	}
}
