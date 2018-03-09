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
		, $aliased
		, $match
		, $regex
		, $matches = []
		, $context = []
	;

	public function __construct(Request $request, Routable $routes, Router $parent = null, $subRouted = false, $aliased = FALSE)
	{
		$this->request = $request;
		$this->path = $request->path();
		$this->routes = $routes;
		$this->parent = $parent;
		$this->subRouted = $subRouted;
		$this->aliased = $aliased;

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

			$aliased = FALSE;

			if(isset($routes->alias[$node]))
			{
				if(!array_filter($path->nodes(), function($x){return isset($x);}))
				{
					$path->setAlias($routes->alias[$node]);
				}

				$node = $routes->alias[$node];
				$aliased = TRUE;
			}

			if(isset($routes->alias)
				&& ($i = array_search($node, $routes->alias)) !== FALSE
				&& !$aliased
				&& $path->remaining() <= 1
			){
				$path = $this->path()->pop();

				if($i !== 'index')
				{
					$path = $path->append($i);
				}

				throw new \SeanMorris\Ids\Http\Http303(
					$path->pathString()
					. ($_GET
						? '?' . http_build_query($_GET)
						: NULL
					)
				);
			}

			if(is_callable([$routes, $node]))
			{
				$this->match = $node;
				$this->routedTo = $node;

				Log::debug(sprintf('Routing to function %s for url node "%s". (%s)', $node, $node, get_class($routes)));

				if(is_callable([$routes, '_preRoute']))
				{
					if($routes->_preRoute($this, $node) !== false)
					{
						$result = $routes->$node($this);
					}
					else
					{
						//$result = $routes->$node($this);
						$result = NULL;

						//if($result === false && is_callable([$routes, '_notFound']))
						if(is_callable([$routes, '_notFound']))
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
						), $path);

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
								$router = new Static($this->request, $routeObj, $this, FALSE, $aliased);
								$result = $router->route();
								break;
							}
							else
							{
								$routeObj = new $route;
								$router = new Static($this->request, $routeObj, $this, FALSE, $aliased);
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
						else if(is_callable([$routes, $route]))
						{
							if(is_callable([$routes, '_preRoute']))
							{
								if($routes->_preRoute($this, $node) !== false)
								{
									$result	= $routes->$route($this);
									break;
								}
							}
							else
							{
								if(is_callable([$routes, $route]))
								{
									$result	= $routes->$route($this);
									break;
								}
							}
						}
						else
						{
							Log::error($errorMessage = sprintf(
								'Supplied url node "%s" matches "%s" on "%s"'
									. PHP_EOL
									. 'But, "%s" is not a valid classname or function under %s.'
								, $node
								, $match
								, get_class($routes)
								, $route
								, get_class($routes)
							));

							throw new \Exception($errorMessage);
						}

						if($result === false && is_callable([$routes, '_notFound']))
						{
							$this->match = false;
							$this->routedTo = false;

							$result = $routes->_notFound($this);
						}

						Log::error(sprintf(
							'No valid route for "%s" on "%s"!!!'
							, $node
							, get_class($routes)
						));
					}
				}
			}

			if($result === FALSE && is_callable([$routes, '_dynamic']))
			{
				Log::debug(sprintf(
					'Routing to _dynamic for url node "%s" for routes %s.'
					, $node
					, get_class($routes)
				));

				$this->match = $node;
				$this->routedTo = '_dynamic';

				if(is_callable([$routes, '_preRoute']))
				{
					if($routes->_preRoute($this, $node) !== false)
					{
						$result = $routes->_dynamic($this);
					}
					
					if($result === false && is_callable([$routes, '_notFound']))
					{
						$this->match = false;
						$this->routedTo = false;

						$result = $routes->_notFound($this);
					}
				}
				else
				{
					$result = $routes->_dynamic($this);
				}
			}

			if($result instanceof \SeanMorris\Ids\Http\HttpResponse
				|| $result instanceof \SeanMorris\Ids\Http\HttpException
			){
				if($this->parent())
				{
					throw $result;
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
		catch(\SeanMorris\Ids\Http\HttpDocument $e)
		{
			if(!\SeanMorris\Ids\Idilic\Cli::isCli())
			{
				$e->onCatch($this);
			}
			return $e;
		}
		catch(\SeanMorris\Ids\Http\HttpResponse $e)
		{
			if(!\SeanMorris\Ids\Idilic\Cli::isCli())
			{
				$e->onCatch($this);
			}
			$result = $e->getMessage();
		}
		catch(\SeanMorris\Ids\Http\HttpException $e)
		{
			\SeanMorris\Ids\Log::debug(sprintf(
				'Caught HttpException of type %s'
				, get_class($e)
			), $e);

			\SeanMorris\Ids\Log::logException($e, TRUE);

			$result = $e->getMessage();

			if(!$this->subRouted)
			{
				$result = $e->getMessage();
				if(!\SeanMorris\Ids\Idilic\Cli::isCli())
				{
					$e->onCatch($this);
					die;
				}
				else if($e instanceof \SeanMorris\Ids\Http\Http303)
				{
					$subRequest = new Request(['uri' => $e->getMessage()]);

					$router = new static($subRequest, $routes, $this);
					
					return $router->route();
				}
				else
				{
					return FALSE;
				}
			}
			else if($this->subRouted && $e instanceof \SeanMorris\Ids\Http\Http303)
			{
				throw new \SeanMorris\Ids\Http\Http303($this->path()->pathString(2), 303, $e);
			}
		}

		return $result;
	}

	public function &getContext()
	{
		// \SeanMorris\Ids\Log::info(__FUNCTION__ . ' deprecated');
		return $this->context;
	}

	public function contextGet($name, $default = NULL)
	{
		// \SeanMorris\Ids\Log::info(__FUNCTION__ . ' deprecated');
		if(isset($this->context[$name]))
		{
			return $this->context[$name];
		}

		return $default;
	}

	public function contextSet($name, $value)
	{
		// \SeanMorris\Ids\Log::info(__FUNCTION__ . ' deprecated');
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

	public function aliased()
	{
		return $this->aliased;
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

	public function root()
	{
		$root = $parent = $this;
		while($parent = $parent->parent())
		{
			$root = $parent;
		}

		return $root;
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
