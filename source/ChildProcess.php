<?php
namespace SeanMorris\Ids;
class ChildProcess
{
	protected
		$command
		, $process
		, $stdIn
		, $stdOut
		, $stdErr
		, $pipeDescriptor = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		)
	;

	public function __construct($command, $async = FALSE)
	{
		$this->command = $command;
		$this->process = proc_open(
			$command
			, $this->pipeDescriptor
			, $pipes
		);

		list($this->stdIn, $this->stdOut, $this->stdErr) = $pipes;

		stream_set_blocking($this->stdIn,  !$async);
		stream_set_blocking($this->stdOut, !$async);
		stream_set_blocking($this->stdErr, !$async);
	}

	public function write($record)
	{
		fwrite($this->stdIn, $record);
	}

	public function read()
	{
		return trim(fgets($this->stdOut));
	}

	public function feof()
	{
		return feof($this->stdOut);
	}

	public function readError()
	{
		return trim(fgets($this->stdErr));
	}

	public function feofError()
	{
		return feof($this->stdErr);
	}

	public function isDead()
	{
		if(!is_resource($this->process))
		{
			return TRUE;
		}

		if(!$this->feof())
		{
			return FALSE;
		}

		$status = proc_get_status($this->process);

		return !$status['running'];
	}

	public function kill()
	{
		is_resource($this->process) && proc_close($this->process);
	}
}
