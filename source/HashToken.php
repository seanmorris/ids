<?php
namespace SeanMorris\Ids;
class HashToken
{
	const STD_EXPIRY = 300
		, SEPARATOR  = '::'
		, HASH_COUNT = 2**16;
	
	public static function getToken($userKey, $secret, $life = self::STD_EXPIRY, $emergeTime = 0)
	{
		$time = time();
		
		$expiry = $time + $life + $emergeTime;
		$emerge = $time + $emergeTime;
		
		$source = implode(
			static::SEPARATOR
			, array($emerge, $expiry, $secret, $userKey)
		);
		
		$hash = hash('sha256', $source);
		$hashCount = 0;

		while(++$hashCount < static::HASH_COUNT)
		{
			$hash = hash('sha256', $hash);
		}
		
		$token  = implode(
			static::SEPARATOR
			, array(
				dechex($emerge)
				, $hash
				, dechex($expiry)
			)
		);
		
		return strtoupper($token);
	}
	
	public static function checkToken($token, $userKey, $secret)
	{
		$time = time();
		$parts = explode(static::SEPARATOR, $token);

		if(count($parts) !== 3)
		{
			return false;
		}

		list($emgHintHex, $tokenHash, $expHintHex) = $parts;
		
		$emgHint      = hexdec($emgHintHex);
		$expHint      = hexdec($expHintHex);

		$source   = implode(
			static::SEPARATOR
			, array(
				$emgHint
				, $expHint
				, $secret
				, $userKey
			)
		);
		
		$testHash = hash('sha256', $source);

		$hashCount = 0;

		while(++$hashCount < static::HASH_COUNT)
		{
			$testHash = hash('sha256', $testHash);
		}

		return(
			($tokenHash  ==  strtoupper($testHash))
			&& ($emgHint <=  $time)
			&& ($expHint >   $time)
		);
	}
}
