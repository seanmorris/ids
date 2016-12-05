<?php
namespace SeanMorris\Ids;
class AssetManager
{
	protected static function mapAssets($package, $callback, $dir = null)
	{
		if(!$dir)
		{
			$dir = $package->assetDir();
		}

		if(!$dir->check())
		{
			return false;
		}

		while($file = $dir->read())
		{
			if(is_dir($file))
			{
				static::mapAssets($package, $callback, $file);
				continue;
			}

			$callback($dir, $file);
		}
	}

	public static function buildAssets2($assets = [])
	{
		$listHash = strtoupper(sha1(print_r($assets, 1)));
		$fullHash = $listHash;

		if(!$publicDir = Settings::read('public'))
		{
			return;
		}

		$cacheAssets = Settings::read('cacheAssets');

		$filename = 'Static/Dynamic/Min/' . $fullHash;

		// \SeanMorris\Ids\Log::debug('Building assets:', $assets);

		$assetHashes = [];
		$assetOrder = [];

		foreach($assets as $asset)
		{
			$chunks = array_filter(explode('/', $asset));
			$vendor = array_shift($chunks);
			$packageName = array_shift($chunks);
			$fullPackageName = $vendor . '/' . $packageName;
			
			if($fullPackageName !== 'Static/Dynamic')
			{
				$assetName = implode('/', $chunks);

				// \SeanMorris\Ids\Log::debug('Building asset ' . $assetName, 'From ' . $vendor . '/' . $packageName);

				$package = Package::get($fullPackageName);

				if($asset = $package->assetDir()->has($assetName))
				{
					$assetType = pathinfo($asset->name(), PATHINFO_EXTENSION);
					$assetHashes[$assetType][$fullPackageName][$asset->name()] = $asset;

					if(!$asset->check())
					{
						\SeanMorris\Ids\Log::error(sprintf("Asset %s not found!\n%s", $asset, $assetName));
					}
				}	
			}
			else
			{
				$assetName = $fullPackageName . '/' . implode('/', $chunks);

				// \SeanMorris\Ids\Log::debug('Building asset ' . $assetName, 'From ' . $vendor . '/' . $packageName);

				$asset = new \SeanMorris\Ids\Disk\File($assetName);

				if($asset->check())
				{
					$assetType = pathinfo($asset->name(), PATHINFO_EXTENSION);
					$assetHashes[$assetType][$fullPackageName][$asset->name()] = $asset;
				}
				else
				{
					\SeanMorris\Ids\Log::error(sprintf("Asset %s not found!\n%s", $asset->name(), $assetName));
				}
			}
		}

		if(isset($assetHashes['js']))
		{
			$filename .= '.js';
			$outputFile = new \SeanMorris\Ids\Disk\File($publicDir . '/' . $filename);
			if($outputFile->check() && $cacheAssets)
			{
				\SeanMorris\Ids\Log::debug('Returning cached asset: '  . $filename);
				return '/' . $filename;
			}
			$outputFile->write('// ' . time() . PHP_EOL, FALSE);

			foreach($assetHashes['js'] as $package => $js)
			{
				$outputFile->write('// ' . $package . PHP_EOL);
				foreach($js as $contentHash => $asset)
				{
					$outputFile->write('//   ' . $asset->name() . PHP_EOL);
				}
				$outputFile->write('// ' . PHP_EOL);
				foreach($js as $contentHash => $asset)
				{
					\SeanMorris\Ids\Log::debug(sprintf(
						"Writing %s\n\tto %s."
						, $asset->name()
						, $publicDir . '/' . $filename
					));
					$outputFile->write('// ' . $asset->name() . PHP_EOL);
					$outputFile->write($asset->slurp() . PHP_EOL);
				}
			}

			\SeanMorris\Ids\Log::debug('Built asset: '  . $filename);

			return '/' . $filename;
		}

		$outputFile = new \SeanMorris\Ids\Disk\File($publicDir . '/' . $filename);
		
		if($outputFile->check())
		{
			return '/' . $filename;
		}

		if(isset($assetHashes['css']))
		{
			$filename .= '.css';
			$outputFile = new \SeanMorris\Ids\Disk\File($publicDir . '/' . $filename);
			if($outputFile->check() && $cacheAssets)
			{
				\SeanMorris\Ids\Log::debug('Returning cached asset: '  . $filename);
				return '/' . $filename;
			}
			
			$outputFile->write(sprintf("/* %s */\n", time()));

			foreach($assetHashes['css'] as $package => $css)
			{
				$outputFile->write(sprintf("/* %s */\n", $package));
				foreach($css as $contentHash => $asset)
				{
					$outputFile->write(sprintf("/*   %s */\n", $asset->name()));
				}
				$outputFile->write('/* */ ' . PHP_EOL);
				foreach($css as $contentHash => $asset)
				{
					\SeanMorris\Ids\Log::debug(sprintf(
						"Writing %s\n\tto %s."
						, $asset->name()
						, $publicDir . '/' . $filename
					));
					$outputFile->write(sprintf("/* %s */\n", $asset->name()));
					$outputFile->write($asset->slurp() . PHP_EOL);
				}
			}
			\SeanMorris\Ids\Log::debug('Built asset: '  . $filename);
			return '/' . $filename;
		}
	}

	public static function buildAssets($package, $pharDir = null)
	{
		$assetDir = $package->assetDir();
		$publicDir = $package->publicDir();

		if(!$assetDir->check())
		{
			return false;
		}

		if($publicDir->check())
		{
			static::clearOutputDir($publicDir);
		}
		else
		{
			var_dump($publicDir);
			$publicDir->create(NULL, 0777, TRUE);
		}

		static::mapAssets(
			$package
			, function($dir, $file) use($assetDir, $publicDir)
			{
				if($assetDir->has($dir))
				{
					$subDir = $dir->subtract($assetDir);
				}

				if(!$dir = $publicDir->has($subDir))
				{
					$dir = $publicDir->create($subDir, 0777, TRUE);
				}

				echo "Building " . $file;
				echo "\n\t To: " . $dir . $file->basename();
				echo PHP_EOL;

				if(!$dir->check())
				{
					$dir->create(NULL, 0777, TRUE);
				}

				$file->copy($dir . $file->basename());
			}
		);
	}

	protected static function clearOutputDir($dir)
	{
		$dirHandle = opendir($dir);

		while($file = readdir($dirHandle))
		{
			if(strpos($file, '.') === 0)
			{
				continue;
			}

			if(is_dir($dir . $file))
			{
				$file = $file . '/';

				static::clearOutputDir($dir . $file);
				rmdir($dir . $file);
				continue;
			}

			echo "Deleting " . $dir . $file;
			echo PHP_EOL;

			unlink($dir . $file);
		}

		echo PHP_EOL;
	}

	public static function packAssets($inputDir, $outputDir, $pharDir = null)
	{
		$dirHandle = opendir($inputDir);

		if(!file_exists($outputDir))
		{
			return false;
		}

		$pharPath = $outputDir . 'assets.phar';

		if(!$pharDir)
		{
			if(file_exists($outputDir . 'assets.phar'))
			{
				unlink($outputDir . 'assets.phar');
			}

			echo "Packing into " . $pharPath;
			echo PHP_EOL;
		}

		$phar = new \Phar($pharPath);

		while($file = readdir($dirHandle))
		{
			if(strpos($file, '.') === 0)
			{
				continue;
			}

			if(is_dir($inputDir . $file))
			{
				$file = $file . '/';

				static::packAssets($inputDir . $file, $outputDir, $file);
				continue;
			}

			echo "Packaging " . $pharDir . $file;
			echo PHP_EOL;

			$phar->addFile($inputDir . $file, $pharDir . $file);
		}
	}

	public static function unpackAssets($inputDir, $outputDir)
	{
		$phar = new \Phar($inputDir . 'assets.phar');

		echo "Unpacking " . $inputDir . 'assets.phar';
		echo PHP_EOL;

		if(!file_exists($outputDir))
		{
			mkdir($outputDir, 0755, true);
		}

		$phar->extractTo($outputDir, NULL, true);
	}
}
