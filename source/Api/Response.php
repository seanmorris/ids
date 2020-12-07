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

	public function send($encoding = 'text/plain')
	{
		$outputHeaders = $this->request->headers('Ids-Output-Headers') === 'true';
		$handle  = $this->request->getResponseBuffer();

		$content  = $this->content;
		$encoding = $this->encoding ?: $this->request->headers('Accept');

		switch($encoding)
		{
			case 'text/csv':
				$parser = new \SeanMorris\Ids\Api\Output\Csv($handle, $outputHeaders);
				break;

			case 'text/tsv':
				$parser = new \SeanMorris\Ids\Api\Output\Tsv($handle, $outputHeaders);
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
