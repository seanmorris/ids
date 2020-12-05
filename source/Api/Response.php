<?php
namespace SeanMorris\Ids\Api;
class Response
{
	protected $handle, $request, $content;

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

	public function send($encoding = 'text/plain')
	{
		$headers = $this->request->headers('Ids-Output-Headers') === 'true';
		$handle  = $this->request->getResponseBuffer();

		$content = $this->content;

		switch($this->encoding)
		{
			case 'text/csv':
				$parser = new \SeanMorris\Ids\Api\Output\Csv($handle, $headers);
				break;

			case 'text/tsv':
				$parser = new \SeanMorris\Ids\Api\Output\Tsv($handle, $headers);
				break;

			case 'text/json':
				$parser = new \SeanMorris\Ids\Api\Output\Json($handle);
				break;

			case 'text/yaml':
				$parser = new \SeanMorris\Ids\Api\Output\Yaml($handle);
				break;

			case 'text/plain':
			default:
				$parser = new \SeanMorris\Ids\Api\Output\Plain($handle);
				break;
		}

		$parser->parse($this->content);
	}
}
