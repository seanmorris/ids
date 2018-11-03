<?php
namespace SeanMorris\Ids;
class Relationship extends Model
{
	protected
		$ownerClass
		, $ownerId
		, $property
		, $subjectId
		, $subjectClass
		, $delta
		, $ownerObject
		, $subjectObject
	;

	protected static
		$table = 'PressKitRelationship'
		, $ignore = [
			'ownerObject'
			, 'subjectObject'
		]
		, $byOwner = [
			'where' => [
				['ownerId' => '?']
				, ['ownerClass' => '?']
				, ['property' => '?']
			]
			, 'order' => ['delta' => 'ASC']
		]
		, $bySubject = [
			'where' => [
				['subjectId' => '?']
				, ['subjectClass' => '?']
			]
			, 'order' => ['ownerClass' => 'ASC', 'ownerId' => 'ASC']
		]
	;

	public function owner()
	{
		if(!$this->ownerObject)
		{
			$ownerClass = $this->ownerClass;

			if(!class_exists($ownerClass))
			{
				return FALSE;
			}

			$this->ownerObject = $ownerClass::loadOneById($this->ownerId);
		}

		return $this->ownerObject;
	}

	public function subject()
	{
		$ownerClass = $this->ownerClass;

		if(!class_exists($ownerClass))
		{
			throw new \Exception(sprintf(
				'Unabled to find referenced class %s.'
				, $ownerClass
			));
		}

		// $subjectClass = $ownerClass::getSubjectClass($this->property);
		$subjectClass = $this->subjectClass;

		if(!$this->subjectObject)
		{
			if(!class_exists($subjectClass))
			{
				return FALSE;
			}
			else
			{
				throw new \Exception(sprintf(
					'Unabled to find referenced class %s.'
					, $subjectClass
				));
			}

			$this->subjectObject = $subjectClass::loadOneById($this->subjectId);
		}
		else
		{
			Log::warn(sprintf(
				'Unabled to find referenced %s[%s].'
				, $subjectClass
				, $this->subjectId
			));
		}

		return $this->subjectObject;
	}

	protected static function instantiate($skeleton, $args = [], $rawArgs = [])
	{
		$owner = array_shift($rawArgs);
		$ownerClass = array_shift($rawArgs);
		$column = array_shift($rawArgs);

		// \SeanMorris\Ids\Log::debug([$owner, $column]);

		if($owner && $column)
		{
			// \SeanMorris\Ids\Log::debug([$owner, $column, $owner->getSubjectClass($column)]);
			// $subjectClass = $owner->getSubjectClass($column);

			$instance = parent::instantiate($skeleton, $args);

			$subjectClass = $instance->subjectClass;
			$subject      = $subjectClass::instantiate($skeleton);

			$instance->ownerObject   = $owner;
			$instance->subjectObject = $subject;

		}
		else
		{
			$instance = parent::instantiate($skeleton, $args);

			$instance->owner();
			$instance->subject();
		}

		return $instance;
	}

	protected static function resolveDef($name, &$args = [], $parentSelect = NULL)
	{
		//\SeanMorris\Ids\Log::debug( "RELATIONSHIP RESOLVEDEF\n" );
		// \SeanMorris\Ids\Log::debug($name, $args);
		$def = parent::resolveDef($name, $args);

		preg_match('/.+?By(.+)$/', $name, $match);

		//var_dump($match);

		if(isset($match[1]) && $match[1] == 'Owner')
		{
			$_args = $args;

			$owner = array_shift($_args);
			$column = array_shift($_args);

			$ownerClass = is_string($owner) ? $owner : get_class($owner);

			// @TODO: Eliminate dirty hack.
			$joinBy = 'Null';
			if($_args)
			{
				if(is_string($owner))
				{
					$joinBy = $column;

					$joinBy = preg_replace('/^by/', NULL, $joinBy);
				}
				$column = array_shift($_args);
			}

			//\SeanMorris\Ids\Log::debug($owner, $column, $owner::getSubjectClass($column));

			if($subjectClass = $owner::getSubjectClass($column))
			{
				array_splice($args, 1, 0, [$ownerClass]);

				// \SeanMorris\Ids\Log::debug($args, $_args);
				// \SeanMorris\Ids\Log::debug([$name, $owner, $column]);
				$def['join'][$subjectClass] = [
					'on' => 'subjectId'
					, 'by' => isset($def['by']) ? $def['by'] : $joinBy
					, 'type' => 'INNER'
				];
				if(is_string($owner))
				{
					$def['where'] = [
						['ownerId'      => sprintf('%s.id', $parentSelect->tableAlias())]
						, ['ownerClass' => sprintf('"%s"', addslashes($owner))]
						, ['property'   => sprintf('"%s"', $column)]
					];
				}
			}
		}
		if(isset($match[1]) && $match[1] == 'Subject')
		{
			$_args = $args;

			$subject = array_shift($_args);
			
			if(is_object($subject))
			{
				$args = [$subject->id, get_class($subject)];
			}
		}

		//\SeanMorris\Ids\Log::debug( "RELATIONSHIP RESOLVEDEF END\n" );	
		return $def;
	}
}
