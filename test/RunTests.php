<?php
namespace SeanMorris\Ids\Test;

$_SERVER['HTTP_HOST'] = 'test.dev';
chdir(dirname(__FILE__));
$composer = require '../source/init.php';
$testClass = __NAMESPACE__ . '\\' . $argv[1];

$test = new $testClass;

$test->run(new \TextReporter());
