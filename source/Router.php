<?php
namespace SeanMorris\Ids;
class Router
{
	protected
		$path	
		, $routes
		, $parent
		, $child
		, $routedTo
		, $subRouted
		, $match
		, $regex
		, $matches
		, $context = []
	;

	public function __construct(Request $request, Routable $routes, Router $parent = null, $subRouted = false)
	{
		$this->request = $request;
		$this->path = $request->path();
		$this->routes = $routes;
		$this->parent = $parent;
		$this->subRouted = $subRouted;

		if($parent && !$subRouted)
		{
			$parent->child = $this;
		}

		if($parent)
		{
			$this->context =& $parent->context;
		}

		if(is_callable([$routes, '_init']))
		{
			$result = $routes->_init($this);
		}
	}

	public function route()
	{
		$path = $this->path;
		$routes = $this->routes;
		$parent = $this->parent;

		if(is_string($routes) && class_exists($routes))
		{
			$routes = new $this->routes();
		}

		$result = false;

		$node = $path->consumeNode();

		$preroutePath = clone $path;

		try
		{
			if((!$path->count() || $node == '') && is_callable([$routes, 'index']))
			{
				if(!$path->nodes())
				{
					$path->append(null);	
				}
				$node = 'index';
			}

			if(strpos($node, '_') === 0)
			{
				$node = '__' . $node;
			}

			if(isset($routes->alias[$node]))
			{
				if(!array_filter($path->nodes()))
				{
					$path->setAlias($routes->alias[$node]);
				}

				$node = $routes->alias[$node];				
			}

			if(is_callable([$routes, $node]))
			{
				$this->match = $node;
				$this->routedTo = $node;

				Log::debug(sprintf('Routing to function %s for url node "%s".', $node, $node));

				if(is_callable([$routes, '_preRoute']))
				{
					if($routes->_preRoute($this, $node) !== false)
					{
						$result = $routes->$node($this);
					}
					else
					{
						$result = $routes->$node($this);

						if($result === false && is_callable([$routes, '_notFound']))
						{
							$this->match = false;
							$this->routedTo = false;

							$result = $routes->_notFound($this);
						}
					}
				}
				else
				{
					$result = $routes->$node($this);
				}
			}
			elseif(
				isset($routes->routes)
				&& is_array($routes->routes)
			){
				foreach($routes->routes as $match => $route)
				{
					if($node === $match
						|| (preg_match('/^\W/', $match)
							&& preg_match($match, $node, $this->matches)
						)
					){
						Log::debug(sprintf(
							'Supplied url node "%s" matches "%s" on "%s"'
							, $node
							, $match
							, get_class($routes)
						));

						if($node !== $match)
						{
							$this->regex = $match;
						}

						$this->match = $node;
						$this->routedTo = $match;

						if(class_exists($route))
						{
							if(is_callable([$routes, '_preRoute'])
								&& $routes->_preRoute($this, $node) !== false
							){
								$routeObj = new $route;
								$router = new Static($this->request, $routeObj, $this);
								$result = $router->route();
								break;
							}
							else
							{
								$routeObj = new $route;
								$router = new Static($this->request, $routeObj, $this);
								$result = $router->route();

								if($result === false && is_callable([$routes, '_notFound']))
								{
									$this->match = false;
									$this->routedTo = false;

									$result = $routes->_notFound($this);
								}
								
								break;
							}
						}
						else
						{
							if(is_callable([$routes, '_preRoute']))
							{
								if($routes->_preRoute($router, $node) !== false)
								{
									$result	= $routes->$route($this);
								}
								else
								{
									if($result === false && is_callable([$routes, '_notFound']))
									{
										$this->match = false;
										$this->routedTo = false;

										$result = $routes->_notFound($this);
										break;
									}
								}
							}
							else
							{
								if(is_callable([$routes, $route]))
								{
									$result	= $routes->$route($this);
								}
								else
								{
									$result	= FALSE;
								}
								break;
							}
						}
					}
				}
			}

			if($result === FALSE && is_callable([$routes, '_dynamic']))
			{
				Log::debug(sprintf('Routing to _dynamic for url node "%s".', $node));

				$this->match = $node;
				$this->routedTo = '_dynamic';

				if(is_callable([$routes, '_preRoute']))
				{
					if($routes->_preRoute($this, $node) !== false)
					{
						$result = $routes->_dynamic($this);
					}
					else
					{
						if($result === false && is_callable([$routes, '_notFound']))
						{
							$this->match = false;
							$this->routedTo = false;

							$result = $routes->_notFound($this);
						}
					}
				}
				else
				{
					$result = $routes->_dynamic($this);
				}
			}
			
			if($result === false && is_callable([$routes, '_notFound']))
			{
				$this->match = false;
				$this->routedTo = false;

				$result = $routes->_notFound($this);
			}

			if(is_callable([$routes, '_postRoute']))
			{
				$result = $routes->_postRoute($this, $result, $preroutePath);
			}
			
		}
		catch(\SeanMorris\Ids\Http\HttpException $e)
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Caught HttpException of type %s'
				, get_class($e)
			), $e);

			\SeanMorris\Ids\Log::logException($e);

			if(!$this->subRouted)
			{
				$result = $e->getMessage();
				$status = $e->getCode();

				$e->onCatch();
			}
			else if($this->subRouted && $e instanceof \SeanMorris\Ids\Http\Http303)
			{
				throw new \SeanMorris\Ids\Http\Http303($this->path()->pathString(2));
			}
		}

		return $result;
	}

	public function &getContext()
	{
		\SeanMorris\Ids\Log::info(__FUNCTION__ . ' deprecated');
		return $this->context;
	}

	public function contextGet($name)
	{
		\SeanMorris\Ids\Log::info(__FUNCTION__ . ' deprecated');
		if(isset($this->context[$name]))
		{
			return $this->context[$name];
		}
	}

	public function contextSet($name, $value)
	{
		\SeanMorris\Ids\Log::info(__FUNCTION__ . ' deprecated');
		$this->context[$name] = $value;
	}

	public function request()
	{
		return $this->request;
	}

	public function routes()
	{
		return $this->routes;
	}

	public function subRoute(Request $request, Routable $routes = NULL)
	{
		$router = new static($request, $routes, $this, true);
		return $router->route();
	}

	public function subRouted()
	{
		return $this->subRouted;
	}

	public function resumeRouting(Routable $routes, Request $request = null, $subRouted = false)
	{
		if(!$request)
		{
			$request = $this->request;	
		}

		$router = new static($request, $routes, $this, $subRouted ? $subRouted : $this->subRouted);

		return $router->route();
	}

	public function path()
	{
		return $this->path;
	}

	public function parent()
	{
		return $this->parent;
	}

	public function child()
	{
		return $this->child;
	}

	public function match()
	{
		return $this->match;
	}

	public function routedTo()
	{
		return $this->routedTo;
	}

	public function regexMatch()
	{
		return $this->regex;
	}

	public function matches()
	{
		return $this->matches;
	}

	protected static function isStatic()
	{
		$trace = debug_backtrace();
        return $trace[1]['type'] == '::';
    }
}
