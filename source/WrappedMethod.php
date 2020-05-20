<?php
namespace SeanMorris\Ids;
abstract class WrappedMethod implements Method
{
	protected $callback;

	public static function wrap(callable $closure)
	{
		return eval(sprintf(
			'return new class($closure) extends \%s {};'
			, get_called_class()
		));
	}

	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	public function __invoke(...$args)
	{
		return ($this->callback)(...$args);
	}
}
