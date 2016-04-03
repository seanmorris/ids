<?php
namespace SeanMorris\Ids;
class HashToken
{
	const STD_EXPIRY = 300;
	
	public static function getToken($userKey, $secret, $life = self::STD_EXPIRY, $emergeTime = 0)
	{
		$time = time();
		
		$expiry = $time + $life + $emergeTime;
		$emerge = $time + $emergeTime;
		
		$source = implode('/',
			array($emerge, $expiry, $time, $secret, $userKey)
		);
		
		$hash   = hash('sha256', $source);
		
		$token  = implode( '/',
			array(
				dechex($time)
				, dechex($emerge)
				, $hash
				, dechex($expiry)
			)
		);
		
		return strtoupper($token);
	}
	
	public static function checkToken($token, $userKey, $secret)
	{
		$time = time();
		list($baseTimeHintHex, $emgHintHex, $tokenHash, $expHintHex) = explode('/', $token);
		
		$baseTimeHint = hexdec($baseTimeHintHex);
		$emgHint      = hexdec($emgHintHex);
		$expHint      = hexdec($expHintHex);
		
		$source   = implode(
			'/', 
			array(
				$emgHint
				, $expHint
				, $baseTimeHint
				, $secret
				, $userKey
			)
		);
		
		$testHash = strtoupper(hash('sha256', $source));

		return(
			($tokenHash == $testHash)
			&&
			(
				($expHint >  $time || $expHint == $baseTimeHint)
				&&
				($emgHint <= $time || $emgHint == $baseTimeHint)
			)
		);
	}
}
