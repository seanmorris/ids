<?php
namespace SeanMorris\Ids\Api\Input;

abstract class XmlValue{};
class XmlArrayValue extends XmlValue{};
class XmlObjectValue extends XmlValue{};

class Xml extends \SeanMorris\Ids\Api\InputPump
{
	public function __construct($handle, $headers = [])
	{
		parent::__construct($handle, $headers);
	}

	public function pump()
	{
		$meta = stream_get_meta_data($this->handle);

		$reader = new \XMLReader();
		$reader->open($meta['uri']);

		$current = null;
		$parentKeys = [];
		$parents = [];

		while($reader->read())
    	{
    		if($reader->nodeType === \XMLReader::ELEMENT)
    		{
				if($reader->name === 'item')
				{
					$current = null;
				}

				if($reader->name === 'scalar'
					|| $reader->name === 'array'
					|| $reader->name === 'object'
				){
					$type = $reader->name;
					$attr = [];

					while($reader->moveToNextAttribute())
                	{
                		$attr[$reader->name] = $reader->value;
                	}

					if($type === 'scalar')
					{
						if(isset($attr['key']))
						{
							if(is_object($current))
							{
								$current->{ $attr['key'] } = $attr['value'] ?? '';
							}
							else if(is_array($current))
							{
								$current[ $attr['key'] ] = $attr['value'] ?? '';
							}
							else
							{
								$current = $attr['value'];
							}
						}
					}

					if($type === 'object' || $type === 'array')
					{
						$parents[] = $current;

						$_type = $type === 'object'
							? XmlObjectValue::CLASS
							: XmlArrayValue::CLASS;

						if(isset($attr['key']))
						{
							$current->{ $attr['key'] } = new $_type;

							$current = $current->{ $attr['key'] };
						}
						else
						{
							$current = new $_type;
						}
					}
                }
    		}

    		if($reader->nodeType === \XMLReader::END_ELEMENT)
    		{
    			$type = $reader->name;

    			if($type === 'array' || $type === 'object')
    			{
	    			if($parent = array_pop($parents))
	    			{
	    				$current = $parent;
	    			}
				}

				if($type === 'item')
    			{
    				static::postProcess($current);

					yield $current;
    			}
    		}
    	}
	}

	public static function postProcess(&$current)
	{
		if(is_scalar($current))
		{
			return;
		}

		if($current instanceof XmlArrayValue)
		{
			$current = (array) $current;
		}
		else if($current instanceof XmlObjectValue)
		{
			$current = (object) (array) $current;
		}

		foreach($current as $key => &$value)
		{
			if(!is_scalar($value))
			{
				static::postProcess($value);
			}
		}
	}
}
