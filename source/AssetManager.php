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

		$filename = 'Static/Dynamic/' . $fullHash;

		$outputFile = new \SeanMorris\Ids\Storage\Disk\File($publicDir . $filename);

		if($outputFile->check())
		{
			return '/' . $filename;
		}

		$assetHashes = [];
		$assetOrder = [];
		
		foreach($assets as $asset)
		{
			$chunks = array_filter(explode('/', $asset));
			$vendor = array_shift($chunks);
			$packageName = array_shift($chunks);
			
			$package = Package::get($vendor . '/' . $packageName);
			$assetName = implode('/', $chunks);

			\SeanMorris\Ids\Log::debug('Building asset ' . $assetName, 'From ' . $vendor . '/' . $packageName);

			if($asset = $package->assetDir()->has($assetName))
			{
				$assetType = pathinfo($asset->name(), PATHINFO_EXTENSION);
				$assetContent = $asset->slurp();

				$assetHashes[$assetType][$packageName][sha1($assetContent)] = $asset;
			}
		}

		if(isset($assetHashes['js']))
		{
			//$fullHash = NULL;

			foreach($assetHashes['js'] as $package => $js)
			{
				foreach($js as $contentHash => $asset)
				{
					// $fullHash = sha1($fullHash . $contentHash);
				}
			}

			$filename = 'Static/Dynamic/' . $fullHash; // . '.js';

			$outputFile = new \SeanMorris\Ids\Storage\Disk\File($publicDir . $filename);

			if($outputFile->check())
			{
				return '/' . $filename;
			}
			
			$outputFile->write('// ' . time() . PHP_EOL, FALSE);

			foreach($assetHashes['js'] as $package => $js)
			{
				foreach($js as $contentHash => $asset)
				{
					$outputFile->write('// ' . $asset->name() . PHP_EOL);
					$outputFile->write($asset->slurp() . PHP_EOL);
				}
			}

			return '/' . $filename;
		}

		$filename = 'Static/Dynamic/' . $listHash; // . '.css';

		$outputFile = new \SeanMorris\Ids\Storage\Disk\File($publicDir . $filename);

		if($outputFile->check())
		{
			return '/' . $filename;
		}

		if(isset($assetHashes['css']))
		{
			// $fullHash = NULL;

			foreach($assetHashes['css'] as $package => $js)
			{
				foreach($js as $contentHash => $asset)
				{
					// $fullHash = sha1($fullHash . $contentHash);
				}
			}

			$filename = 'Static/Dynamic/' . $fullHash; // . '.css';
			
			$outputFile = new \SeanMorris\Ids\Storage\Disk\File($publicDir . $filename);

			if($outputFile->check())
			{
				return '/' . $filename;
			}
			
			$outputFile->write('// ' . time() . PHP_EOL, FALSE);

			foreach($assetHashes['css'] as $package => $js)
			{
				foreach($js as $contentHash => $asset)
				{
					$outputFile->write('// ' . $asset->name() . PHP_EOL);
					$outputFile->write($asset->slurp() . PHP_EOL);
				}
			}

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
			$publicDir->create();
		}

		static::mapAssets(
			$package
			, function($dir, $file) use($assetDir, $publicDir)
			{
				$sub = $assetDir->has($dir);

				$dir = new \SeanMorris\Ids\Storage\Disk\Directory($publicDir . $sub);

				if(!$dir->check())
				{
					$dir->create();
				}

				echo "Building " . $file;
				echo "\n\t To: " . $dir . $file->basename();
				echo PHP_EOL;
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
