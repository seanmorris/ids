<?php
namespace SeanMorris\Ids;
class Package
{
	protected
		$folder
		, $packageName
	;

	protected static
		$assetManager
		, $tables = []
	;

	public static function get($packageName = NULL)
	{
		if(!$packageName)
		{
			$packageName = preg_replace(
				'/\\\(?:\w+$)/'
				, ''
				, get_called_class()
			);
		}

		$packageName = static::name($packageName);
		$packageClass = $packageName . '\\Package';

		if(class_exists($packageClass))
		{
			return new $packageClass($packageName);
		}

		$package = new static($packageName);

		return $package;
	}

	protected function __construct($package)
	{
		if(class_exists($package . '\Package'))
		{
			$reflection = new \ReflectionClass($package . '\Package');
			$classFile = $reflection->getFileName();
			$this->folder = dirname(dirname($classFile)) . '/';
		}
		else
		{
			throw new \Exception('No IDS Package defined for ' . $package);
		}
	}

	public static function listPackages(\Composer\Autoload\ClassLoader $composer)
	{
		$directories = array_merge(...array_values($composer->getPrefixes()));

		$packages = [];

		foreach($directories as $directory)
		{
			$dirHandle = opendir($directory);

			while($vendorDir = readdir($dirHandle))
			{
				if($vendorDir == '.' || $vendorDir == '..')
				{
					continue;
				}

				if(!is_dir($directory . '/' . $vendorDir))
				{
					continue;
				}

				$vendorDirHandle = opendir($directory . '/' . $vendorDir);

				while($packageDir = readdir($vendorDirHandle))
				{
					if($packageDir == '.' || $packageDir == '..')
					{
						continue;
					}

					$packagePath = $directory . '/' . $vendorDir . '/' . $packageDir;
					
					$packages[] = $vendorDir . '/' . $packageDir;
				}
			}
		}

		sort($packages);

		return $packages;
	}

	public static function name($package)
	{
		return str_replace('/', '\\', $package);
	}

	public function packageDir()
	{
		return $this->folder;

		return false;
	}

	public function packageSpace()
	{
		return $this->packageName;
	}

	public function dirSpace()
	{
		return $this->folder;
	}

	public function assetDir()
	{
		// Todo: Use site settings to locate asset directory
		return $this->folder . 'asset/';
	}

	public function publicDir()
	{
		// Todo: Use site settings to locate public directory
		return $this->folder . 'public';;
	}

	public function globalDir()
	{
		// Todo: Use site settings to locate global directory
		return $this->packageDir() . 'data/global/';
	}

	public function localDir()
	{
		// Todo: Use site settings to locate local directory
		return $this->packageDir() . 'data/local/';
	}

	public function assetManager()
	{
		$assetManager = static::$assetManager;
		
		if(!static::$assetManager)
		{
			$assetManager = $this->packageSpace() . '\Suffix\AssetManager';
		}		

		if(class_exists($assetManager))
		{
			return new $assetManager;
		}
	}

	public function setVar($var, $val, $type = 'local')
	{
		$varPath = preg_split('/(?<!\\\\)\:/', $var);

		if($type == 'local')
		{
			$dir = $this->localDir();
		}
		else
		{
			$dir = $this->globalDir();
		}

		if(!file_exists($dir))
		{
			mkdir($dir, 0777, true);
		}

		$path = $dir . 'var.json';

		if(!file_exists($dir))
		{
			mkdir($dir, 0777, true);
		}

		if(!file_exists($path))
		{
			file_put_contents($path, '{}');
		}

		$varsJson = file_get_contents($path);

		if($vars = json_decode($varsJson))
		{
			$currentVar =& $vars;

			while($varName = array_shift($varPath))
			{
				if(is_scalar($currentVar))
				{
					$currentVar = (object)[];
				}
				
				$currentVar =& $currentVar->$varName;	
			}

			$currentVar = $val;
		}
		else
		{
			print "Invalid JSON in " . $path;
			return;
		}

		file_put_contents($path, json_encode($vars, JSON_PRETTY_PRINT));
	}

	public function getVar($var, $val = NULL, $type = 'local')
	{
		$varPath = preg_split('/(?<!\\\\)\:/', $var);

		if($type == 'local')
		{
			$dir = $this->localDir();
		}
		else
		{
			$dir = $this->globalDir();
		}

		$path = $dir . 'var.json';

		if(!file_exists($dir) || !file_exists($path))
		{
			return $val;
		}

		$varsJson = file_get_contents($path);

		if(($vars = json_decode($varsJson)))
		{
			$currentVar =& $vars;

			while($varName = array_shift($varPath))
			{
				$currentVar =& $currentVar->$varName;	
			}

			return $currentVar;
		}

		return $val;
	}

	public function deleteVar($var, $type = 'local')
	{
		$varPath = preg_split('/(?<!\\\\)\:/', $var);

		if($type == 'local')
		{
			$dir = $this->localDir();
		}
		else
		{
			$dir = $this->globalDir();
		}

		$path = $dir . 'var.json';

		if(!file_exists($dir) || !file_exists($path))
		{
			return;
		}

		$varsJson = file_get_contents($path);

		if(($vars = json_decode($varsJson)))
		{
			$currentVar =& $vars;

			while($varName = array_shift($varPath))
			{
				if(!count($varPath))
				{
					unset($currentVar->$varName);
					break;
				}

				$currentVar =& $currentVar->$varName;
			}
		}

		file_put_contents($path, json_encode($vars));
	}

	protected function getStoredSchema()
	{
		$schemaFilename = $this->globalDir() . 'schema.json';

		if(!file_exists($schemaFilename))
		{
			return;
		}

		$schema = json_decode(file_get_contents($schemaFilename));

		if(!$schema)
		{
			$schema = new \StdClass;
		}

		if(!isset($schema->revisions))
		{
			$schema->revisions = [];
		}

		return $schema;
	}

	public function getFullSchema($revision = null)
	{
		$schema = $this->getStoredSchema();

		if(!$schema)
		{
			return;
		}

		$fullSchema = (object)[];

		$objectMerge = function($obj1, $obj2) use(&$objectMerge)
		{
			$obj1 = clone $obj1;
			$obj2 = clone $obj2;

			foreach($obj2 as $prop => $val)
			{
				if(isset($obj1->$prop)
					&& is_object($obj1->$prop)
					&& is_object($obj2->$prop)
				){
					$obj1->$prop = $objectMerge($obj1->$prop, $obj2->$prop);
				}
				else
				{
					$obj1->$prop = $obj2->$prop;
				}
			}

			return $obj1;
		};

		foreach($schema->revisions as $index => $revision)
		{
			$fullSchema = $objectMerge($revision, $fullSchema);	
		}

		return $fullSchema;
	}

	public function storeSchema()
	{
		$schema = $this->getStoredSchema();
		$changes = $this->getSchemaChanges();

		$revisionCount = count((array)$schema->revisions);
		$changeCount = count((array)$changes);

		if(!$schema->revisions)
		{
			$schema->revisions = new \StdClass;
		}

		// var_dump($schema, $changes);

		if($changeCount)
		{
			$schema->revisions->{$revisionCount} = $changes;

			$schemaFilename = $this->globalDir() . 'schema.json';

			$schema->revisions = (object)$schema->revisions;

			file_put_contents($schemaFilename, json_encode(
				$schema, JSON_PRETTY_PRINT
			));
		}
	}

	public function getSchemaChanges()
	{
		$storedSchema = $this->getFullSchema();
		$changes = (object)[];

		foreach(static::$tables as $db => $tables)
		{
			$db = Database::get($db);
			foreach($tables as $table)		
			{
				if(!isset($storedSchema->$table))
				{
					$storedSchema->$table = new \StdClass;
				}

				if(!isset($storedSchema->$table->fields))
				{
					$storedSchema->$table->fields = new \StdClass;
				}

				if(!isset($storedSchema->$table->keys))
				{
					$storedSchema->$table->keys = new \StdClass;
				}

				$query = $db->prepare('SHOW FULL COLUMNS FROM ' . $table);
				$query->execute();

				while($column = $query->fetchObject())
				{
					unset($column->Privileges);

					if(isset($storedSchema->$table->fields->{$column->Field})
					 && $storedSchema->$table->fields->{$column->Field} == $column
					){
						continue;
					}

					if(!isset($changes->$table))
					{
						$changes->$table = new \StdClass;
					}

					if(!isset($changes->$table->fields))
					{
						$changes->$table->fields = new \StdClass;
					}

					$changes->$table->fields->{$column->Field} = $column;
				}

				$query = $db->prepare('SHOW INDEXES FROM ' . $table);
				$query->execute();

				while($index = $query->fetchObject())
				{
					unset($index->Cardinality);

					if(isset($storedSchema->$table->keys->{$index->Key_name}))
					{
						$_arKey = (array)$storedSchema->$table->keys->{$index->Key_name};
						$arKey = [];

						foreach($_arKey as $key=>$val)
						{
							$arKey[(int)$key] = $val;
						}

						unset($arKey[$index->Seq_in_index]->Cardinality);

						if(isset($arKey[$index->Seq_in_index]) && $index == $arKey[$index->Seq_in_index])
						{
							continue;
						}
					}

					if(isset($storedSchema->$table->keys->{$index->Key_name})
					 && isset($storedSchema->$table->keys->{$index->Key_name}->{$index->Seq_in_index})
					 && $storedSchema->$table->keys->{$index->Key_name}->{$index->Seq_in_index} == $index
					){
						continue;
					}

					if(!isset($changes->$table))
					{
						$changes->$table = new \StdClass;
					}

					if(!isset($changes->$table->keys))
					{
						$changes->$table->keys = new \StdClass;
					}

					if(!isset($changes->$table->keys->{$index->Key_name}))
					{
						$changes->$table->keys->{$index->Key_name} = new \StdClass;
					}

					$changes->$table->keys->{$index->Key_name}->{$index->Seq_in_index} = $index;
				}
			}
		}

		return $changes;
	}

	public function applySchema($real = false)
	{
		$exportTables = $this->getFullSchema();
		$queries = [];

		foreach(static::$tables as $db => $tables)
		{
			$db = Database::get($db);
			
			foreach($tables as $table)
			{
				$query = $db->prepare('SHOW FULL COLUMNS FROM ' . $table);
				$query->execute();

				$tableFound = false;

				while($column = $query->fetchObject())
				{
					if(!isset($exportTables->$table->fields->{$column->Field}))
					{
						$queries[] = sprintf(
							"ALTER TABLE `%s` DROP COLUMN `%s`;"
							, $table
							, $column->Field
						);

						continue;
					}

					$tableFound = true;

					unset($column->Privileges);

					if($column == $exportTables->$table->fields->{$column->Field})
					{
						unset($exportTables->$table->fields->{$column->Field});
						continue;
					}

					$queries[] = sprintf(
						"ALTER TABLE %s MODIFY %s %s %s %s %s COMMENT '%s'"
						, $table
						, $exportTables->$table->fields->{$column->Field}->Field
						, $exportTables->$table->fields->{$column->Field}->Type
						, $exportTables->$table->fields->{$column->Field}->Null == 'YES'
							? 'NULL'
							: 'NOT NULL'
						, $exportTables->$table->fields->{$column->Field}->Extra == 'auto_increment'
							? 'auto_increment'
							: NULL
						, $exportTables->$table->fields->{$column->Field}->Collation
							? 'COLLATE ' . $exportTables[$table]['field'][$column->Field]->Collation
							: NULL
						, $exportTables->$table->fields->{$column->Field}->Comment
					);

					unset($exportTables->$table->fields->{$column->Field});
				}

				$query = $db->prepare('SHOW INDEXES FROM ' . $table);
				$query->execute();

				while($index = $query->fetchObject())
				{
					if(!isset($exportTables->$table->keys->{$index->Key_name}))
					{
						continue;
					}

					$_arKey = (array)$exportTables->$table->keys->{$index->Key_name};

					$arKey = [];

					foreach($_arKey as $key=>$val)
					{
						$arKey[(int)$key] = $val;
					}

					unset($index->Cardinality, $arKey[$index->Seq_in_index]->Cardinality);

					if(!isset($arKey[$index->Seq_in_index]))
					{
						continue;
					}

					if($index == $arKey[$index->Seq_in_index])
					{
						unset($exportTables->$table->keys->{$index->Key_name});
						continue;
					}

					$columns = static::latestColumns($exportTables->$table->keys->{$index->Key_name});

					if($index->Key_name == 'PRIMARY')
					{
						$queries[] = sprintf(
							"ALTER TABLE `%s` DROP PRIMARY KEY;"
							, $table
						);
						$queries[] = sprintf(
							"ALTER TABLE `%s` ADD PRIMARY KEY (`%s`) COMMENT '%s';"
							, $table
							, $columns
							, $arKey[$index->Seq_in_index]->Index_comment
						);
					}
					elseif($index->Non_unique == 0)
					{
						$queries[] = sprintf(
							"ALTER TABLE `%s` DROP KEY %s;"
							, $table
							, $index->Key_name
						);
						$queries[] = sprintf(
							"ALTER TABLE `%s` ADD UNIQUE KEY `%s` (`%s`) COMMENT '%s';"
							, $table
							, $index->Key_name
							, $columns
							, $arKey[$index->Seq_in_index]->Index_comment
						);	
					}
					else
					{
						$queries[] = sprintf(
							"ALTER TABLE `%s` DROP INDEX;"
							, $table
						);
						$queries[] = sprintf(
							"ALTER TABLE `%s` ADD INDEX `%s` (`%s`) COMMENT '%s';"
							, $table
							, $index->Key_name
							, $columns
							, $arKey[$index->Seq_in_index]->Index_comment
						);	
					}

					unset($exportTables->$table->keys->{$index->Key_name});
				}

				if(!$tableFound)
				{
					$createColumn = [];

					if(!isset($exportTables->$table)
						|| !isset($exportTables->$table->fields)
					){
						continue;
					}

					foreach($exportTables->$table->fields as $field)
					{
						$createColumn[] = sprintf(
							"\t`%s` %s %s %s %s COMMENT '%s'"
							, $exportTables->$table->fields->{$field->Field}->Field
							, $exportTables->$table->fields->{$field->Field}->Type
							, $exportTables->$table->fields->{$field->Field}->Null == 'YES'
								? 'NULL'
								: 'NOT NULL'
							, $exportTables->$table->fields->{$field->Field}->Extra == 'auto_increment'
								? 'auto_increment'
								: NULL
							, $exportTables->$table->fields->{$field->Field}->Collation
								? 'COLLATE ' . $exportTables->$table->fields->{$field->Field}->Collation
								: NULL
							, $exportTables->$table->fields->{$field->Field}->Comment
						);
					}

					if(!isset($exportTables->$table->keys))
					{
						continue;
					}

					$createIndex = [];

					foreach($exportTables->$table->keys as $keyName => $key)
					{
						$key = $this->latestKeys($key);
						$columns = $this->latestColumns($key);

						$arKey = (array)$key;

						if($keyName == 'PRIMARY')
						{
							$createIndex[] = sprintf(
								"\tPRIMARY KEY (`%s`)"
								, $columns
							);

							continue;
						}
						else if($key[1]->Non_unique == 0)
						{
							$createIndex[] = sprintf(
								"\tUNIQUE KEY `%s` (`%s`)"
								, $key[1]->Key_name 
								, $columns
							);

							continue;
						}
						else
						{
							$createIndex[] = sprintf(
								"\tKEY `%s` (`%s`)"
								, $key[1]->Key_name 
								, $columns
							);
						}
					}

					$queries[] = sprintf(
						"CREATE TABLE %s(\n%s\n);"
						, $table
						, implode(',' . PHP_EOL, array_merge(
							$createColumn, $createIndex
						))
					);
				}
				else
				{
					if(!isset($exportTables->$table->keys))
					{
						continue;
					}
					
					foreach($exportTables->$table->fields as $field)
					{
						$queries[] = sprintf(
							'ALTER TABLE %s ADD %s %s %s %s'
							, $table
							, $exportTables->$table->fields->{$field->Field}->Field
							, $exportTables->$table->fields->{$field->Field}->Type
							, $exportTables->$table->fields->{$field->Field}->Null == 'YES'
								? 'NULL'
								: 'NOT NULL'
							, $exportTables->$table->fields->{$field->Field}->Extra == 'auto_increment'
								? 'auto_increment'
								: NULL
						);
					}

					foreach($exportTables->$table->keys as $keyName => $key)
					{
						$columns = $this->latestColumns($key);
						$_arKey = (array)$key;

						foreach($_arKey as $key=>$val)
						{
							$arKey[(int)$key] = $val;
						}

						if($keyName == 'PRIMARY')
						{
							$queries[] = sprintf(
								"ALTER TABLE `%s` ADD PRIMARY KEY (`%s`)"
								, $table
								, $columns
							);

							continue;
						}
						else if($arKey[$arKey["1"]->Seq_in_index]->Non_unique == 0)
						{
							$queries[] = sprintf(
								"ALTER TABLE `%s` ADD UNIQUE KEY (`%s`)"
								, $table
								, $columns
							);

							continue;
						}
						else
						{
							$queries[] = sprintf(
								"ALTER TABLE `%s` ADD KEY (`%s`)"
								, $table
								, $columns
							);
						}
					}

				}
			}
		}

		if($real)
		{
			foreach($queries as $query)
			{
				$query = $db->prepare($query);
				$query->execute();
			}
		}

		return $queries;
	}

	protected function latestKeys($key)
	{
		$keys = [];

		foreach($key as $column)
		{
			if(isset($columns[$column->Seq_in_index]))
			{
				continue;
			}

			$keys[$column->Seq_in_index] = $column;
		}

		return $keys;
	}

	protected function latestColumns($key)
	{
		$columns = implode('`, `', array_map(
			function($column)
			{
				return $column->Column_name;
			}
			, $this->latestKeys($key)
		));

		return $columns;
	}

	public function exportModels($model, $function, $args)
	{
		// var_dump($model::getGenerator($function));

		if(!$model::getGenerator($function))
		{
			$function = 'ByNothing';
		}

		$function = ucwords($function);

		$function = 'generate' . $function;

		// var_dump($function);

		$generator = $model::$function();
		$models = [];

		foreach($generator(...$args) as $model)
		{
			if(!$model)
			{
				continue;
			}

			$models[] = $model;
		}

		return $models;
	}

	public function importModels(...$args)
	{
		while($skeleton = array_shift($args))
		{
			$model = new $modelClass;
			$model->consume($skeleton);

			if(isset($skeleton['id']))
			{
				if($_model = $modelClass::loadOneById($skeleton['id']))
				{
					$model = $_model;
					$model->consume($skeleton, true);
					$model->update();
				}
				else
				{
					$model->consume($skeleton, true);
					$model->create();
				}
			}
			else
			{
				$model->create();
			}
		}
	}

	public static function tables()
	{
		return static::$tables;
	}

	public static function setTables($tables)
	{
		static::$tables = $tables;
	}
}