<?php
namespace SeanMorris\Ids;

/**
 * Manges communication with spawned processes. Allows the main program
 * to communicate with the child (optionally) asyncronously.
 */

class ChildProcess
{
	protected
		$command
		, $process
		, $errorCode = -1
		, $stdIn
		, $stdOut
		, $stdErr
		, $stdOutBuffer = ''
		, $stdErrBuffer = ''
		, $pipeDescriptor = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		]
	;

	public function __construct($command, $asyncOut = FALSE, $asyncIn = FALSE)
	{
		$this->command = $command;
		$this->process = proc_open(
			$command
			, $this->pipeDescriptor
			, $pipes
		);

		[$this->stdIn, $this->stdOut, $this->stdErr] = $pipes;

		stream_set_blocking($this->stdIn,  !$asyncIn);
		stream_set_blocking($this->stdOut, !$asyncOut);
		stream_set_blocking($this->stdErr, !$asyncOut);
	}

	public function write($record)
	{
		if(is_resource($this->process))
		{
			return fwrite($this->stdIn, $record);
		}
		return FALSE;
	}

	public function get($bytes)
	{
		return fread($this->stdOut, $bytes);
	}

	public function getError($bytes)
	{
		return fread($this->stdErr, $bytes);
	}

	public function read()
	{
		$got = fgets($this->stdOut);

		if(!$this->feof() && substr($got, -1) !== "\n")
		{
			$this->stdOutBuffer .= $got;
			return;
		}
		else
		{
			$message = $this->stdOutBuffer .= $got;

			$this->stdOutBuffer = NULL;

			return $message;
		}

		return $got;
	}

	public function readAll()
	{
		if($this->feof())
		{
			return;
		}

		return stream_get_contents($this->stdOut);
	}

	public function readError()
	{
		$got = fgets($this->stdErr);

		if(!$this->feofError() && substr($got, -1) !== "\n")
		{
			$this->stdErrBuffer .= $got;
			return;
		}
		else
		{
			$message = $this->stdErrBuffer .= $got;

			$this->stdErrBuffer = NULL;

			return $message;
		}

		return $got;
	}

	public function readAllError()
	{
		if($this->feofError())
		{
			return;
		}

		return stream_get_contents($this->stdErrBuffer);
	}

	public function feof()
	{
		return feof($this->stdOut);
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

		$status = proc_get_status($this->process);

		return !$status['running'];
	}

	public function kill()
	{
		is_resource($this->process) && proc_close($this->process);
	}

	public function errorCode()
	{
		if(!$this->feof() || $this->errorCode !== -1)
		{
			return $this->errorCode;
		}

		$status = proc_get_status($this->process);

		if($status['running'] === FALSE)
		{
			$this->errorCode = $status['exitcode'];
		}

		return $this->errorCode;
	}
}
