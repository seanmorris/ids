<?php
namespace SeanMorris\Ids;
abstract class WrappedMethod implements Method
{
	protected $callback;

	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	public function __invoke(...$args)
	{
		return ($this->callback)(...$args);
	}
}
