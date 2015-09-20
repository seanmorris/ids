<?php
namespace SeanMorris\Ids;
class View
{
	protected $vars = [];

	public function __construct($vars = [])
	{
		$this->update($vars);
	}

	public function update($vars)
	{
		$this->vars = $vars;

		$this->preprocess();
	}

	protected function preprocess()
	{
		
	}

	public function render($vars = [])
	{
		$vars = $this->vars + $vars;
		$className = get_called_class();

		do {
			$reflection = new \ReflectionClass($className);
			$classFile = $reflection->getFileName();

			$fileContent = file_get_contents($classFile);
			$tokens = token_get_all($fileContent);

			$hasHalt = (bool)array_filter($tokens, function($token){
				return isset($token[0]) && $token[0] == T_HALT_COMPILER;
			});

			if($hasHalt)
			{
				$template = $tokens[ count($tokens)-1 ][1];
				break;
			}

			$className = get_parent_class($className);
    
			if(!$className || $className == get_class())
			{
				throw new \Exception(sprintf(
					'Cannot locate template. '
					. 'No call to __halt_compiler() found along inheritance chain of %s.'
					, get_called_class()
				));
			}

		} while(!$hasHalt);

		$renderScope = function() use($template, $vars, $classFile)
		{
			extract($vars);
			ob_start();

			try{
				eval($template);
			}
			catch(\Exception $e)
			{
				Log::debug('Uh-oh: ' . $classFile . ':' . $e->getLine());
				throw $e;
			}
			
			
			$content = ob_get_contents();
			ob_end_clean();

			return $content;
		};

		return $renderScope();
	}

	public function __toString()
	{
		try{
			$result = $this->render();
		} catch (\Exception $e) {
			Log::debug($e);
			$result = '!!!';
		}

		return (string)$result;
	}
}