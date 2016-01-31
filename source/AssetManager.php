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
