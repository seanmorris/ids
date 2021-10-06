<?php
namespace SeanMorris\Ids;
interface Logger
{
	public static function start($logBlob);
	public static function log($logBlob);
}
