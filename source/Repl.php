<?php
namespace SeanMorris\Ids;
class Repl
{
	public static function init()
	{
		stream_set_blocking(STDIN, FALSE);
		exec('stty -icanon min 0 time 0');
		system('stty -echo');

		register_shutdown_function(function(){
			system('stty echo');
		});

		set_error_handler(function($errno, $errstr, $errfile, $errline)
		{
			printf("Error %d: %s\n%s:%s", $errno, $errstr, $errfile, $errline);
		});

		print "Welcome to iREPL v0.1\n>";
		$line = $input = NULL;
		$lines = [];
		$offset = 0;
		while (TRUE)
		{
			sleep(0.1);
			$char = fread(STDIN,16);

			if(!strlen($char))
			{
				continue;
			}

			var_dump($char);

			$byte = bin2hex($char);

			if($offset < 0)
			{
				$offset = 0;
			}

			if($offset > strlen($line))
			{
				$offset = strlen($line);
			}
			if($byte == '7f')
			{
				if(strlen($line) && $offset < strlen($line))
				{
					print static::backspace($line, $offset);
					$line = substr($line, 0, strlen($line)-($offset+1)) . substr($line, strlen($line)-$offset);
					print $line;
				}
				if(!$line)
				{
					$offset = 0;
				}
				continue;
			}
			if(!preg_match('/\n/', $char) && strlen($byte) == 2)
			{
				print static::backspace($line, $offset);
				$line = substr($line, 0, strlen($line)-$offset) . $char . substr($line, strlen($line)-$offset);
				print $line;
				continue;
			}
			else if(strlen($byte) > 2)
			{
				if($byte == '1b5b41')
				{
					print static::backspace($line, $offset);
					$line = current($lines);
					print $line;
					if(next($lines) === FALSE)
					{
						reset($lines);
					}
					continue;
				}
				else if($byte == '1b5b42')
				{
					print static::backspace($line);
					$line = current($lines);
					print $line;
					if(prev($lines) === FALSE)
					{
						reset($lines);
					}
					continue;
				}
				else if(in_array($byte, ['1b5b43', '1b5b44']))
				{
					if($byte == '1b5b44')
					{
						$offset++;
					}
					else
					{
						$offset--;
					}
					continue;
				}
			}
			$line = trim($line);
			array_unshift($lines, $line);
			if (substr($line, 0, 1) === '/')
			{
				$line = Ltrim($line, '/');
				if ($line === 'export') {
					/*
					 file_put_contents(
					 getenv("HOME") . '/.idilic/replVars.dat',
					 serialize(array_diff_key(
					 get_defined_vars(),
					 array_flip(array('line', 'input', 'output'))
					 ))
					 );
					 */
				}
				else if ($line === 'import') {
				}
			}
			else if (substr($line, -1, 1) === '\\')
			{
				$line = rtrim($line, '\\');
				$input .= PHP_EOL . $line;
				print "|";
				continue;
			}
			else if ($line && substr($line, -1, 1) !== ';')
			{
// 				$input .= sprintf("print PHP_EOL; print_r(%s);", $line);
			}
			else if ($line)
			{
				$input .= $line;
			}
			ob_start();
			try
			{
				print ($input . ';') . PHP_EOL;
				eval($line . ';');
			}
			catch(\Exception $exception)
			{
				print \SeanMorris\Ids\Log::renderException($exception);;
			}
			$output = ob_get_contents();
			ob_end_flush();
			$input = $line = NULL;
			$offset = 0;
			print "\n>";
		}
	}

	protected static function backspace($len, $offset = 0)
	{
		$len = strlen($len);

		//$string = static::jump($offset);
		$string = NULL;

		//$len += $offset;

		$string .= str_repeat("\x08", $len)
		. str_repeat(" ", $len)
		. str_repeat("\x08", $len);

		return $string;
	}

	protected static function jump($offset)
	{
		$string = NULL;

		if($offset < 0)
		{
			$string .= str_repeat("\x1b\x5b\x43", abs($offset));
		}
		else if($offset)
		{
			$string .= str_repeat("\x1b\x5b\x44", abs($offset));
		}
		return $string;
	}
}