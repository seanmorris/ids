<?php
namespace SeanMorris\Ids;
class Path
{
	protected 
		$counter = -1
		, $nodes = []
		, $nodeAlias = []
	;

	public function __construct()
	{
		$this->nodes = func_get_args();
	}

	public function getNode($i = 0)
	{
		if(isset($this->nodes[$this->counter + $i]))
		{
			return $this->nodes[$this->counter + $i];
		}
	}

	public function append(...$nodes)
	{
		$newPath = clone $this;
		
		foreach($nodes as $node)
		{
			$newPath->nodes[] = $node;
		}

		$newPath->counter--;

		return $newPath;
	}

	public function pop(&$node = null)
	{
		$newPath = clone $this;

		$newPath->nodes = [];
		
		foreach($this->nodes as $node)
		{
			$newPath->nodes[] = $node;
		}

		$node = array_pop($newPath->nodes);

		return $newPath;
	}

	public function getSpentPath()
	{
		$newPath = clone $this;
		$newPath->nodes = $this->getSpentNodes();
		$newPath->counter = $this->counter;

		return $newPath;
	}

	public function getAliasedPath()
	{
		$path = clone $this;
		$path->nodes = $this->nodeAlias + $this->nodes;

		ksort($path->nodes);

		return $path;
	}

	public function getSpentNodes()
	{
		$spentNodes = [];

		foreach($this->nodes as $key => $node)
		{
			if($key < $this->counter)
			{
				$spentNodes[] = $node;
			}
		}

		return $spentNodes;
	}

	public function consumeNode()
	{
		if($this->counter <= count($this->nodes))
		{
			//++$this->counter;
		}

		++$this->counter;

		return $this->getNode();
	}

	public function unconsumeNode()
	{
		return $this->counter--;
	}

	public function consumeNodes()
	{
		$args = [];
		
		while($node = $this->consumeNode())
		{
			$args[] = $node;
		}

		return $args;
	}

	public function done()
	{
		if(!count($this->nodes) || $this->counter >= count($this->nodes))
		{
			return true;
		}

		return false;
	}

	public function string()
	{
		return $this->pathString();
	}

	public function pathString($depth = 0)
	{
		$nodes = $this->nodes;

		while($depth-- > 0)
		{
			array_pop($nodes);
		}

		return implode('/', $nodes);
	}

	public function reset()
	{
		$this->counter = -1;
	}

	public function nodes()
	{
		return $this->nodes;
	}

	public function count()
	{
		return count($this->nodes);
	}

	public function remaining()
	{
		return count($this->nodes) - $this->counter; 
	}

	public function setAlias($alias)
	{
		$this->nodeAlias[$this->counter] = $alias;	
	}

	public function getAlias($nodeKey)
	{
		if(isset($this->nodes[$nodeKey]))
		{
			return $this->nodeAlias[$nodeKey];
		}

		if(isset($this->nodes[$nodeKey]))
		{
			return $this->nodes[$nodeKey];
		}
	}
}