<?php
namespace SeanMorris\Ids;
class Model
{
	protected
		$id
		, $class
	;

	protected static
		$table
		, $relationshipClass
		, $relationshipTable
		, $createColumns = []
		, $readColumns = []
		, $updateColumns = []
		, $ignore = []
		, $hasOne = []
		, $hasMany = []
		, $byId = ['where' => [['id' => '?']]]
		, $byNothing = ['where' => [['id' => 'NULL', 'IS NOT']]]
		, $cache = []
	;

	public function create()
	{
		return $this->_create(get_called_class());
	}

	protected function _create($curClass)
	{
		$parentClass = get_parent_class($curClass);
		$parentModel = NULL;

		while($parentClass)
		{
			$tableProperty = new \ReflectionProperty($parentClass, 'table');

			if($parentClass::$table && $parentClass::$table !== static::$table)
			{
				$parentModel = $this->_create($parentClass);
				break;
			}

			$parentClass = get_parent_class($parentClass);
		}

		$columnsToWrappers = $curClass::getColumns('create', FALSE);

		\SeanMorris\Ids\Log::debug($curClass, $columnsToWrappers, $this);

		$wrappers = array_filter(
			$columnsToWrappers
			, function($columnName) use($columnsToWrappers)
			{
				return !isset($columnsToWrappers[$columnName]);
			}
		);

		$columns = array_keys($columnsToWrappers);

		$where = [];

		$insert = new \SeanMorris\Ids\Storage\Mysql\InsertStatement($curClass::$table);
		
		$values = [];

		// @TODO: Store Relationships

		foreach($columns as $column)
		{
			$colVal = $this->$column;

			if(isset(static::$hasOne[$column]))
			{
				$columnClass = static::$hasOne[$column];

				if(is_array($colVal)
					&& isset($colVal['id'])
					&& isset($colVal['class'])
					&& is_a($colVal['class'], $columnClass, true)
				){
					$columnClass = $colVal['class'];
				}
				else if(is_array($colVal)
					&& isset($colVal['class'])
					&& !is_a($colVal['class'], $columnClass, true)
					|| (is_array($colVal) && !$colVal['class'])
				){
					throw new \Exception(sprintf(
						'Bad id and/or classname supplied for column %s.'
						, $column
					));
				}
				
				if(is_array($colVal) && isset($colVal['id']))
				{
					$columnObject = $columnClass::loadOneById($colVal['id']);
					$columnObject->consume($colVal);
					$columnObject->save();

					\SeanMorris\Ids\Log::debug($columnObject);

					$colVal = $columnObject->id;
				}
				else if(is_array($colVal))
				{
					$columnObject = new $columnClass;
					$columnObject->consume($colVal);
					$columnObject->save();

					\SeanMorris\Ids\Log::debug($columnObject);

					$colVal = $columnObject->id;
				}
				else if(is_object($colVal) && is_a($colVal, $columnClass) && isset($colVal->id))
				{
					$colVal = $colVal->id;
				}
			}

			if($column === 'class')
			{
				$values[] = get_called_class();
			}
			else if(isset($wrappers[$column]) && $insert->hasReplacements($wrappers[$column]))
			{
				$values[] = $colVal;
			}
			elseif(!isset($wrappers[$column]))
			{
				$values[] = $colVal;
			}
		}

		if($curClass::beforeCreate($this, $values) === FALSE
			|| $curClass::beforeWrite($this, $values) === FALSE
		){
			return false;
		}

		if(isset($parentModel, $parentModel->id))
		{
			$columns[] = 'id';
			$values[] = $id = $parentModel->id;
			$insert->columns(...$columns)->wrappers($wrappers);
			$inserted = $insert->execute(...$values);
		}
		else
		{
			$insert->columns(...$columns)->wrappers($wrappers);
			$inserted = $id = $insert->execute(...$values);
		}
		
		$saved = $curClass::loadOneById($id);
		
		foreach($this as $property => &$value)
		{
			if(!property_exists($curClass, $property))
			{
				continue;
			}

			$value = $saved->$property;
		}

		if($id || $inserted)
		{
			$curClass::afterUpdate($this, $values);

			return $this;
		}

		return false;
	}

	public function update()
	{
		return $this->_update(get_called_class());
	}	

	protected function _update($curClass)
	{
		\SeanMorris\Ids\Log::debug(get_called_class());

		$columnsToWrappers = $curClass::getColumns('update', FALSE);

		$wrappers = array_filter(
			$columnsToWrappers
			, function($columnName) use($columnsToWrappers)
			{
				return !isset($columnsToWrappers[$columnName]);
			}
		);

		$columns = array_keys($columnsToWrappers);

		$where = [];

		$update = new \SeanMorris\Ids\Storage\Mysql\UpdateStatement($curClass::$table);
		$update->columns(...$columns)->wrappers($wrappers)
			->conditions([['id' => '?']]);

		$values = [];
		$namedValues = [];

		foreach($columns as $column)
		{
			$colVal = $this->$column;

			if(isset(static::$hasOne[$column]))
			{
				$columnClass = static::$hasOne[$column];

				if(is_array($colVal)
					&& isset($colVal['id'])
					&& isset($colVal['class'])
					&& (
						is_a($colVal['class'], $columnClass)
						|| $colVal['class'] == $columnClass
					)
				){
					$columnClass = $colVal['class'];
				}
				else if(isset($colVal['class']))
				{
					throw new \Exception(sprintf(
						'Bad classname supplied for column %s (%s).'
						, $column
						, isset($colVal['class'])
							? print_r($colVal['class'], true)
							: null
					));
				}	

				if(is_array($colVal) && isset($colVal['id']))
				{
					$columnObject = $columnClass::loadOneById($colVal['id']);

					$columnObject->consume($colVal);

					$columnObject->save();

					\SeanMorris\Ids\Log::debug($columnObject);

					$colVal = $colVal['id'];
				}
				else
				{
					// @todo Create new model based on input/classDef
				}
			}

			if(isset($wrappers[$column]) && $update->hasReplacements($wrappers[$column]))
			{
				$namedValues[$column] = $values[] = $colVal;
			}
			elseif(!isset($wrappers[$column]))
			{
				$namedValues[$column] = $values[] = $colVal;	
			}
		}

		$values[] = $this->id;

		if($curClass::beforeUpdate($this, $namedValues) === FALSE
			|| $curClass::beforeWrite($this, $namedValues) === FALSE
		){
			return FALSE;
		}

		$update->execute(...$values);

		if($this->id)
		{
			$reflection = new \ReflectionClass($curClass);

			foreach($this as $property => $value)
			{
				if(!$reflection->hasProperty($property))
				{
					continue;
				}

				$reflectionProperty = $reflection->getProperty($property);

				if($reflectionProperty->class !== $curClass)
				{
					continue;
				}

				if(isset($curClass::$hasOne[$property]))
				{
					// var_dump($this->{$property});
					//die;
					//$this->storeRelationship($property, $this->{$property});
				}

				if(isset($curClass::$hasMany[$property]) && is_array($this->{$property}))
				{
					$this->storeRelationships($property, $this->{$property});
				}
			}

			$saved = NULL;

			if($parentClass = get_parent_class($curClass))
			{
				$tableProperty = new \ReflectionProperty($parentClass, 'table');
				$curParentClass = $parentClass;

				while($parentClass && $tableProperty->class !== $parentClass)
				{
					\SeanMorris\Ids\Log::debug('CHECKING CLASS ' . $parentClass);
					if($parentClass::beforeUpdate($this, $namedValues) === FALSE
						|| $parentClass::beforeWrite($this, $namedValues) === FALSE
					){
						return FALSE;
					}
					$parentClass = get_parent_class($parentClass);
					$tableProperty = new \ReflectionProperty($parentClass, 'table');
				}

				if($parentClass
					&& $parentClass::$table
					&& $tableProperty->class === $parentClass
				){
					\SeanMorris\Ids\Log::debug('ASCENDING TO ' . $parentClass);
					if(!$this->_update($parentClass))
					{
						return FALSE;
					}
				}

				$parentClass = $curParentClass;
				$tableProperty = new \ReflectionProperty($parentClass, 'table');

				while($parentClass && $tableProperty->class !== $parentClass)
				{
					\SeanMorris\Ids\Log::debug('CHECKING CLASS ' . $parentClass);
					if($saved
						&& $parentClass::afterUpdate($saved, $namedValues) === FALSE
						|| $parentClass::afterWrite($saved, $namedValues) === FALSE
					){
						return FALSE;
					}
					$parentClass = get_parent_class($parentClass);
					$tableProperty = new \ReflectionProperty($parentClass, 'table');
				}
			}

			$saved = static::loadOneById($this->id);

			if($curClass::afterUpdate($saved, $namedValues) === FALSE
				|| $curClass::afterWrite($saved, $namedValues) === FALSE
			){
				return FALSE;
			}

			if(!$saved)
			{
				\SeanMorris\Ids\Log::debug([$this, $saved]);

				return FALSE;
			}

			foreach($this as $property => $value)
			{
				if(isset($curClass::$hasMany[$property]))
				{
					continue;
				}

				$this->{$property} = $saved->$property;
			}

			return $this;
		}

		return FALSE;
	}

	public function delete()
	{
		$tables = [];
		$class = get_called_class();

		while($class)
		{
			$tables[] = $class::$table;

			$class = get_parent_class($class);
		}

		$tables = array_unique(array_filter($tables));

		$failed = false;

		foreach($tables as $table)
		{
			$delete = new \SeanMorris\Ids\Storage\Mysql\DeleteStatement($table);
			$delete->conditions([['id' => '?']]);
			if(!$delete->execute($this->id))
			{
				$failed = true;
			}
		}

		return !$failed;
	}

	public function save()
	{
		if(!$this->id)
		{
			return $this->create();
		}
		else
		{
			return $this->update();
		}			
	}

	public static function clearCache($descentants = TRUE)
	{
		foreach(static::$cache as $class => &$cache)
		{
			if($descentants && is_subclass_of($class, get_called_class(), TRUE)
				|| $class === get_called_class()
			){
				$cache = [];
			}
		}
	}

	public static function __callStatic($name, $args = null)
	{
		$cacheKey = get_called_class()
			. '::'
			. $name
			. '  '
			. md5(print_r(func_get_args(), 1));

		if(!isset(static::$cache[$cacheKey]))
		{
			//static::$cache[$cacheKey] = NULL;
		}

		$cache =& static::$cache[get_called_class()][$name][md5(print_r(func_get_args(), 1))];

		\SeanMorris\Ids\Log::debug(sprintf(
			'Static call to %s::%s, Cache: %s'
			, get_called_class()
			, $name
			, $cacheKey
			, $cache ? '*' : ''
		), $args);

		if(!$args)
		{
			$args = [];
		}

		$def = static::resolveDef($name, $args);
		$select = static::selectStatement($name, null, $args);
		$gen = $select->generate();
		
		$rawArgs = $args;

		$args = array_map(
			function($arg)
			{
				if($arg instanceof Model)
				{
					return $arg->id;
				}

				return $arg;
			},
			$args
		);

		// $count = $select->countStatement('id');
		// $countResult = $count->execute(...$args);


		if(isset($def['type']) && $def['type'] == 'generate')
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Generating %s', get_called_class()
			));

			return function(...$overArgs) use($gen, $args, $rawArgs, &$cache)
			{
				$overArgs = [];
				$i = 0;

				if($cache)
				{
					while(isset($cache[$i]))
					{
						\SeanMorris\Ids\Log::debug('From cache...');

						yield $cache[$i];
						$i++;
					}
				}
				else
				{
					$args = $overArgs + $args;

					foreach($gen(...$args) as $skeleton)
					{
						$subSkeleton = static::subSkeleton($skeleton);

						if(static::beforeRead(NULL, $subSkeleton) === FALSE)
						{
							continue;
						}

						$model = static::instantiate($skeleton, $args, $rawArgs);

						if(static::afterRead($model, $subSkeleton) === FALSE)
						{
							continue;
						}

						\SeanMorris\Ids\Log::debug('Loaded ', $i, $model);

						$cache[$i] = $model;
						
						yield $model;

						$i++;
					}
				}
			};
		}

		if(isset($def['type']) && $def['type'] == 'loadOne')
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Loading one %s', get_called_class()
			));

			if(isset($cache[0]))
			{
				\SeanMorris\Ids\Log::debug('From cache...');

				return $cache[0];
			}

			foreach($gen(...$args) as $skeleton)
			{	
				$subSkeleton = static::subSkeleton($skeleton);

				if(static::beforeRead(NULL, $subSkeleton) === FALSE)
				{
					continue;
				}

				$model = static::instantiate($skeleton, $args, $rawArgs);

				if(static::afterRead($model, $subSkeleton) === FALSE)
				{
					continue;
				}

				\SeanMorris\Ids\Log::debug('Loaded ', $model);

				$cache[0] = $model;

				return $model;
			}

			return false;
		}

		if(isset($def['type']) && $def['type'] == 'get')
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Getting %s', get_called_class()
			));

			if($cache)
			{
				\SeanMorris\Ids\Log::debug('From cache...');

				return $cache;
			}

			$models = [];

			foreach($gen(...$args) as $skeleton)
			{
				$subSkeleton = static::subSkeleton($skeleton);

				if(static::beforeRead(NULL, $subSkeleton) === FALSE)
				{
					continue;
				}

				$model = static::instantiate($skeleton, $args, $rawArgs);

				if(static::afterRead($model, $subSkeleton) === FALSE)
				{
					continue;
				}

				$cache[count($models)] = $model;

				$models[] = $model;
				
				\SeanMorris\Ids\Log::debug('Got ', $model);
			}

			return $models;
		}

		return function(...$overArgs) use($gen, $args, $rawArgs, &$cache)
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Loading %s', get_called_class()
			));

			if($cache)
			{
				\SeanMorris\Ids\Log::debug('From cache...');

				return $cache;
			}

			$models = [];

			$args = $overArgs + $args;

			foreach($gen(...$args) as $skeleton)
			{
				$subSkeleton = static::subSkeleton($skeleton);

				if(static::beforeRead(NULL, $subSkeleton) === FALSE)
				{
					continue;
				}

				$model = static::instantiate($skeleton, $args, $rawArgs);

				if(static::afterRead($model, $subSkeleton) === FALSE)
				{
					continue;
				}

				\SeanMorris\Ids\Log::debug('Loaded ', $model);

				$cache[count($models)] = $model;

				$models[] = $model;
			}

			return $models;
		};
	}

	protected static function instantiate($skeleton, $args = [], $rawArgs = [])
	{
		if(!isset($skeleton[static::$table]))
		{
			return;
		}

		$subSkeleton = current($skeleton[static::$table]);

		$class = null;

		if(isset($subSkeleton['class']))
		{
			$class = $subSkeleton['class'];
		}
		
		if($class == NULL || !is_subclass_of($class, get_called_class()))
		{
			$class = get_called_class();
		}

		$instance = new $class();		

		$instance->consumeStatement($skeleton, $args, $rawArgs);

		return $instance;
	}

	protected function storeRelationships($column, $newSubjects)
	{
		\SeanMorris\Ids\Log::debug(sprintf(
			'Storing relatioships for %s->%s'
			, get_called_class()
			, $column
		));
		$deltas = [];
		$values = [];
		$newRelationships = [];
		$oldRelationships = [];

		foreach($this->genSubjectRelationships($column) as $delta => $relationship)
		{
			$subjectId = $relationship->subjectId;
			$oldRelationships[$subjectId][] = $relationship;
			
			$values[$delta][] = $subjectId;
			$deltas[$subjectId][] = $delta;
		}

		foreach($newSubjects as $delta => $subjectId)
		{
			if(is_object($subjectId))
			{
				$subjectId = $subjectId->id;
			}

			if(!isset($oldRelationships[$subjectId]) || !$oldRelationships[$subjectId])
			{
				$subjectClass = static::$hasMany[$column];

				$subject = $subjectClass::loadOneById($subjectId);

				if($subject)
				{
					$relationshipClass = '\SeanMorris\Ids\Relationship';

					if(static::$relationshipClass)
					{
						$relationshipClass = static::$relationshipClass;
					}

					$relationship = new $relationshipClass;

					$relationship->consume([
							'ownerId'         => $this->id
							, 'ownerClass'    => get_called_class()
							, 'property'      => $column
							, 'subjectId'     => $subjectId
							, 'subjectClass'  => $subjectClass
							, 'delta'         => $delta
						]
						, []
						, [$this, $column]
					);
					
					$newRelationships[] = $relationship;
				}
			}
			else
			{
				$relationship = array_shift($oldRelationships[$subjectId]);

				$relationship->delta = $delta;

				$newRelationships[] = $relationship;
			}
		}

		$newRelationships = array_values($newRelationships);

		foreach($newRelationships as $delta => $newRel)
		{
			$newRel->save();
		}

		foreach($oldRelationships as $oldRels)
		{
			foreach($oldRels as $oldRel)
			{
				$oldRel->delete();				
			}
		}
	}

	protected static function subSkeleton($skeleton)
	{
		if(!static::$table
			|| !isset($skeleton[static::$table])
			//&& is_array($skeleton[static::$table])
		){
			return [];
		}

		return array_shift($skeleton[static::$table]);
	}

	protected function consumeStatement($skeleton, $args = [], $rawArgs = [])
	{
		$subSkeleton = static::subSkeleton($skeleton);

		$baseClass = get_class();
		$parentClass = get_parent_class(get_called_class());

		while($parentClass)
		{
			$subSkeleton += $parentClass::subSkeleton($skeleton);

			$parentClass = get_parent_class($parentClass);
		}

		foreach($subSkeleton as $column => $value)
		{
			$this->$column = $value;
		}
	}

	public static function getGenerator($name, $class = NULL)
	{
		if(!$class)
		{
			$class = get_called_class();
		}

		$reflection = new \ReflectionClass($class);
		
		if($reflection->hasProperty($name))
		{
			return $reflection->getProperty($name);
		}
		else
		{
			return false;
		}
	}

	protected static function resolveDef($name, &$args = null)
	{
		\SeanMorris\Ids\Log::debug( "MODEL RESOLVEDEF\n" );
		$type = 'generate';
		
		if(preg_match('/^(loadOne|load|generate|get)(By.+)/', $name, $match))
		{
			if(isset($match[1]))
			{
				$type = lcfirst($match[1]);
			}

			if(isset($match[2]))
			{
				$name = lcfirst($match[2]);
			}
		}

		$def = ['type' => $type];

		$class = get_called_class();

		while($class)
		{
			try
			{
				$property = new \ReflectionProperty($class, $name);
			}
			catch(\ReflectionException $exception)
			{
				$class = get_parent_class($class);
				continue;
			}

			$propertyClass = $property->class;

			if($class::$table == $propertyClass::$table)
			{
				$def = $class::$$name;
				$def['type'] = $type;
				$def['class'] = $class;
				break;
			}

			$parentClass = get_parent_class($class);

			if($parentClass::$table && $parentClass::$table !== $class::$table)
			{
				break;
			}

			$class = $parentClass;
		}

		\SeanMorris\Ids\Log::debug( "MODEL RESOLVEDEF END\n" );
		return $def;
	}

	protected static function selectStatement($selectDefName, $superior = null, $args = [], $table = NULL)
	{
		$table = !empty(static::$table) ? static::$table : $table;

		$select = new \SeanMorris\Ids\Storage\Mysql\SelectStatement($table);

		$columnsToWrappers = static::getColumns('read', FALSE);

		$wrappers = array_filter(
			$columnsToWrappers
			, function($columnName) use($columnsToWrappers)
			{
				if(!isset($columnsToWrappers[$columnName]))
				{
					return true;
				}
			}
		);

		$columns = array_keys($columnsToWrappers);

		$called = get_called_class();

		$selectDef = $called::resolveDef($selectDefName, $args);

		\SeanMorris\Ids\Log::debug(
			'Resolved def'
			, $selectDefName
			, $selectDef
			, 'for'
			, get_called_class()
			, 'of type'
			, isset($def['type'])
				? $def['type']
				: 'generate'
		);

		$where = [];
		$order = [];

		if(isset($selectDef['where']))
		{
			$where = $selectDef['where'];
		}

		if(isset($selectDef['order']))
		{
			$order = $selectDef['order'];
		}

		$select->columns(...$columns)
			->wrappers($wrappers)
			->order($order)
			->conditions($where)
		;

		\SeanMorris\Ids\Log::debug($called, $selectDefName, $selectDef, isset($selectDef['join']));

		if(isset($selectDef['join']) && is_array($selectDef['join']))
		{
			\SeanMorris\Ids\Log::debug($called, $selectDef['join']);

			foreach($selectDef['join'] as $joinClass => $join)
			{
				$defName = 'loadBy'.ucwords($join['by']);
				$subSelect = $joinClass::selectStatement($defName, $select, $args, $table);

				$select->subjugate($subSelect);
				$select->join($subSelect, $join['on'], 'id');		
			}
		}

		$curClass = get_called_class();
		$parentClass = get_parent_class($curClass);

		while($parentClass && $parentClass::$table == $curClass::$table)
		{
			$parentClass = get_parent_class($parentClass);
		}

		if($parentClass && $parentClass::$table)
		{
			$subSelect = $parentClass::selectStatement($selectDefName, $select, $args, $table);

			$subSelect->subjugate($select);
			$subSelect->join($select, 'id', 'id');

			$select = $subSelect;
		}

		return $select;
	}

	public static function getProperties($all = FALSE)
	{
		$result = [];

		$class = get_called_class();

		while($class)
		{
			$reflection = new \ReflectionClass($class);
			$proprties = $reflection->getProperties();
			$tableProperty = $reflection->getProperty('table');

			foreach($proprties as $property)
			{
				$propClass = $property->class;
				
				if($property->isStatic())
				{
					continue;
				}

				if($property->class !== $class)
				{
					continue;
				}

				$result[] = $property->name;
			}

			$class = get_parent_class($class);
		}

		return $result;
	}

	public static function getTable()
	{
		return static::$table;
	}

	protected static function getColumns($type = null, $all = true)
	{
		$curClass = get_called_class();

		// \SeanMorris\Ids\Log::debug($curClass);

		$properties = $curClass::getProperties();

		switch($type) 
		{
			case 'create':
				$wrappers = static::$createColumns;
				break;
			case 'read':
				$wrappers = static::$readColumns;
				break;	
			case 'update':
				$wrappers = static::$updateColumns;
				break;
		}

		$columns = [];

		if(!$all)
		{
			$class = $curClass;
			$nonTableClasses = [];
			while($class)
			{
				$classTableProperty = new \ReflectionProperty($class, 'table');
				// \SeanMorris\Ids\Log::debug($class, $classTableProperty);	
				/*if($class::$table === NULL
					|| $classTableProperty->class !== $class
					|| $curClass == $class
				){*/
				if($class::$table === static::$table || !$class::$table)
				{
					$nonTableClasses[] = $class;
				}
				else
				{
					break;
				}
				
				$class = get_parent_class($class);
			}

			// \SeanMorris\Ids\Log::debug($curClass, $nonTableClasses);	
		}

		foreach($properties as $property)
		{
			$reflectionProperty = new \ReflectionProperty($curClass, $property);

			//\SeanMorris\Ids\Log::debug($curClass, $property, $reflectionProperty);

			if(!$all && !in_array($reflectionProperty->class, $nonTableClasses))
			{
				continue;
			}

			if(in_array($property, static::$ignore))
			{
				continue;
			}

			if(isset(static::$hasMany[$property]))
			{
				continue;
			}

			if(isset($wrappers[$property]))
			{
				$columns[$property] = $wrappers[$property];
			}
			else
			{
				$columns[$property] = $property;
			}
		}

		return $columns;
	}

	public function __get($name)
	{
		if(!isset($this->$name))
		{
			return;
		}
		
		return $this->$name;
	}

	public function consume($skeleton, $override = false)
	{
		if(static::beforeConsume($this, $skeleton) === FALSE)
		{
			return;
		}

		$proprties = static::getProperties();

		foreach($proprties as $property)
		{
			if(!$override && (
				$property == 'id'
				|| $property == 'publicId'
				|| $property == 'class'
				|| isset(static::$hasMany[$property])
			))
			{
				continue;
			}

			if(array_key_exists($property, $skeleton))
			{
				$this->{$property} = $skeleton[$property];
			}
		}

		foreach(static::$hasMany as $property => $class)
		{
			if(!isset($skeleton[$property]))
			{
				continue;
			}

			if(is_array($skeleton[$property]))
			{
				unset($skeleton[$property][-1], $skeleton[$property]['']);

				\SeanMorris\Ids\Log::debug(sprintf(
					'Consuming relationships for %s::%s'
					, get_called_class()
					, $property
				), $skeleton[$property]);

				$subModelsSubmitted = FALSE;

				foreach($skeleton[$property] as $delta => $values)
				{
					$propertyClass = $this::getSubjectClass($property);

					\SeanMorris\Ids\Log::debug(sprintf(
						'Consuming subModel of type %s for column %s'
						, $propertyClass
						, $property
					), $values);

					if(isset($values['id']) && $values['id'])
					{
						\SeanMorris\Ids\Log::debug('Using existing model');

						$this->{$property}[$delta] = $values['id'];

						$subModelsSubmitted = TRUE;
					}
					else if(isset($values['class']) && $values['class'])
					{
						\SeanMorris\Ids\Log::debug('Creating new model');

						if(is_a($values['class'], $propertyClass, TRUE))
						{
							$subject = new $values['class'];

							$subject->consume($values);

							if($subject->save())
							{
								$this->{$property}[$delta] = $subject->id;
							}
						}

						$subModelsSubmitted = TRUE;
					}
				}

				if(!$subModelsSubmitted)
				{
					$this->{$property} = [];
				}
			}
		}

		// print_r($this);

		static::afterConsume($this, $skeleton);
	}

	public function unconsume($children = 0)
	{
		$proprties = static::getProperties(TRUE);
		$skeleton = [];

		foreach($proprties as $property)
		{
			$skeleton[$property] = $this->$property;
		}

		\SeanMorris\Ids\Log::debug($children);

		if($children)
		{
			\SeanMorris\Ids\Log::debug(static::$hasMany);

			foreach(static::$hasMany as $property => $class)
			{
				$subjects = $this->getSubjects($property);

				\SeanMorris\Ids\Log::debug($property, $subjects);

				$skeleton[$property] = array_map(
					function($subject) use($children)
					{
						return $subject->unconsume($children -1);
					}
					, $subjects
				);
			}

			foreach(static::$hasOne as $property => $class)
			{
				$subject = $this->getSubject($property);

				\SeanMorris\Ids\Log::debug($property, $subject);

				if(!$subject)
				{
					continue;
				}

				$skeleton[$property] = $subject->unconsume($children -1);
			}
		}

		return $skeleton;
	}

	public function addSubject($property, $subject)
	{
		$subjectClass = get_class($subject);

		if($subjectClass && $subjectClass == $this->canHaveMany($property))
		{
			if(!$this->{$property})
			{
				$this->{$property} = $this->getSubjects($property);
			}

			$this->{$property}[] = $subject;
			return true;
		}

		if($subjectClass && $subjectClass == $this->canHaveOne($property))
		{
			$this->{$property} = $subject->id;
			return true;
		}

		return false;
	}

	public function getSubject($column = null)
	{
		if(!isset(static::$hasOne[$column]))
		{
			return false;
		}

		$class = static::$hasOne[$column];

		return $class::loadOneById($this->$column);
	}

	public static function getSubjectClass($column)
	{
		// \SeanMorris\Ids\Log::debug(get_called_class(), $column);

		$class = null;

		if(isset(static::$hasOne[$column]))
		{
			$class = static::$hasOne[$column];
		}
		elseif(isset(static::$hasMany[$column]))
		{
			$class = static::$hasMany[$column];
		}

		return $class;
	}

	public function canHaveOne($property)
	{
		return isset(static::$hasOne[$property])
			? static::$hasOne[$property]
			: FALSE;	
	}

	public function canHaveMany($property)
	{
		return isset(static::$hasMany[$property])
			? static::$hasMany[$property]
			: FALSE;
	}

	public function genSubjectRelationships($column)
	{
		$subjectClass = $this->getSubjectClass($column);

		if(!$subjectClass)
		{
			throw new \Exception(sprintf(
				'Cannot load subjects from %s::$%s'
				, get_called_class()
				, $column
			));
		}

		$relationshipClass = '\SeanMorris\Ids\Relationship';

		if(static::$relationshipClass)
		{
			$relationshipClass = static::$relationshipClass;
		}

		$gen = $relationshipClass::generateByOwner(
			$this, $column
		);

		foreach($gen() as $subjectRelationship)
		{
			if($subjectRelationship)
			{
				yield $subjectRelationship;
			}
		}
	}

	public function genSubjects($column)
	{
		foreach($this->genSubjectRelationships($column) as $subjectRelationship)
		{
			if($subjectRelationship)
			{
				yield $subjectRelationship->subject();
			}
		}
	}

	public function getSubjects($column)
	{
		$subjects = [];

		foreach($this->genSubjects($column) as $subject)
		{
			if(!$subject)
			{
				continue;
			}
			
			$subjects[] = $subject;
		}

		return $subjects;
	}

	public function getSubjectRelationships($column)
	{
		$subjects = [];

		foreach($this->genSubjectRelationships($column) as $subject)
		{
			$subjects[] = $subject;
		}

		return $subjects;
	}

	protected static function beforeConsume($instance, &$skeleton)
	{

	}

	protected static function afterConsume($instance, &$skeleton)
	{

	}

	protected static function beforeCreate($instance, &$skeleton)
	{

	}

	protected static function afterCreate($instance, &$skeleton)
	{

	}

	protected static function beforeWrite($instance, &$skeleton)
	{

	}

	protected static function afterWrite($instance, &$skeleton)
	{

	}

	protected static function beforeRead($instance)
	{

	}

	protected static function afterRead($instance)
	{

	}

	protected static function beforeUpdate($instance, &$skeleton)
	{
		
	}

	protected static function afterUpdate($instance, &$skeleton)
	{

	}

	protected static function beforeDelete($instance)
	{

	}

	protected static function afterDelete($instance)
	{

	}

	public static function getCache()
	{
		return static::$cache;
	}
}