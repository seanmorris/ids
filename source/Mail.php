<?php
namespace SeanMorris\Ids;
class Mail
{
	protected
		$recipients = []
		, $from     = NULL
		, $subject  = NULL
		, $body     = NULL
	;

	public function to(...$recipients)
	{
		$this->recipients = [];

		foreach($recipients as $recipient)
		{
			$this->recipients[]= $recipient;
		}
	}

	public function from($email)
	{
		$this->from = $email;
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
				, sprintf('From: %s', $this->from)
				, sprintf('Subject: %s', $this->subject)
				, sprintf("Body:\n%s", $this->body)
				, '----'
			);

			mail(
				implode(", ", $this->recipients)
				, $this->subject
				, $this->body
				, sprintf(
					'From: %s' . "\r\n"
					, $this->from
				)
			);
		}
		else
		{
			\SeanMorris\Ids\Log::debug(
				'SENDING FAKE MAIL...'
				, sprintf('To:      [%s]', implode(', ', $this->recipients))
				, sprintf('From: %s', $this->from)
				, sprintf('Subject: %s', $this->subject)
				, sprintf("Body:\n%s", $this->body)
				, '----'
			);
		}
	}
}