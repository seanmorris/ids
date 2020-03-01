<?php
namespace SeanMorris\Ids;
class Model
{
	protected $id, $class;

	private $_changed = [], $_unconsumed;

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
		, $idCache = []
		, $instances = []
		, $columnMeta = []
	;

	public function create()
	{
		return $this->_create(get_called_class());
	}

	protected function _create($curClass)
	{

		$backtrace = debug_backtrace();

		$trace = [];

		foreach($backtrace as $frame)
		{
			$trace[] = sprintf('%s:%d', $frame['file'] ?? '--', $frame['line'] ?? 0);
		}

		\SeanMorris\Ids\Log::debug(sprintf(
			'%s::_create(...)'
				. PHP_EOL
				. "\t" . "Called from\n\t\t%s."
				. PHP_EOL
			, get_called_class()
			, implode(PHP_EOL . "\t\t", $trace)
		));

		static::clearCache();

		$parentClass = get_parent_class($curClass);
		$parentModel = NULL;

		while($parentClass)
		{
			$tableProperty = new \ReflectionProperty($parentClass, 'table');

			if($parentClass::$table && $parentClass == $tableProperty->class)
			{
				$parentModel = $this->_create($parentClass);
				break;
			}

			$parentClass = get_parent_class($parentClass);
		}

		$columnsToWrappers = $curClass::getColumns('create', FALSE);

		$wrappers = array_filter(
			$columnsToWrappers
			, function($columnName) use($columnsToWrappers)
			{
				return !isset($columnsToWrappers[$columnName]);
			}
		);

		$columns = array_keys($columnsToWrappers);

		$insert = new \SeanMorris\Ids\Mysql\InsertStatement($curClass::$table);

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
					&& is_a($colVal['class'], $columnClass, true)
				){
					$columnClass = $colVal['class'];
				}
				else if(is_array($colVal)
					&& (!isset($colVal['class'])
					 	|| !$colVal['class']
					)
				){
					$colVal['class'] = $columnClass;
				}
				else if(is_array($colVal)
					&& isset($colVal['class'])
					&& $colVal['class']
					&& !is_a($colVal['class'], $columnClass, true)
					|| (is_array($colVal)
						&& isset($colVal['class'])
						&& is_array($colVal)
						&& !$colVal['class']
					)
				){
					throw new \Exception(sprintf(
						'Bad id and/or classname supplied for column %s.'
						, $column
					));
				}

				if(is_array($colVal)
					&& is_numeric(key($colVal))
					&& count($colVal) == 1
					&& is_array(current($colVal))
				){
					$colVal = reset($colVal);
				}

				if(is_array($colVal) && isset($colVal['id']) && $colVal['id'])
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

					\SeanMorris\Ids\Log::debug(
						$columnClass
						, $colVal
						, $columnObject
					);
					$columnObject->save();

					\SeanMorris\Ids\Log::debug($columnObject);

					$colVal = $columnObject->id;
				}
				else if(is_object($colVal) && is_a($colVal, $columnClass))
				{
					if(!isset($colVal->id))
					{
						$colVal->save();
					}

					$colVal = $colVal->id;
				}
			}

			if($column === 'class')
			{
				$values[] =& $namedValues[$column];
				$namedValues[$column] = get_called_class();
			}
			else if(isset($wrappers[$column]) && $insert->hasReplacements($wrappers[$column]))
			{
				$values[] = $colVal;

				$namedValues[$column] =& $values[ count($values)-1 ];
			}
			elseif(!isset($wrappers[$column]))
			{
				$values[] = $colVal;

				$namedValues[$column] =& $values[ count($values)-1 ];
			}
		}

		if($curClass::beforeCreate($this, $namedValues) === FALSE
			| $curClass::beforeWrite($this, $namedValues) === FALSE
		){
			return false;
		}

		$id = $this->id;

		$inserted = NULL;

		if(!$parentClass || $parentClass::$table !== static::$table)
		{
			if($parentModel && $parentModel->id)
			{
				$columns[] = 'id';
				$values[] = $id = $parentModel->id;
				$insert->columns(...$columns)->wrappers($wrappers);
				$inserted = $insert->execute(...$values);
			}
			else
			{
				$insert->columns(...$columns)->wrappers($wrappers);
				$inserted = $insert->execute(...$values);
				if(!is_bool($inserted))
				{
					$id = $inserted;
				}
			}

			$saved = $curClass::loadOneFlatRecord($id);

			if(!$saved)
			{
				return;
			}
		}
		else
		{
			$saved = $parentModel;
		}

		$reflection = new \ReflectionClass($curClass);

		if($saved)
		{
			foreach($saved as $property => $value)
			{
				if(isset($curClass::$hasOne[$property]))
				{
					continue;
				}

				if(isset($curClass::$hasMany[$property]))
				{
					continue;
				}

				if($saved->$property == NULL && $curClass !== get_called_class())
				{
					continue;
				}

				$this->$property = $saved->$property;
			}
		}

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

			if(isset($curClass::$hasMany[$property]) && is_array($value))
			{
				try
				{
					$this->storeRelationships($property, $this->{$property});
				}
				catch(\Exception $e)
				{
					\SeanMorris\Ids\Log::logException($e);
				}

			}
		}

		if($id || $inserted)
		{
			$curClass::afterWrite($this, $values);
			$curClass::afterCreate($this, $values);

			if($id)
			{
				$this->id = $id;
			}

			return $this;
		}

		return false;
	}

	public function update()
	{
		return $this->_update(get_called_class());
	}

	protected function postUpdate()
	{
		return $this->_update(get_called_class(), TRUE);
	}

	protected function _update($curClass, $postUpdate = false)
	{
		static::clearCache();

		if($this->_changed)
		{
			$backtrace = debug_backtrace();

			$trace = [];

			foreach($backtrace as $frame)
			{
				$trace[] = sprintf('%s:%d', $frame['file'] ?? '--', $frame['line'] ?? 0);
			}

			\SeanMorris\Ids\Log::debug(sprintf(
				'%s::_update(...)'
					. PHP_EOL
					. "\t" . "Called from\n\t\t%s."
					. PHP_EOL
				, get_called_class()
				, implode(PHP_EOL . "\t\t", $trace)
			));

			$columnsToWrappers = $curClass::getColumns('update', FALSE);

			$wrappers = array_filter(
				$columnsToWrappers
				, function($columnName) use($columnsToWrappers)
				{
					return !isset($columnsToWrappers[$columnName]);
				}
			);

			$columns = array_keys($columnsToWrappers);

			$update = new \SeanMorris\Ids\Mysql\UpdateStatement($curClass::$table);
			$update->columns(...$columns)->wrappers($wrappers)
				->conditions([['id' => '?']]);

			$values = [];
			$namedValues = [];

			foreach($columns as $column)
			{
				$colVal = $this->$column;

				if(is_array($colVal) && isset(static::$hasOne[$column]))
				{
					$colVal = $this->storeSubmodel($column, $colVal);
				}
				else if(is_array($colVal) && isset(static::$hasMany[$column]))
				{
					$colVal = $this->storeSubmodel($column, $colVal);
				}
				else if(is_object($colVal) && isset($colVal->id))
				{
					$colVal = $colVal->id;
				}

				if(isset($wrappers[$column]) && $update->hasReplacements($wrappers[$column]))
				{
					$namedValues[$column] =& $values[];
					$namedValues[$column] = $colVal;
				}
				elseif(!isset($wrappers[$column]))
				{
					$namedValues[$column] =& $values[];
					$namedValues[$column] = $colVal;
				}
			}

			$values[] = $this->id;

			if(!$postUpdate)
			{
				if(($curClass::beforeUpdate($this, $namedValues) === FALSE)
					| ($curClass::beforeWrite($this, $namedValues) === FALSE)
				){
					return FALSE;
				}
			}

			$update->execute(...$values);

			static $reflection = [];

			if($this->id)
			{
				if(!isset($reflections[$curClass]))
				{
					$reflections[$curClass] = new \ReflectionClass($curClass);
				}

				$reflection = $reflections[$curClass];

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
							| $parentClass::beforeWrite($this, $namedValues) === FALSE
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

				$saved = static::loadOneRecord($this->id);

				if(!$postUpdate)
				{
					if($curClass::afterUpdate($saved, $namedValues) === FALSE
						|| $curClass::afterWrite($saved, $namedValues) === FALSE
					){
						return FALSE;
					}
				}

				if(!$saved)
				{
					\SeanMorris\Ids\Log::debug(['NOT SAVED', $this, $saved]);

					return FALSE;
				}
			}

			foreach($this as $property => $value)
			{
				if(isset($curClass::$hasMany[$property]))
				{
					continue;
				}

				$this->{$property} = $saved->$property;
			}

			static::clearCache();

			return $this;
		}

		return FALSE;
	}

	public function delete()
	{
		foreach($this->genOwnerRelationships() as $relationship)
		{
			$relationship->delete();
		}

		$tables = [];
		$class = get_called_class();

		while($class)
		{
			if($class::beforeDelete($this) === FALSE)
			{
				return FALSE;
			}

			$tables[] = $class::$table;

			$class = get_parent_class($class);
		}

		$tables = array_unique(array_filter($tables));

		$failed = false;

		foreach($tables as $table)
		{
			$delete = new \SeanMorris\Ids\Mysql\DeleteStatement($table);
			$delete->conditions([['id' => '?']]);
			if(!$delete->execute($this->id))
			{
				$failed = true;
			}
		}

		$class = get_called_class();

		while($class)
		{
			if($class::afterDelete($this) === FALSE)
			{
				return FALSE;
			}

			$class = get_parent_class($class);
		}

		return !$failed;
	}

	protected function storeSubmodel($column, $colVal)
	{
		if(isset(static::$hasOne[$column]))
		{
			$columnClass = static::$hasOne[$column];
		}
		else if(static::$hasMany[$column])
		{
			$columnClass = static::$hasMany[$column];
		}
		else
		{
			return;
		}

		\SeanMorris\Ids\Log::debug($columnClass, $colVal);

		if(is_subclass_of($columnClass, $colVal['class']))
		{
			$colVal['class'] = $columnClass;
		}

		if(isset($colVal['id'])
			&& isset($colVal['class'])
			&& $colVal['class']
			&& (
				is_subclass_of($colVal['class'], $columnClass)
				|| $colVal['class'] == $columnClass
			)
		){
			$columnClass = $colVal['class'];
		}
		else if(isset($colVal['class']) && $colVal['class'])
		{
			throw new \Exception(sprintf(
				'Bad classname supplied for column %s (%s).'
				, $column
				, isset($colVal['class'])
					? print_r($colVal['class'], true)
					: null
			));
		}

		if(is_array($colVal) && isset($colVal['id']) && $colVal['id'])
		{
			if($columnObject = $columnClass::loadOneById($colVal['id']))
			{
				$columnObject->consume($colVal);

				$columnObject->save();

				\SeanMorris\Ids\Log::debug($columnObject);

				$colVal = $colVal['id'];
			}
			else
			{
				$colVal = NULL;
			}
		}
		else if(is_array($colVal) && (!isset($colVal['id']) || !$colVal['id']))
		{
			$columnObject = new $columnClass;
			$columnObject->consume($colVal);

			\SeanMorris\Ids\Log::debug($columnObject);
			if($columnObject->save())
			{
				$colVal = $columnObject->id;
			}
			else
			{
				$colVal = NULL;
			}
		}

		return $colVal;
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
		\SeanMorris\Ids\Log::debug(sprintf(
			'Clearing cache for %s%s.'
			, get_called_class()
			, $descentants ? ' and descentants' : NULL
		));

		foreach(static::$cache as $class => &$cache)
		{
			if($descentants && is_subclass_of($class, get_called_class(), TRUE)
				|| $class === get_called_class()
			){
				$cache = [];
			}
		}

		foreach(static::$idCache as $class => &$cache)
		{
			if($descentants && is_subclass_of($class, get_called_class(), TRUE)
				|| $class === get_called_class()
			){
				$cache = [];
			}
		}

		foreach(static::$idCache as $class => &$cache)
		{
			if($descentants && is_subclass_of($class, get_called_class(), TRUE)
				|| $class === get_called_class()
			){
				$cache = [];
			}
		}
	}

	public static function __callStatic($name, $args = [])
	{
		if(preg_match('/^map([Bb]y.+)?$/', $name, $match))
		{
			$methodName = sprintf('getCursor%s', $match[1] ?? 'ByNull');

			$position = 0;
			$pageSize = 25;

			$_args = $args;

			if(!is_callable($_args[count($_args)-1]))
			{
				$position = array_pop($_args);
			}

			if(!is_callable($_args[count($_args)-1]))
			{
				$pageSize = array_pop($_args);
			}

			$callback = array_pop($_args);

			$_position = $position;

			do
			{
				$models = static::$methodName(...array_merge(
					$_args
					, [$_position, $pageSize]
				));

				foreach($models as $model)
				{
					if($callback($model) === 0)
					{
						break 2;
					}

					$_position = $model->id;
				}
			} while($models);

			return;
		}

		$hashableArgs = array_map(
			function($arg)
			{
				if(is_a($arg, get_class()) && $arg->id)
				{
					return $arg->id;
				}
				return $arg;
			}
			, $args
		);
		$curClass = get_called_class();
		$cacheKey = $curClass
			. '::'
			. $name
			. '--'
			. sha1(print_r($hashableArgs, 1));

		$classCache =& self::$cache[$curClass];

		if(!$classCache)
		{
			$classCache = [];
		}

		$cacheHit = FALSE;

		if(array_key_exists($cacheKey, $classCache))
		{
			$cacheHit = TRUE;
		}

		$backtrace = debug_backtrace();

		$trace = [];

		foreach($backtrace as $frame)
		{
			$trace[] = sprintf('%s:%d', $frame['file'] ?? '--', $frame['line'] ?? 0);
		}

		array_shift($backtrace);

		\SeanMorris\Ids\Log::debug(
			sprintf(
				'%s::%s(...)'
				, $curClass
				, $name
			)
			, $args
			, sprintf(
				'%s::%s(...)'
				. PHP_EOL
				. "\t" . "Called from\n\t\t%s."
				. PHP_EOL
				. "\t" . 'Cache "%s%s"'
				, $curClass
				, $name
				, implode(PHP_EOL . "\t\t", $trace)
				, $cacheKey
				, $cacheHit
					? PHP_EOL . "\t\t" . 'CACHE HIT!!!'
					: ''
			)
		);

		$cache   =& $classCache[$cacheKey];
		$idCache =& self::$idCache[$curClass];

		if(!$args)
		{
			$args = [];
		}

		$currentDefClass = get_called_class();

		while(TRUE)
		{
			$parentDefClass = get_parent_class($currentDefClass);

			if(!$parentDefClass)
			{
				break;
			}

			if($parentDefClass == $currentDefClass)
			{
				break;
			}

			$currentDefClass = $parentDefClass;
		}

		$def  = static::resolveDef($name, $args);
		$recs = FALSE;

		if(isset($def['recs']))
		{
			$recs = $def['recs'];
		}

		if(isset($def['cursor']) && $def['cursor'])
		{
			$limit = (int) array_pop($args);
		}

		$select = static::selectStatement($def, null, $args);

		if(isset($def['cursor']) && $def['cursor'])
		{
			$select->limit($limit);
			$select->conditions([['id' => '?', '>']]);
		}

		if(isset($def['paged']) && $def['paged'])
		{
			$limit = (int) array_pop($args);
			$offset = (int) array_pop($args) * $limit;

			$select->limit($limit, $offset);
		}

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

		if(isset($def['type']) && $def['type'] == 'generate')
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Generating %s', $curClass
			));

			return function(...$overArgs) use($select, $args, $rawArgs, $curClass, &$cache, &$idCache, $cacheHit, $def, $recs)
			{
				$gen = $select->generate();

				$overArgs = [];
				$i = 0;

				if($cacheHit
						&& isset($cache)
						&& array_key_exists($i, $cache)
				){
					while(array_key_exists($i, $cache))
					{
						\SeanMorris\Ids\Log::debug('From cache...');

						yield $cache[$i];
						$i++;
					}
				}
				else if(!$cacheHit)
				{
					$args = $overArgs + $args;

					foreach($gen(...$args) as $skeleton)
					{
						$subSkeleton = static::subSkeleton($skeleton);

						if(!$recs && static::beforeRead(NULL, $subSkeleton) === FALSE)
						{
							\SeanMorris\Ids\Log::debug('beforeRead Failed');

							$cache[$i] = FALSE;
							continue;
						}

						$model = static::instantiate($skeleton, $args, $rawArgs, $select);

						if(!$model)
						{
							\SeanMorris\Ids\Log::debug('Read Failed');

							$cache[$i] = FALSE;
							continue;
						}

						if(!$recs && static::afterRead($model, $subSkeleton) === FALSE)
						{
							\SeanMorris\Ids\Log::debug('afterRead Failed');

							$cache[$i] = FALSE;
							continue;
						}

						\SeanMorris\Ids\Log::debug('Loaded ', $i, $model);

						if(isset($idCache[$model->id]))
						{
							\SeanMorris\Ids\Log::debug('Already loaded...', $model);

							$model = $idCache[$model->id];
						}

						$cache[$i] = $idCache[$model->id] = $model;

						yield $model;

						$i++;
					}
				}
				if($cacheHit && isset($cache))
				{
					\SeanMorris\Ids\Log::debug(sprintf(
						'Empty cache hit on generate %s.'
						, get_called_class()
					));
				}
			};
		}

		if(isset($def['type']) && $def['type'] == 'loadOne')
		{
			$gen = $select->generate();

			\SeanMorris\Ids\Log::debug(sprintf(
				'Loading one %s', get_called_class()
			));

			if($name === 'loadOneById')
			{
				if(isset($idCache[$args[0]]))
				{
					$model = $idCache[$args[0]];

					\SeanMorris\Ids\Log::debug('Already loaded...', $model);

					return $model;
				}
			}

			if($cacheHit && isset($cache) && array_key_exists(0, $cache))
			{
				\SeanMorris\Ids\Log::debug('From cache...');

				return $cache[0];
			}

			foreach($gen(...$args) as $skeleton)
			{
				$subSkeleton = static::subSkeleton($skeleton);

				if(!$recs && static::beforeRead(NULL, $subSkeleton) === FALSE)
				{
					$cache[0] = FALSE;
					continue;
				}

				$model = static::instantiate($skeleton, $args, $rawArgs, $select);

				if(!$model)
				{
					\SeanMorris\Ids\Log::debug('Read Failed', $skeleton);
					$cache[0] = FALSE;
					continue;
				}

				if(!$recs && static::afterRead($model, $subSkeleton) === FALSE)
				{
					$cache[0] = FALSE;
					continue;
				}

				\SeanMorris\Ids\Log::debug('Loaded ', $model);

				if(isset($idCache[$model->id]))
				{
					\SeanMorris\Ids\Log::debug('Already loaded...', $model);

					$model = $idCache[$model->id];
				}

				$cache[0] = $idCache[$model->id] = $model;

				return $model;
			}

			$cache[0] = FALSE;

			return false;
		}

		if(isset($def['type']) && $def['type'] == 'get')
		{
			$gen = $select->generate();

			\SeanMorris\Ids\Log::debug(sprintf(
				'Getting %s', get_called_class()
			));

			if(isset($cache))
			{
				\SeanMorris\Ids\Log::debug('From cache...');

				return $cache;
			}

			$models = [];

			foreach($gen(...$args) as $skeleton)
			{
				$subSkeleton = static::subSkeleton($skeleton);

				if(!$recs && static::beforeRead(NULL, $subSkeleton) === FALSE)
				{
					continue;
				}

				$model = static::instantiate($skeleton, $args, $rawArgs, $select);

				if(!$model)
				{
					\SeanMorris\Ids\Log::debug('Read Failed', $skeleton);
					continue;
				}

				if(!$recs && static::afterRead($model, $subSkeleton) === FALSE)
				{
					continue;
				}

				$cache[count($models)] = $model;
				$idCache[$model->id] = $model;

				$models[] = $model;

				\SeanMorris\Ids\Log::debug('Got ', $model);
			}

			return $models;
		}

		if(isset($def['type']) && $def['type'] == 'count')
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Counting %s', get_called_class()
			));

			if(isset($cache) && array_key_exists(0, $cache))
			{
				\SeanMorris\Ids\Log::debug('From cache...');

				return $cache[0];
			}

			$countQuery = $select->countStatement('id');
			$countResult = $countQuery->execute(...$args);

			$count = (int) $countResult->fetchColumn();

			\SeanMorris\Ids\Log::debug(sprintf(
				'Caching count %s', get_called_class()
			));

			$cache[0] = $count;

			return $count;
		}

		if(isset($def['type']) && $def['type'] == 'load')
		{
			return function(...$overArgs) use($select, $args, $rawArgs, &$cache, &$idCache, $recs)
			{
				$gen = $select->generate();

				\SeanMorris\Ids\Log::debug(sprintf(
					'Loading %s', get_called_class()
				));

				if(isset($cache))
				{
					\SeanMorris\Ids\Log::debug('From cache...');

					return $cache;
				}

				$models = [];

				$args = $overArgs + $args;

				foreach($gen(...$args) as $skeleton)
				{
					$subSkeleton = static::subSkeleton($skeleton);

					if(!$recs && static::beforeRead(NULL, $subSkeleton) === FALSE)
					{
						continue;
					}

					$model = static::instantiate($skeleton, $args, $rawArgs, $select);

					if(!$model)
					{
						\SeanMorris\Ids\Log::debug('Read Failed', $skeleton);
					}

					if(!$recs && static::afterRead($model, $subSkeleton) === FALSE)
					{
						continue;
					}

					\SeanMorris\Ids\Log::debug('Loaded ', $model);

					$cache[count($models)] = $model;

					if(isset($idCache[$model->id]))
					{
						\SeanMorris\Ids\Log::debug('Already loaded...', $model);

						$model = $idCache[$model->id];
					}

					$idCache[$model->id] = $model;

					$models[] = $model;
				}

				return $models;
			};
		}

		throw new \Exception(sprintf(
			'%s::%s is not a valid method.'
			, get_called_class()
			, $name
		));
	}

	protected static function instantiate($skeleton, $args = [], $rawArgs = [], $select = NULL)
	{
		if(!isset($skeleton[static::$table]))
		{
			Log::warn('Cannot instantiate skeleton:', $skeleton);
			return;
		}

		$subSkeleton = current($skeleton[static::$table]);

		$class = null;

		if(isset($subSkeleton['class']))
		{
			$class = $subSkeleton['class'];
		}

		if($class == NULL
			|| !class_exists($class)
			|| (
				!is_subclass_of($class, get_called_class())
				&& !is_subclass_of(get_called_class(), $class)
			)
		){
			$class = get_called_class();
		}

		$instance = new $class();

		// $timelimit = ini_get("max_execution_time");

		// set_time_limit(30);

		$instance->consumeStatement($skeleton, $args, $rawArgs, $select);

		// set_time_limit($timelimit);

		self::$instances[get_called_class()][$instance->id] = $instance;

		return $instance;
	}

	public function storeRelationships($column, $newSubjects)
	{
		if(!$newSubjects)
		{
			$newSubjects = [];
		}

		\SeanMorris\Ids\Log::debug(sprintf(
			'Storing relatioships for %s->%s'
			, get_called_class()
			, $column
		));
		$deltas = [];
		$values = [];
		$newRelationships = [];
		$oldRelationships = [];

		$this->startTransaction();

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
				$subject   = $subjectId;
				$subjectId = $subjectId->id;
			}

			if(!isset($oldRelationships[$subjectId]) || !$oldRelationships[$subjectId])
			{
				$subjectClass = static::$hasMany[$column];

				if($subject = $subjectClass::loadOneById($subjectId))
				{
					if($subject::$table !== $subjectClass::$table)
					{
						continue;
					}

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
							, 'subjectClass'  => get_class($subject)
							, 'delta'         => $delta
						]
						, []
						, [$this, $column]
					);

					$relationship->delta = $delta;

					$newRelationships[] = $relationship;

					$relationship->save();
				}
			}
			else
			{
				$relationship = array_shift($oldRelationships[$subjectId]);

				$newRelationships[] = $relationship;

				if($relationship->delta !== $delta)
				{
					$relationship->delta = $delta;

					$relationship->save();
				}
			}
		}

		foreach($oldRelationships as $oldRels)
		{
			foreach($oldRels as $oldRel)
			{
				$oldRel->delete();
			}
		}

		$this->commitTransaction();
	}

	protected static function subskeletonWithAlias($skeleton)
	{
		if(!static::$table
			|| !isset($skeleton[static::$table])
			//&& is_array($skeleton[static::$table])
		){
			return [NULL, []];
		}

		reset($skeleton[static::$table]);

// 		Log::debug($skeleton);

		return [key($skeleton[static::$table]), array_shift($skeleton[static::$table])];
	}

	protected static function subskeletons($skeleton)
	{
		if(!static::$table
			|| !isset($skeleton[static::$table])
		){
			return [];
		}

		return $skeleton[static::$table];
	}

	protected static function subSkeleton($skeleton)
	{
		list(,$subSkeleton) = static::subskeletonWithAlias($skeleton);

		return $subSkeleton ?: [];
	}

	protected function consumeStatement($skeleton, $args = [], $rawArgs = [], $select = NULL)
	{
		$joinMap = [];

		if($select)
		{
			foreach($select->joins() as $join)
			{
				$joinMap[$join[1]] = $join[0]->tableAlias();
			}

			$subSkeletonAlias = $select->tableAlias();
			$subSkeleton      = $skeleton[$select->table()][$subSkeletonAlias];
		}
		else
		{
			list(
				$subSkeletonAlias
				, $subSkeleton
			) = static::subskeletonWithAlias($skeleton);
		}

		$parentClass = get_parent_class(get_called_class());

		while($parentClass)
		{
			$subSkeleton += $parentClass::subSkeleton($skeleton);

			$parentClass = get_parent_class($parentClass);
		}

		foreach($subSkeleton as $column => $value)
		{
			$this->$column = $value;

			if(
				is_string($this->$column)
				&& is_numeric($this->$column)
				&& ($this->$column[0] !== '0' || $this->$column === '0')
				&& $this->$column == (int) $this->$column
			){
				$this->$column = (int) $this->$column;
			}
		}

// 		\SeanMorris\Ids\Log::debug(get_called_class(), $skeleton);

		foreach($this as $property => $value)
		{
			if(isset(static::$hasOne[$property]))
			{
				$subjectClass = static::$hasOne[$property];

// 				\SeanMorris\Ids\Log::debug(
// 					$property
// 					, $subjectClass
// 					, $subjectClass::$table
// 				);

				if(isset($skeleton[$subjectClass::$table]))
				{
					\SeanMorris\Ids\Log::debug(sprintf(
						'Trying to preload %s for %s::%s'
						, $subjectClass
						, get_called_class()
						, $property
					));

					$model = $value;

					$subSkeletons = $subjectClass::subskeletons($skeleton);

					$_subSkeletonAlias = $subSkeletonAlias;
					$parentClass       = get_parent_class(get_called_class());

					if($parentClass
						&& isset($parentClass::$hasOne[$property])
						&& $_subjectClass = $parentClass::$hasOne[$property]
					){
						Log::debug(
							$parentClass
							, $_subjectClass::$table
							, $subjectClass::$table
						);

						if($_subjectClass::$table === $subjectClass::$table)
						{
							list($_subSkeletonAlias) = $parentClass::subskeletonWithAlias($skeleton);
						}
					}

					$subSkeletonKey = FALSE;

					if(isset($joinMap[$property]))
					{
					    $subSkeletonKey = $joinMap[$property];
					}

// 					$subSkeletonAliasChain = explode('__', $_subSkeletonAlias);

// 					$subSkeletonKey = array_pop($subSkeletonAliasChain)
// 						. '_'
// 						. $property
// 						. '__'
// 						. $subjectClass::$table
// 						. '_0'
// 					;

// 					\SeanMorris\Ids\Log::debug($_subSkeletonAlias, $subSkeletonKey, $subSkeletons);

					if(isset(
						$subSkeletons[$subSkeletonKey]
						, $subSkeletons[$subSkeletonKey]['id']
						, static::$idCache[$subjectClass][ $subSkeletons[$subSkeletonKey]['id'] ]
					)){
						$model = static::$idCache[$subjectClass][ $subSkeletons[$subSkeletonKey]['id'] ];

						\SeanMorris\Ids\Log::debug('Already loaded...', $model);
					}
					else if(
						isset( $subSkeletons[$subSkeletonKey] )
						&& $subSkeletons[$subSkeletonKey]
						&& array_filter($subSkeletons[$subSkeletonKey])
					){
						\SeanMorris\Ids\Log::debug(
							sprintf('Able to preload %s object', $subjectClass)
							, $subSkeletonKey
							, $subSkeletons
						);

						$subSkeletonClean = [];

						$subSkeletonClean[$subjectClass::$table][] = $skeleton[$subjectClass::$table][$subSkeletonKey];

						$skeleton[$subjectClass::$table][$subSkeletonKey];

						$model = $subjectClass::instantiate($subSkeletonClean);

						\SeanMorris\Ids\Log::debug('Preloaded', $model);

						static::$idCache[$subjectClass][ $subSkeletons[$subSkeletonKey]['id'] ] = $model;
					}

					$this->{$property} = $model;
				}
			}
		}
	}

	public static function getGenerator($name, $class = NULL)
	{
		if(!$class)
		{
			$class = get_called_class();
		}

		static $reflections = [];

		if(!isset($reflections[$class]))
		{
			$reflections[$class] = new \ReflectionClass($class);
		}

		$reflection = $reflections[$class];

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
		static $reflections = [];

		$type = NULL;
		$flat = $subs = $recs = $cursor = $paged = FALSE;

		$originalName = $name;

		if(preg_match(
			'/^(?:(loadOne|load|generate|get|count)?)
				((?:Flat)?)
				((?:Submodel|Record)?s?)
				((?:Page|Cursor)?)
				([Bb]y.+)/x'
			, $originalName
			, $match)
		){
			if(isset($match[1]))
			{
				$type = lcfirst($match[1]);
			}

			if(isset($match[2]) && $match[2])
			{
				$flat = TRUE;
			}

			if(isset($match[3]) && $match[3])
			{
				if(strtolower($match[3]) == 'submodel' || strtolower($match[3]) == 'submodels')
				{
					$subs = TRUE;
				}
				if(strtolower($match[3]) == 'record' || strtolower($match[3]) == 'records')
				{
					$recs = TRUE;
				}
			}

			if(isset($match[4]) && $match[4] == 'Page')
			{
				$paged = TRUE;
			}

			if(isset($match[4]) && $match[4] == 'Cursor')
			{
				$cursor = TRUE;
			}

			if(isset($match[5]))
			{
				$name = lcfirst($match[5]);
			}
		}
		else if(preg_match(
			'/^(?:(loadOne|load|generate|get|count)?)
				((?:Flat)?)
				((?:Submodel|Record)?s?)$/x'
			, $originalName
			, $matchB)
		){
			if(isset($matchB[1]))
			{
				$type = lcfirst($matchB[1]);
			}

			if(isset($matchB[2]) && $matchB[2])
			{
				$flat = TRUE;
			}

			if(isset($matchB[3]) && $matchB[3])
			{
				if(strtolower($matchB[3]) == 'submodel' || strtolower($matchB[3]) == 'submodels')
				{
					$subs = TRUE;
				}
				if(strtolower($matchB[3]) == 'record' || strtolower($matchB[3]) == 'records')
				{
					$recs = TRUE;
				}
			}

			$paged  = FALSE;
			$cursor = FALSE;
			$name   = NULL;
		}

		$def = [
			'name'        => $name
			, 'wholeName' => $originalName
			, 'type'      => $type
			, 'paged'     => $paged
			, 'cursor'    => $cursor
			, 'subs'      => $subs
			, 'flat'      => $flat
			, 'recs'      => $recs
		];

		$class = get_called_class();

		while($class)
		{
			if(!property_exists($class, $name))
			{
				$class = get_parent_class($class);
				continue;
			}

			try
			{
				$key = $class . '::' . $name;

				if(!isset($reflections[$key]))
				{
					$reflections[$key] = new \ReflectionProperty($class, $name);
				}

				$property = $reflections[$key];
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

				if($flat && isset($def['with']))
				{
					unset($def['with']);
				}

				$def['name']       = $name;
				$def['wholeName']  = $originalName;
				$def['type']       = $type;
				$def['paged']      = $paged;
				$def['cursor']     = $cursor;
				$def['class']      = $class;
				$def['subs']       = $subs;
				$def['flat']       = $flat;
				$def['recs']       = $recs;
				break;
			}

			$parentClass = get_parent_class($class);

			if($parentClass::$table && $parentClass::$table !== $class::$table)
			{
				break;
			}

			$class = $parentClass;
		}

		return $def;
	}

	public static function hasSelector($name)
	{
		$name = lcfirst($name);

		if(isset(static::$$name) || $name == 'byNull')
		{
			return TRUE;
		}

		return FALSE;
	}

	protected static function selectStatement($selectDefName, $superior = null, $args = [], $table = NULL, $topClass = NULL, $flat = FALSE)
	{
		if(!$topClass)
		{
			$topClass = get_called_class();
		}

		$table = !empty(static::$table) ? static::$table : $table;

		$select = new \SeanMorris\Ids\Mysql\SelectStatement($table);

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

		$selectDef = !is_array($selectDefName)
			? $called::resolveDef($selectDefName, $args, $superior)
			: $selectDefName;

		if(isset($selectDef['flat']) && $selectDef['flat'])
		{
			$flat = $selectDef['flat'];
		}
		else
		{
			$selectDef['flat'] = $flat;
		}

		$where = [];
		$order = [];
		$index = [];

		if(isset($selectDef['where']))
		{
			$where = $selectDef['where'];
		}

		if(isset($selectDef['recs'])
			&& $selectDef['recs']
			&& !$superior
			&& (!isset($selectDef['name']) || !$selectDef['name'])
		){
			$where = [['id' => '?']];
		}

		if(isset($selectDef['order']))
		{
			$order = $selectDef['order'];
		}

		if(isset($selectDef['index']))
		{
			$index = $selectDef['index'];
		}

		// \SeanMorris\Ids\Log::debug($selectDef);

		$select->columns(...$columns)
			->wrappers($wrappers)
			->order($order)
			->index($index)
			->conditions($where)
		;

		if(!$superior)
		{
			$select->group('id');
		}

		if(!$selectDef['flat'] && isset($selectDef['join']) && is_array($selectDef['join']))
		{
			foreach($selectDef['join'] as $joinClass => $join)
			{
				$defName = 'loadBy'.ucwords($join['by']);
				$subSelect = $joinClass::selectStatement($defName, $select, $args, $table, $joinClass);

				$select->join($subSelect, $join['on'], 'id');
			}
		}

		if(!$selectDef['flat'] && isset($selectDef['with']) && is_array($selectDef['with']))
		{
			foreach($selectDef['with'] as $childProperty => $joinBy)
			{
				if(is_numeric($childProperty))
				{
					$childProperty = $joinBy;
					$joinBy        = 'byNull';
				}

				if(isset(static::$hasOne[$childProperty]))
				{
					$joinClass = $topClass::$hasOne[$childProperty];

					$defName   = 'load'.ucwords($joinBy);
					$subSelect = $joinClass::selectStatement($defName, $select, $args, $table, $joinClass);

					$select->join(
						$subSelect
						, $childProperty
						, 'id'
						, 'LEFT'
					);
				}
				else if(isset(static::$hasMany[$childProperty]))
				{
					$relationshipClass = '\SeanMorris\Ids\Relationship';

					if(static::$relationshipClass)
					{
						$relationshipClass = static::$relationshipClass;
					}

					$joinClass = static::$hasMany[$childProperty];
					$defName   = 'loadByOwner';

					array_unshift($args, $childProperty);
					array_unshift($args, $joinBy);
					array_unshift($args, get_called_class());

					$subSelect = $relationshipClass::selectStatement($defName, $select, $args, $table);

					$select->join(
						$subSelect
						, 'id'
						, 'ownerId'
					);
				}
				else
				{
					throw new \Exception(sprintf(
						'Invalid property %s for %s::$selectDef["with"]'
						, $childProperty
						, get_called_class()
					));
				}
			}
		}
		else if(!$selectDef['flat'] && isset($selectDef['with']) && !is_array($selectDef['with']))
		{
			\SeanMorris\Ids\Log::warn(sprintf(
				'Invalid value for %s::$selectDef["with"]'
				, get_called_class()
			));
		}

		$curClass = get_called_class();
		$parentClass = get_parent_class($curClass);

		while($parentClass && $parentClass::$table == $curClass::$table)
		{
			$parentClass = get_parent_class($parentClass);
		}

		if($parentClass && $parentClass::$table)
		{
			$selectDefName = is_array($selectDefName)
				? ($selectDefName['name'] ?? $selectDefName)
				: $selectDefName;

			$subSelect = $parentClass::selectStatement($selectDefName, $select, $args, $table, $topClass, $selectDef['flat']);

			$select->subjugate($subSelect);
			$select->join($subSelect, 'id', 'id');
		}
		else if(!in_array('class', static::$ignore))
		{
			$rootPackage = \SeanMorris\Ids\Package::getRoot();
			$allClasses  =  $roles = \SeanMorris\Ids\Linker::classes();

			$subClasses  = $allClasses->{$topClass} ?? [];

			$subClasses[] = $topClass;

			$subClasses = array_unique($subClasses);

			if($selectDef['subs'] && !$selectDef['recs'])
			{
				if(count($subClasses) == 1)
				{
					$select->conditions([[
						'class' => sprintf('"%s"',
							addslashes(current($subClasses))
						)
					]]);
				}
				else
				{
					$classesString = sprintf(
						'("%s")'
						, implode('", "', array_map('addslashes', $subClasses))
					);

					$select->conditions([[
						'class' => $classesString, 'IN'
					]]);
				}
			}
			else if(!$selectDef['recs'])
			{
				$subClasses = array_filter(
					$subClasses
					, function($subClass)
					{
						return $subClass::$table == static::$table;
					}
				);

				$subClasses[] = $topClass;

				$subClasses = array_unique($subClasses);

				if(count($subClasses) == 1)
				{
					$select->conditions([[
						'class' => sprintf('"%s"',
							addslashes(current($subClasses))
						)
					]]);
				}
				else
				{
					$classesString = sprintf(
						'("%s")'
						, implode('","', array_map('addslashes', $subClasses))
					);

					$select->conditions([[
						'class' => $classesString, 'IN'
					]]);
				}
			}
		}

		return $select;
	}

	public static function getProperties($all = FALSE)
	{
		$class = get_called_class();

		static $_propertyCache = [], $_reflectionCache = [];

		$cacheKey = $class . '::' . (int) $all;

		if(isset($_propertyCache[$cacheKey]))
		{
			return $_propertyCache[$cacheKey];
		}

		$result = [];

		while($class)
		{
			if(!isset($_reflectionCache[$class]))
			{
				$reflection = new \ReflectionClass($class);

				$_reflectionCache[$class] = $reflection->getProperties();
			}

			$proprties = $_reflectionCache[$class];

			foreach($proprties as $property)
			{
				if($property->isStatic() || $property->isPrivate())
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

		$_propertyCache[$cacheKey] = $result;

		return $result;
	}

	public static function getTable()
	{
		return static::$table;
	}

	protected static function getColumns($type = null, $all = true)
	{
		$calledClass = $curClass = get_called_class();

		static $_columnCache = [];

		$cacheKey = $calledClass
			. '::'
			. (string) $type
			. '::'
			. ((int) $all)
			. ':::';

		if(isset($_columnCache[$cacheKey]))
		{
			return $_columnCache[$cacheKey];
		}

		if(!isset(static::$columnMeta[$curClass]))
		{
			static::$columnMeta[$curClass] = $curClass::getProperties();
		}

		$properties = static::$columnMeta[$curClass];//$curClass::getProperties();

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
		}

		foreach($properties as $property)
		{
			if($property[0] === '_')
			{
				continue;
			}

			$reflectionProperty = new \ReflectionProperty($curClass, $property);

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

		return $_columnCache[$cacheKey] = $columns;
	}

	public function __get($name)
	{
		if(!isset($this->$name))
		{
			return;
		}

		return $this->$name;
	}

	public function __set($name, $value)
	{
		if(!isset($this->$name))
		{
			return;
		}

		$this->$name = $value;
	}

	public function consume($skeleton, $override = false)
	{
		\SeanMorris\Ids\Log::debug(sprintf(
				'Consuming skeleton for model of type %s'
				, get_called_class()
			), $skeleton
		);

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
				if($this->{$property} !== $skeleton[$property])
				{
					$this->_changed[$property] = true;
					$this->_unconsumed = [];
				}
				$this->{$property} = $skeleton[$property];
			}
		}

		foreach(static::$hasOne as $property => $class)
		{
			if(!isset($skeleton[$property]))
			{
				continue;
			}

			$values = $skeleton[$property];

			$propertyClass = $this::getSubjectClass($property);

			\SeanMorris\Ids\Log::debug(sprintf(
				'Consuming subModel of type %s for column %s'
				, $propertyClass
				, $property
			), $values);

			if(is_object($values) && $values->id && is_subclass_of($values, $propertyClass))
			{
				\SeanMorris\Ids\Log::debug('Using existing model');

				$this->{$property} = $values->id;
			}
			else if(is_array($values))
			{
				if(isset($values['id']) && $values['id'])
				{
					\SeanMorris\Ids\Log::debug('Using existing model');

					if(isset($values['class']) && $values['class']
						&& is_subclass_of($values['class'], $propertyClass)
					){
						$propertyClass = $values['class'];
					}

					$subject = $propertyClass::loadOneById($values['id']);

					if(!$subject)
					{
						continue;
					}

					$subject->consume($values);

					if($subject->save())
					{
						$this->{$property} = $subject->id;
					}
				}
				else if($values)
				{
					 if(!isset($values['class']) || !$values['class'])
					 {
					 	$values['class'] = $propertyClass;
					 }

					\SeanMorris\Ids\Log::debug(
						'Trying to create new model'
						, $values['class']
						, $propertyClass
					);

					if(is_a($values['class'], $propertyClass, TRUE))
					{
						\SeanMorris\Ids\Log::debug(
							'Creating new model'
							, $values['class']
							, $propertyClass
						);

						$subject = new $values['class'];

						$subject->consume($values);

						try
						{
							if($subject->save())
							{
								$this->{$property} = $subject->id;
							}
						}
						catch(\SeanMorris\PressKit\Exception\ModelAccessException $exception)
						{
							\SeanMorris\Ids\Log::logException($exception);
						}
					}
				}
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

					if(is_object($values) && isset($values->id))
					{
						$values = [
							'class' => get_class($values)
							, 'id'  => $values->id
						];
					}
					elseif($values instanceof $class)
					{
						if($values->save())
						{
							$this->{$property}[$delta] = $values->id;

							$subModelsSubmitted = TRUE;
						}
					}
					else if(is_array($values) && isset($values['id']) && $values['id'])
					{
						if(isset($values['class']) && $values['class']
							&& is_subclass_of($values['class'], $propertyClass)
						){
							$propertyClass = $values['class'];
						}

						$subject = $propertyClass::loadOneById($values['id']);

						if(!$subject)
						{
							continue;
						}

						$subject->consume($values);

						try
						{
							if($subject->save())
							{
								$this->{$property}[$delta] = $subject->id;
							}
						}
						catch(\SeanMorris\PressKit\Exception\ModelAccessException $exception)
						{
							\SeanMorris\Ids\Log::logException($exception);
						}

						$subModelsSubmitted = TRUE;
					}
					elseif(is_array($values))
					{
						if(!isset($values['class']) || !$values['class'])
						 {
						 	$values['class'] = $propertyClass;
						 }

						\SeanMorris\Ids\Log::debug(
							'Trying to create new model'
							, $values['class']
							, $propertyClass
						);

						if(is_a($values['class'], $propertyClass, TRUE))
						{
							\SeanMorris\Ids\Log::debug(
								'Creating new model'
								, $values['class']
								, $propertyClass
							);

							$subject = new $values['class'];

							$subject->consume($values);

							try
							{
								if($subject->save())
								{
									$this->{$property}[$delta] = $subject->id;
								}
							}
							catch(\SeanMorris\PressKit\Exception\ModelAccessException $exception)
							{
								\SeanMorris\Ids\Log::logException($exception);
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

		static::afterConsume($this, $skeleton);
	}

	public function unconsume($children = 0)
	{
		if(isset($this->_unconsumed[$children]))
		{
			return $this->_unconsumed[$children];
		}

		$class = get_called_class();

		static $_propertyCache = [];

		$cacheKey = $class;

		if(!isset($_propertyCache[$cacheKey]))
		{
			$_propertyCache[$cacheKey] = static::getProperties(TRUE);
		}

		$proprties = $_propertyCache[$cacheKey];

		$skeleton = [];

		foreach($proprties as $property)
		{
			if($this->$property instanceof Model)
			{
				$skeleton[$property] = $this->$property->id;

				if($children && $children < 0)
				{
					$skeleton[$property] = $this->$property->unconsume(FALSE);
				}

				continue;
			}

			if(is_array($this->$property))
			{
				if($this->canHaveOne($property) && !$children)
				{
					$skeleton[$property] = $this->$property['id'] ?? NULL;

					continue;
				}

				if(!$this->_stub && (!$children || $children < 0))
				{
					continue;
				}

				$loadedChildren = $this->$property;

				$skeletons = [];

				foreach($loadedChildren as $index => $child)
				{
					if($child instanceof Model)
					{
						$skeletons[$index] = $child->unconsume(FALSE);
					}
					else
					{
						$skeletons[$index] = $child;
					}
				}

				$skeleton[$property] = $skeletons;

				continue;
			}

			$skeleton[$property] = $this->$property;
		}

		if($children && $children > 0)
		{
			foreach(static::$hasMany as $property => $class)
			{
				$subjects = $this->getSubjects($property);

				\SeanMorris\Ids\Log::debug($property, $subjects);

				$skeleton[$property] = array_map(
					function($subject) use($children)
					{
						return $subject->unconsume($children - 1);
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

		return $this->_unconsumed[$children] = $skeleton;
	}

	public function addSubject($property, $subject)
	{
		\SeanMorris\Ids\Log::debug(sprintf(
			'Trying to add %s to %s.'
			, get_class($subject)
			, $property
		));

		if($subject->onSubjugate($this, $property) === FALSE)
		{
			return;
		}

		$subjectClass = get_class($subject);

		if($subjectClass
			&& (
				$subjectClass == $this->canHaveMany($property)
				|| is_subclass_of($subjectClass, $this->canHaveMany($property))
			)
		){
			if(!$this->{$property})
			{
				$this->{$property} = $this->getSubjects($property, TRUE);
			}

			foreach($this->{$property} as $existingSubject)
			{
				if($existingSubject && $subject ->id == (is_object($existingSubject)
						? $existingSubject->id
						: $existingSubject
				)
					&& get_class($existingSubject) == get_class($subject)
				){
					return;
				}
			}

			\SeanMorris\Ids\Log::debug(
				'Adding to ' . $property
				, $subject
			);

			$this->{$property}[] = $subject;

			$this->_changed[$property] = TRUE;
			$this->_unconsumed = [];

			return TRUE;
		}

		if($subjectClass
			&& (
				$subjectClass == $this->canHaveOne($property)
				|| is_subclass_of($subjectClass, $this->canHaveOne($property))
			)
		){
			\SeanMorris\Ids\Log::debug(
				'Adding to ' . $property
				, $subject
			);

			$this->{$property} = $subject->id;

			return TRUE;
		}

		return FALSE;
	}

	public function getSubject($column = null)
	{
		if(!$this->$column || is_object($this->$column))
		{
			return $this->$column;
		}

		if(!isset(static::$hasOne[$column]))
		{
			return false;
		}

		$class = static::$hasOne[$column];

		if($loaded = $class::loadOneById($this->{$column}))
		{
			$this->$column = $loaded;
		}

		return $loaded;
	}

	public static function getSubjectClass($column)
	{
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

	public static function canHaveOne($property)
	{
		return isset(static::$hasOne[$property])
			? static::$hasOne[$property]
			: FALSE;
	}

	public static function canHaveMany($property)
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
				$this->{$column}[$subjectRelationship->delta] = $subjectRelationship->subject();

				yield $this->{$column}[$subjectRelationship->delta];
			}
		}
	}

	public function getSubjects($column)
	{
		return $this->_getSubjects($column);
	}

	protected function _getSubjects($column)
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

	public function genOwnerRelationships()
	{
		$relationshipClass = '\SeanMorris\Ids\Relationship';

		if(static::$relationshipClass)
		{
			$relationshipClass = static::$relationshipClass;
		}

		$gen = $relationshipClass::generateBySubject($this);

		foreach($gen() as $ownerRelationship)
		{
			if($ownerRelationship)
			{
				yield $ownerRelationship;
			}
		}
	}

	public function genOwners()
	{
		foreach($this->genOwnerRelationships() as $ownerRelationship)
		{
			if($ownerRelationship)
			{
				yield $ownerRelationship->owner();
			}
		}
	}

	public function getOwners()
	{
		$owners = [];

		foreach($this->genOwners() as $owner)
		{
			if(!$owner)
			{
				continue;
			}

			$owners[] = $owner;
		}

		return $owners;
	}

	public function changed($property = NULL)
	{
		if($property === NULL)
		{
			return $this->_changed;
		}

		return $this->changed[$property] ?? FALSE;
	}

	public static function table()
	{
		return static::$table;
	}

	protected static function startTransaction()
	{
		$database = \SeanMorris\Ids\Database::get('main');
		$database->prepare('START TRANSACTION')->execute();
	}

	protected static function commitTransaction()
	{
		$database = \SeanMorris\Ids\Database::get('main');
		$database->prepare('COMMIT')->execute();
	}

	protected static function rollbackTransaction()
	{
		$database = \SeanMorris\Ids\Database::get('main');
		$database->prepare('ROLLBACK')->execute();
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

	protected function onSubjugate($parent, $property)
	{

	}
}
