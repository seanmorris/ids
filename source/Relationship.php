<?php
namespace SeanMorris\Ids;
class Relationship extends Model
{
	protected
		$ownerClass
		, $ownerId
		, $property
		, $subjectId
		, $delta
		, $ownerObject
		, $subjectObject
	;

	protected static
		$table = 'IdsRelationship'
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
	;

	public function owner()
	{
		if(!$this->ownerObject)
		{
			$ownerClass = $this->ownerClass;

			$this->ownerObject = $ownerClass::loadOneById($this->ownerId);
		}

		return $this->ownerObject;
	}

	public function subject()
	{
		if(!$this->subjectObject)
		{
			$owner = $this->ownerObject;

			$subjectClass = $owner->getSubjectClass($this->property);

			$this->subjectObject = $subjectClass::loadOneById($this->subjectId);
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
			$subjectClass = $owner->getSubjectClass($column);
			$subject = $subjectClass::instantiate($skeleton);

			$instance = parent::instantiate($skeleton, $args);
			
			$instance->ownerObject = $owner;
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

	protected static function resolveDef($name, &$args = [])
	{
		// \SeanMorris\Ids\Log::debug($name, $args);

		$def = parent::resolveDef($name, $args);

		preg_match('/.+?By(.+)$/', $name, $match);

		if(isset($match[1]) && $match[1] == 'Owner')
		{
			$_args = $args;

			$owner = array_shift($_args);
			$column = array_shift($_args);

			if($subjectClass = $owner->getSubjectClass($column))
			{
				array_splice($args, 1, 0, [get_class($owner)]);

				// \SeanMorris\Ids\Log::debug($args, $_args);
				// \SeanMorris\Ids\Log::debug([$name, $owner, $column]);

				
				$def['join'][$subjectClass] = [
					'on' => 'subjectId'
					, 'by' => 'moderated'
					, 'type' => 'LEFT'
				];
			}
		}

		return $def;
	}
}