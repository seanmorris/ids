<?php
namespace SeanMorris\Ids;

use SeanMorris\Ids\Inject\FactoryMethod;

(new class {
	use Injectable;

	protected $childProcess;

	protected static
		$Interval   = 0.05
		, $Command  = 'idilic @SeanMorris/Ids/Worker::process'
		, $Iterator
		, $Cli;

})::inject([
	'childProcess' => FactoryMethod::wrap(function($parent){
		return new ChildProcess($parent::getCommand(), TRUE, TRUE);
	})

	, 'Cli' => \SeanMorris\Ids\Idilic\Cli::class

], \SeanMorris\Ids\___\BaseWorker::class);

const Worker = __NAMESPACE__ . '\\' . 'WORKER';

class Worker extends \SeanMorris\Ids\___\BaseWorker
{
	public static function process()
	{
		$message = '';

		while($m = static::$Cli::in(NULL, FALSE))
		{
			$message .= $m;
		}

		$message = unserialize($message);

		static::$Cli::error(print_r($message, 1));

		$message->status = 'done!';

		static::$Cli::out(serialize($message));
	}

	public static function run()
	{
		$worker  = new static;
		$last    = 0;

		$worker->childProcess->write(serialize(

			(object)['status' => 'pending']

		));

		do
		{
			if($error = $worker->childProcess->readError())
			{
				static::$Cli::error(' ++ ' . $error);
			}

			if($message = $worker->childProcess->read())
			{
				$message = unserialize($message);

				print_r($message);

				continue;
			}

			if(microtime(true) - $last > static::$Interval)
			{
				$last = microtime(true);
			}
			else
			{
				sleep(static::$Interval);
			}
		} while(!$worker->childProcess->feof() && !$worker->childProcess->feofError());
	}

	public static function getCommand()
	{
		return static::$Command;
	}
}
