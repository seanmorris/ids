<?php
namespace SeanMorris\Ids;
class Mail
{
	protected
		$recipients = []
		, $subject = NULL
		, $body = NULL
	;

	public function to(...$recipients)
	{
		$this->recipients = [];
		foreach($recipients as $recipient)
		{
			$this->recipients[]= $recipient;
		}
	}

	public function subject($subject)
	{
		$this->subject = $subject;
	}

	public function body($body)
	{
		$this->body = $body;
	}

	public function send($real = FALSE)
	{
		if($real)
		{
			\SeanMorris\Ids\Log::debug(
				'SENDING REAL MAIL...'
				, sprintf('To:      [%s]', implode(', ', $this->recipients))
				, sprintf('Subject: %s', $this->subject)
				, sprintf("Body:\n%s", $this->body)
				, '----'
			);

			mail(
				$this->recipients[0]
				, $this->subject
				, $this->body
			);
		}
		else
		{
			\SeanMorris\Ids\Log::debug(
				'SENDING FAKE MAIL...'
				, sprintf('To:      [%s]', implode(', ', $this->recipients))
				, sprintf('Subject: %s', $this->subject)
				, sprintf("Body:\n%s", $this->body)
				, '----'
			);
		}
	}
}