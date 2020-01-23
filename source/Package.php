<?php
namespace SeanMorris\Ids;
class Package
{
	protected $folder, $packageName, $variables   = [];

	protected static
		$assetManager
		, $packages    = []
		, $directories = []
		, $tables      = [
			'main' => ['IdsRelationship']
		];

	public static function getRoot()
	{
		if(isset(static::$directories['root']))
		{
			$vendorRoot = static::$directories['root'];
		}
		else
		{
			$vendorRoot = new \SeanMorris\Ids\Disk\Directory(IDS_VENDOR_ROOT);

			static::$directories['root'] = $vendorRoot;
		}

		$appRoot = $vendorRoot->parent();

		$composerJson = $appRoot->file('composer.json');

		$composerData = json_decode($composerJson->slurp());

		if(!isset($composerData->name))
		{
			$composerData->name = '';
		}

		$packageName = static::name($composerData->name ?? $packageName);

		if(isset(
			$composerData
			, $composerData->autoload
			, $composerData->autoload->{'psr-4'}
		)){
			$namespaces = array_keys(get_object_vars(
				$composerData->autoload->{'psr-4'}
			));

			foreach($namespaces as $namespace)
			{
				if(strtolower($packageName) === strtolower($namespace))
				{
					$packageName = substr($namespace, 0, -1);
				}
			}
		}

		$packageClass = $packageName . '\\Package';

		if(class_exists($packageClass))
		{
			return new $packageClass($packageName);
		}

		$package = static::get($packageName);

		return $package;
	}

	public static function get($packageName = NULL)
	{
		$packageName = str_replace('\\/', '\\', $packageName);

		$packageNameParts = explode('/\\', $packageName);
		$packageName      = array_shift($packageNameParts);
		$packageName     .= '\\' . array_shift($packageNameParts);

		$vendorRoot = new \SeanMorris\Ids\Disk\Directory(IDS_VENDOR_ROOT);
		$appRoot    = $vendorRoot->parent();

		$dirFrag    = strtolower(str_replace('\\', '/', $packageName));
		$spaceFrag  = strtolower($packageName);

		if($dirFrag[strlen($dirFrag) - 1] === '/')
		{
			$dirFrag = substr($dirFrag, 0, -1);
		}

		$rootComposerJson = $appRoot->file('composer.json');
		$rootComposerData = json_decode($rootComposerJson->slurp());

		$root = FALSE;

		if(isset($rootComposerData->name)
			&& strtolower($dirFrag) === ($rootComposerData->name ?? NULL)
		){
			$composerJson = $rootComposerJson;
			$root = TRUE;
		}
		else
		{
			$composerJson = $vendorRoot->dir($dirFrag)->file('composer.json');
		}

		$packageName  = static::name($packageName);
		$packageClass = $packageName . '\\Package';

		if($packageClass == __CLASS__ || class_exists($packageName))
		{
			return new $packageClass($packageName, $root);
		}

		return new class($packageName, $root) extends Package {
			protected static
				$directories = []
				, $tables    = ['main' => []]
			;
		};
	}

	protected function __construct($package, $root = FALSE)
	{
		$packageName = static::name($package);
		$packageDir = static::dir($package);

		$packageClass = $packageName . 'Package';

		if($packageClass === __CLASS__ || class_exists($packageClass))
		{
			$reflection = new \ReflectionClass($packageClass);
			$classFile = $reflection->getFileName();
			$this->packageName = $packageName;

			$folder = dirname(dirname($classFile)) . '/';

			if($root)
			{
				$vendorRoot   = new \SeanMorris\Ids\Disk\Directory(IDS_VENDOR_ROOT);
				$folder = $vendorRoot->parent();
			}
		}
		else
		{
			$packages = static::packageDirectories();

			if(isset($packages[$packageDir]))
			{
				$this->packageName = $packageName;
				$folder = $packages[$packageDir];
			}
			else if(isset($packages[strtolower($packageDir)]))
			{
				$this->packageName = $packageName;
				$folder = $packages[strtolower($packageDir)];
			}
			else
			{
				throw new \Exception('No Package defined for ' . $package);
			}
		}

		$this->folder = new \SeanMorris\Ids\Disk\Directory($folder);

		$composerJson = $this->folder->file('composer.json');

		if($composerJson && $composerJson->check())
		{
			$composerData = json_decode($composerJson->slurp());

			$packageName = static::name($composerData->name);
		}

		if(isset(
			$composerData
			, $composerData->autoload
			, $composerData->autoload->{'psr-4'}
		)){
			$namespaces = array_keys(
				get_object_vars($composerData->autoload->{'psr-4'})
			);

			foreach($namespaces as $namespace)
			{
				if(strtolower($packageName . '\\') === strtolower($namespace))
				{
					$this->packageName = substr($namespace, 0, -1);
				}
			}
		}
	}

	public static function listPackages()
	{
		return array_keys(static::packageDirectories());
	}

	public static function packageDirectories()
	{
		if(static::$packages)
		{
			return static::$packages;
		}

		$vendorRoot = new \SeanMorris\Ids\Disk\Directory(IDS_VENDOR_ROOT);

		while($vendorDir = $vendorRoot->read())
		{
			while($packageDir = $vendorDir->read())
			{
				if(! $packageDir instanceof \SeanMorris\Ids\Disk\Directory)
				{
					continue;
				}

				$composerJson = $packageDir->file('composer.json');

				if(! $composerJson->check())
				{
					continue;
				}

				$composerData = json_decode($composerJson->slurp());

				static::$packages[$composerData->name] = $packageDir->name();
			}
		}

		$appRoot = $vendorRoot->parent();

		$composerJson = $appRoot->file('composer.json');

		$composerData = json_decode($composerJson->slurp());

		$name = '';

		if(isset($composerData->name))
		{
			$name = $composerData->name;
		}
		static::$packages[$name] = $appRoot->name();

		ksort(static::$packages);

		return static::$packages;
	}

	public static function name($package = NULL)
	{
		$name = str_replace(
			'/', '\\', $package ?: substr(
				static::class, 0, strpos(
					static::class
					, '\\'
					, strpos(static::class, '\\')
				)
			)
		);

		while($name && strstr($name, '\\\\'))
		{
			$name = str_replace('\\\\', '\\', $name);
		}

		while($name && $name[strlen($name)-1] == '\\')
		{
			$name = substr($name, 0, -1);
		}

		return $name;
	}

	public static function dir($package)
	{
		return str_replace('\\', '/', $package ?? static::class);
	}

	public function packageDir()
	{
		$key = $this->packageName . '-package';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$this->folder
		);
	}

	public function packageSpace()
	{
		return $this->packageName;
	}

	public function assetDir()
	{
		$key = $this->packageName . '-assets';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$this->folder . 'asset/'
		);
	}

	public function publicDir()
	{
		$key = $this->packageName . '-public';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		if(!$publicDir = Settings::read('public'))
		{
			return;
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$publicDir . '/' . $this->dir($this->packageSpace())
		);
	}

	public function globalDir()
	{
		$key = $this->packageName . '-global';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$this->packageDir() . 'data/global/'
		);
	}

	public function localDir()
	{
		$key = $this->packageName . '-local';

		if(isset(static::$directories[$key]))
		{
			return static::$directories['local'];
		}

		return static::$directories['local'] = new \SeanMorris\Ids\Disk\Directory(
			$this->packageDir() . 'data/local/'
		);
	}

	public function configDir()
	{
		$key = $this->packageName . '-config';

		if(isset(static::$directories[$key]))
		{
			return static::$directories['config'];
		}

		return static::$directories['config'] = new \SeanMorris\Ids\Disk\Directory(
			$this->packageDir() . 'config/'
		);
	}

	public function sourceDir()
	{
		$key = $this->packageName . '-source';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$this->packageDir() . 'source/'
		);
	}

	public function testDir()
	{
		$key = $this->packageName . '-test';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$this->packageDir() . 'test/'
		);
	}

	public function dataDir()
	{
		$key = $this->packageName . '-data';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$this->packageDir() . 'data/');
	}

	public function localSiteDir()
	{
		$key = $this->packageName . '-localSite';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		if(!isset($_SERVER['HTTP_HOST']) && php_sapi_name() == 'cli')
		{
			// throw new \Exception(
			// 	'Please set a site with the "-d" switch or use .idilicProfile.json'
			// );
			return FALSE;
		}

		$hostname = NULL;

		if(isset($_SERVER['HTTP_HOST']))
		{
			$hostname = parse_url('//' . $_SERVER['HTTP_HOST'], PHP_URL_HOST);
		}

		$port = NULL;

		if(isset($_SERVER['SERVER_PORT']))
		{
			$port = $_SERVER['SERVER_PORT'];
		}

		$fileNames = [
			sprintf('%s:%d/', $hostname, $port)
			, sprintf('%s;%d/', $hostname, $port)
			, sprintf('%s:/', $hostname)
			, sprintf('%s;/', $hostname)
			, $hostname . '/'
			, sprintf(':%d/', $port)
			, sprintf(';%d/', $port)
			, ':/settings'
			, ';/settings'
			, ':/'
			, ';/'
		];

		foreach($fileNames as $fileName)
		{
			$dirPath = sprintf('%ssites/%s', $this->configDir(), $fileName);

			$maybeDir = new \SeanMorris\Ids\Disk\Directory(
				$dirPath
			);

			if($maybeDir->check())
			{
				return static::$directories[$key] = $maybeDir;
			}

			$dirPath = sprintf('%ssites/%s', $this->localDir(), $fileName);

			$maybeDir = new \SeanMorris\Ids\Disk\Directory(
				$dirPath
			);

			if($maybeDir->check())
			{
				return static::$directories[$key] = $maybeDir;
			}
		}
	}

	public function globalSiteDir()
	{
		$key = $this->packageName . '-globalSite';

		if(isset(static::$directories[$key]))
		{
			return static::$directories[$key];
		}

		if(! isset($_SERVER['HTTP_HOST']))
		{
			throw new \Exception(
				'$_SERVER["HTTP_HOST"] is not defined. Please set a site with the "-d" switch or use .idilicProfile.json'
			);
		}

		return static::$directories[$key] = new \SeanMorris\Ids\Disk\Directory(
			$this->globalDir() . 'sites/' . $_SERVER['HTTP_HOST'] . '/');
	}

	public function assetManager()
	{
		$assetManager = static::$assetManager;

		if(! $assetManager)
		{
			$assetManager = 'SeanMorris\Ids\AssetManager';
		}

		if(class_exists($assetManager))
		{
			return new $assetManager;
		}

		return new $assetManager;
	}

	public function setVar($var, $val, $type = 'local')
	{
		$key = $var . '::' . $type;

		$varPath = preg_split('/(?<!\\\\)\:/', $var);

		if($type == 'local')
		{
			$dir = $this->localSiteDir();
		}
		else
		{
			$dir = $this->globalDir();
		}

		if(!$dir)
		{
			$dir = new \SeanMorris\Ids\Disk\Directory($this->configDir() . '_;/');
			$dir->create();
		}

		if(!file_exists($dir))
		{
			\SeanMorris\Ids\Log::warn(sprintf(
				'%s directory %s does not exist. Attempting to create...'
				, $type
				, $dir
			));

			mkdir($dir, 0777, true);
		}

		$path = $dir . 'var.json';

		if(! file_exists($path))
		{
			file_put_contents($path, '{}');
		}

		$varsJson = file_get_contents($path);

		if($vars = json_decode($varsJson))
		{
			$currentVar = & $vars;

			while($varName = array_shift($varPath))
			{
				if(is_scalar($currentVar) || !$currentVar)
				{
					$currentVar = (object) [
						$varName => (object) []
					];
				}

				$currentVar = & $currentVar->$varName;
			}

			$currentVar = $val;
		}
		else
		{
			print "Invalid JSON in " . $path;
			return;
		}

		file_put_contents($path, json_encode($vars, JSON_PRETTY_PRINT));

		$this->variables[$key] = $val;
	}

	public function getVar($var, $val = NULL, $type = 'local')
	{
		$key = $var . '::' . $type;

		if(isset($this->variables[$key]))
		{
			return $this->variables[$key];
		}

		$varPath = preg_split('/(?<!\\\\)\:/', $var);

		if($type == 'local')
		{
			$dir = $this->localSiteDir();
		}
		else
		{
			$dir = $this->globalDir();
		}

		if(!$dir)
		{
			return $val;
		}

		$path = $dir->name() . 'var.json';

		if(! file_exists($dir) || ! file_exists($path))
		{
			return $val;
		}

		$varsJson = file_get_contents($path);

		if(($vars = json_decode($varsJson)))
		{

			$currentVar = $vars;

			while($varName = array_shift($varPath))
			{
				$currentVar = ($currentVar
					? ($currentVar->{$varName} ?? NULL)
					: (object) []
				);
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
			$dir = $this->localSiteDir();
		}
		else
		{
			$dir = $this->globalDir();
		}

		$path = $dir . 'var.json';

		if(! file_exists($dir) || ! file_exists($path))
		{
			return;
		}

		$varsJson = file_get_contents($path);

		if(($vars = json_decode($varsJson)))
		{
			$currentVar = & $vars;

			while($varName = array_shift($varPath))
			{
				if(! count($varPath))
				{
					unset($currentVar->$varName);
					break;
				}

				$currentVar = & $currentVar->$varName;
			}
		}

		file_put_contents($path, json_encode($vars));
	}

	protected function getStoredSchema()
	{
		$schemaFilename = $this->globalDir() . 'schema.json';

		if(! file_exists($schemaFilename))
		{
			return;
		}

		$schema = json_decode(file_get_contents($schemaFilename));

		if(! $schema)
		{
			throw new \Exception(
				sprintf('Schema file invalid at %s', $schemaFilename)
			);
		}

		if(! isset($schema->revisions))
		{
			$schema->revisions = [];
		}

		return $schema;
	}

	public function getFullSchema($revision = null)
	{
		$schema = $this->getStoredSchema();

		if(! $schema)
		{
			return;
		}

		$fullSchema = (object) [];

		$objectMerge = function ($obj1, $obj2) use (&$objectMerge) {
			$obj1 = clone $obj1;
			$obj2 = clone $obj2;

			foreach($obj2 as $prop =>$val)
			{
				if(isset($obj1->$prop)
					&& is_object($obj1->$prop)
					&& is_object($obj2->$prop)
				){
					$obj1->$prop = $objectMerge($obj1->$prop, $obj2->$prop);
				}
				else
					if($val === FALSE)
					{
						unset($obj1->$prop);
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
			$fullSchema = $objectMerge($fullSchema, $revision);
		}

		return $fullSchema;
	}

	public function storeSchema()
	{
		$globalDir = $this->globalDir();
		$schemaFilename = $globalDir . 'schema.json';

		if(! $globalDir->check())
		{
			$globalDir->create(NULL, 0766, TRUE);
		}

		if(! $globalDir->check())
		{
			throw new \Exception(
				sprintf('Cannot find global dir %s', $globalDir->name()));
		}

		if(! file_exists($schemaFilename))
		{
			file_put_contents($schemaFilename, '{}');
		}

		$schema = $this->getStoredSchema();
		$changes = $this->getSchemaChanges();

		$revisionCount = count((array) $schema->revisions);
		$changeCount = count((array) $changes);

		if(! $schema->revisions)
		{
			$schema->revisions = new \StdClass();
		}

		if($changeCount)
		{
			$schema->revisions->{$revisionCount} = $changes;

			$schemaFilename = $this->globalDir() . 'schema.json';

			$schema->revisions = (object) $schema->revisions;

			file_put_contents(
				$schemaFilename,
				json_encode($schema, JSON_PRETTY_PRINT));

			return $changes;
		}
	}

	public function getSchemaChanges()
	{
		$storedSchema = $this->getFullSchema();
		$changes = (object) [];

		if(! $storedSchema)
		{
			$storedSchema = (object) [];
		}

		$classTables = static::$tables + ['main' => []];

		foreach($classTables as $db =>$tables)
		{
			// @TODO: GENERALIZE FOR MULTIPLE DBs
			// Need to refactor all schema functions...
			$tables += array_keys((array) $storedSchema);

			$db = Database::get($db);

			foreach($tables as $table)
			{
				$tableCheck = $db->prepare('SHOW TABLES LIKE "' . $table . '"');

				$tableCheck->execute();

				if(! $tableCheck->fetchObject())
				{
					continue;
				}

				if(! isset($storedSchema->$table))
				{
					$storedSchema->$table = new \StdClass;
				}

				if(! isset($storedSchema->$table->fields))
				{
					$storedSchema->$table->fields = new \StdClass;
				}

				if(! isset($storedSchema->$table->keys))
				{
					$storedSchema->$table->keys = new \StdClass;
				}

				$queryString = 'SHOW FULL COLUMNS FROM ' . $table;
				\SeanMorris\Ids\Log::query($queryString);
				$query = $db->prepare($queryString);
				$query->execute();

				while($column = $query->fetchObject())
				{
					unset($column->Privileges);

					if(isset($storedSchema->$table->fields->{$column->Field})
						&& $storedSchema->$table->fields->{$column->Field} == $column
					){
						continue;
					}

					if(! isset($changes->$table))
					{
						$changes->$table = new \StdClass;
					}

					if(! isset($changes->$table->fields))
					{
						$changes->$table->fields = new \StdClass;
					}

					$changes->$table->fields->{$column->Field} = $column;
				}

				$queryString = 'SHOW INDEXES FROM ' . $table;
				\SeanMorris\Ids\Log::query($queryString);
				$query = $db->prepare($queryString);
				$query->execute();

				while($index = $query->fetchObject())
				{
					unset($index->Cardinality);

					if(isset($storedSchema->$table->keys->{$index->Key_name}))
					{
						$_arKey = (array) $storedSchema->$table->keys->{$index->Key_name};
						$arKey = [];

						foreach($_arKey as $key =>$val)
						{
							$arKey[(int) $key] = $val;
						}

						unset($arKey[$index->Seq_in_index]->Cardinality);

						if(isset($arKey[$index->Seq_in_index])
							&& $index == $arKey[$index->Seq_in_index]
						){
							continue;
						}
					}

					if(isset($storedSchema->$table->keys->{$index->Key_name})
						&& isset($storedSchema->$table->keys->{$index->Key_name}->{$index->Seq_in_index})
						&& $storedSchema->$table->keys->{$index->Key_name}->{$index->Seq_in_index} ==$index
					){
						continue;
					}

					if(! isset($changes->$table))
					{
						$changes->$table = new \StdClass;
					}

					if(! isset($changes->$table->keys))
					{
						$changes->$table->keys = new \StdClass;
					}

					if(! isset($changes->$table->keys->{$index->Key_name}))
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

		foreach(static::$tables as $db =>$definedTables)
		{
			$db = Database::get($db);

			$tables = array_unique(
				$definedTables + array_keys((array) $exportTables)
			);

			foreach($tables as $table)
			{
				if(! isset($exportTables->$table))
				{
					continue;
				}

				$tableCheckString = 'SHOW TABLES LIKE "' . $table . '"';

				$tableCheck = $db->prepare($tableCheckString);

				$tableCheck->execute();

				$tableFound = false;

				if($tableCheck->fetchObject())
				{
					$queryString = 'SHOW FULL COLUMNS FROM ' . $table;
					\SeanMorris\Ids\Log::query($queryString);
					$query = $db->prepare($queryString);
					$query->execute();

					while($column = $query->fetchObject())
					{
						\SeanMorris\Ids\Log::query('Loaded', $column);
						if(! isset($exportTables->$table->fields->{$column->Field}))
						{
							if(! isset($exportTables->$table))
							{
								\SeanMorris\Ids\Log::warn(
									sprintf(
										'Table %s::%s exists but is not defined in schema file.'
										, $this
										, $exportTables->$table
									)
								);

								continue;
							}
							$queries[] = sprintf(
								"ALTER TABLE `%s` DROP COLUMN `%s`;"
								, $table
								, $column->Field
							);

							continue;
						}

						$tableFound = true;

						unset($column->Privileges);
						unset($column->Key);
						unset($exportTables->$table->fields->{$column->Field}->Key);

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
								? 'COLLATE ' . $exportTables->$table->fields->{$column->Field}->Collation
								: NULL

							, $exportTables->$table->fields->{$column->Field}->Comment
						);

						unset($exportTables->$table->fields->{$column->Field});
					}

					$queryString = 'SHOW INDEXES FROM ' . $table;
					\SeanMorris\Ids\Log::query($queryString);
					$query = $db->prepare($queryString);
					$query->execute();

					while($index = $query->fetchObject())
					{
						\SeanMorris\Ids\Log::query('Loaded', $index);
						if(! isset($exportTables->$table->keys->{$index->Key_name}))
						{
							continue;
						}

						$_arKey = (array) $exportTables->$table->keys->{$index->Key_name};

						$arKey = [];

						foreach($_arKey as $key =>$val)
						{
							$arKey[(int) $key] = $val;
						}

						unset(
							$index->Cardinality
							, $arKey[$index->Seq_in_index]->Cardinality
						);

						if(! isset($arKey[$index->Seq_in_index]))
						{
							continue;
						}

						if($index == $arKey[$index->Seq_in_index])
						{
							unset($exportTables->$table->keys->{$index->Key_name});

							continue;
						}

						$columns = static::latestColumns(
							$exportTables->$table->keys->{$index->Key_name}
						);

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
						else if($index->Non_unique == 0)
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
								"ALTER TABLE `%s` DROP KEY %s;"
								, $table
								, $index->Key_name
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
				}

				if(! $tableFound)
				{
					$createColumn = [];

					if(!isset($exportTables->$table) || !isset($exportTables->$table->fields))
					{
						continue;
					}

					foreach($exportTables->$table->fields as $field)
					{
						if(! isset($exportTables->$table->fields->{$field->Field}))
						{
							continue;
						}

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

					$createIndex = [];

					if(isset($exportTables->$table->keys))
					{
						foreach($exportTables->$table->keys as $keyName =>$key)
						{
							$key = $this->latestKeys($key);
							$columns = $this->latestColumns($key);

							$arKey = (array) $key;

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
					}

					$queries[] = sprintf(
						"CREATE TABLE %s(\n%s\n);"
						, $table
						, implode(
							',' . PHP_EOL
							, array_merge($createColumn, $createIndex)
						)
					);
				}
				else
				{
					foreach($exportTables->$table->fields as $field)
					{
						if(! isset($exportTables->$table->fields->{$field->Field}))
						{
							continue;
						}

						$queries[$field->Field] = sprintf(
							'ALTER TABLE %s ADD COLUMN %s %s %s %s'
							, $table
							, $exportTables->$table->fields->{$field->Field}->Field
							, $exportTables->$table->fields->{$field->Field}->Type
							, $exportTables->$table->fields->{$field->Field}->Null == 'YES'
								? 'NULL'
								: 'NOT NULL'
							, $exportTables->$table->fields->{$field->Field}->Extra == 'auto_increment'
								? 'auto_increment PRIMARY KEY'
								: NULL
						);
					}

					if(isset($exportTables->$table->keys))
					{
						foreach($exportTables->$table->keys as $keyName =>$key)
						{
							$columns = $this->latestColumns($key);
							$_arKey = (array) $key;

							foreach($_arKey as $key =>$val)
							{
								$arKey[(int) $key] = $val;
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
							else if($arKey[$arKey["1"]->Seq_in_index]->Non_unique ==	0)
								{
									$queries[] = sprintf(
										"ALTER TABLE `%s` ADD UNIQUE KEY `%s` (`%s`)"
										, $table
										, $arKey[1]->Key_name
										, $columns
									);

									continue;
								}
								else
								{
									$queries[] = sprintf(
										"ALTER TABLE `%s` ADD KEY `%s` (`%s`)"
										, $table
										, $arKey[1]->Key_name
										, $columns
									);
								}
						}
					}
				}
			}
		}

		if($real)
		{
			foreach($queries as $query)
			{
				\SeanMorris\Ids\Log::query($query);
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
			$keys[$column->Seq_in_index] = $column;
		}

		return $keys;
	}

	protected function latestColumns($key)
	{
		$columns = implode(
			'`, `'
			, array_map(function ($column) {
				return $column->Column_name;
			}
			, $this->latestKeys($key))
		);

		return $columns;
	}

	public function exportModels($model, $function, $args)
	{
		if(! $model::getGenerator($function))
		{
			$function = 'ByNull';
		}

		$function = ucwords($function);

		$function = 'generate' . $function;

		$generator = $model::$function(...$args);
		$models = [];

		return $generator;

		foreach($generator() as $model)
		{
			if(! $model)
			{
				continue;
			}

			$models[] = $model;

			\SeanMorris\Ids\Model::clearCache(TRUE);
		}

		return $models;
	}

	public function importModels($modelClass, ...$args)
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

	public static function fromClass($class)
	{
		$splitClass = preg_split('/\\\\/', $class);

		if(! count($splitClass) >= 2)
		{
			return FALSE;
		}

		return static::get($splitClass[0] . '/' . $splitClass[1]);
	}

	public function cliName()
	{
		return preg_replace('/\\\\/', '/', $this->packageName);
	}
}
