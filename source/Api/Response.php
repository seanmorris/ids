<?php
namespace SeanMorris\Ids\Api;
class Response
{
	protected $handle, $request, $content, $encoding;

	public function __construct($request)
	{
		$this->request = $request;
	}

	public function setContent($content)
	{
		$this->content = $content;
	}

	public function setEncoding($encoding)
	{
		$this->encoding = $encoding;
	}

	public function send($content)
	{

		$headers  = $this->request->headers();
		$encoding = $this->encoding ?: $this->request->headers('Accept');

		$encodingSplit = explode('+', $encoding);
		$eventStream   = FALSE;

		if($encodingSplit[0] === 'text/event-stream')
		{
			$encoding = 'text/' . ($encodingSplit[1] ?? 'event-stream');
			$eventStream = TRUE;
		}
		else
		{
			$encoding = $encodingSplit[0];
		}

		switch($encoding)
		{
			case 'text/csv':
				$output = new \SeanMorris\Ids\Api\Output\Csv($headers);
				break;

			case 'text/tsv':
				$output = new \SeanMorris\Ids\Api\Output\Tsv($headers);
				break;

			case 'text/json':
				$output = new \SeanMorris\Ids\Api\Output\Json($headers);
				break;

			case 'text/yaml':
				$output = new \SeanMorris\Ids\Api\Output\Yaml($headers);
				break;

			case 'text/xml':
				$output = new \SeanMorris\Ids\Api\Output\Xml($headers);
				break;

			default:
			case 'text/plain':
				$encoding = 'text/plain';
				$output = new \SeanMorris\Ids\Api\Output\Plain($headers);
				break;
		}

		if($eventStream)
		{
			header('Cache-Control: no-cache');
			header('Content-Type: text/event-stream');
			header('Ids-Event-Type: ' . $encoding);

			foreach($output->pump($this->request->read()) as $chunk)
			{
				if($encodingSplit[2] ?? '' === 'uri')
				{
					yield new \SeanMorris\Ids\Http\Event(
						'data://' . $encoding . ',' . str_replace("\n", '%0A', trim($chunk))
					);
				}
				else
				{
					yield new \SeanMorris\Ids\Http\Event(trim($chunk));
				}
			}
		}
		else
		{
			if(($encodingSplit[1] ?? '') === 'uri')
			{
				header('Content-Type: text/plain');
			}
			else
			{
				header(sprintf('Content-Type: %s', $encoding));
			}

			foreach($output->pump($this->request->read()) as $chunk)
			{
				if(($encodingSplit[1] ?? '') === 'uri')
				{
					yield 'data://' . $encoding . ',' . str_replace("\n", '%0A', trim($chunk)) . PHP_EOL;
				}
				else
				{
					yield $chunk;
				}
			}
		}
	}
}
