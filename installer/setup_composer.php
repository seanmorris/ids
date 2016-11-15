<?php
$composerJson = $argv[1];
$packageName = $argv[2];
$domain = $argv[3];
$namespace = preg_replace('/\//', '\\', $packageName);
$namespace = preg_replace_callback('/\b(\w)/', function($m){return ucwords($m[1]);}, $namespace);
$enamespace = str_replace('\\', '\\\\', $namespace);

$json = json_decode(file_get_contents($composerJson));

//var_dump($json);

$json->autoload = (object)[];
$json->autoload->{"psr-4"} = (object)[];
$json->autoload->{"psr-4"}->{$namespace . '\\'    } = 'source/';
$json->autoload->{"psr-4"}->{$namespace . '\\Test\\'} = 'test/';

file_put_contents(
	$composerJson
	, json_encode(
		$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	)
);

file_exists('./source/Route') || mkdir('./source/Route');

$indexRoute = <<<EOF
<?php
namespace $namespace\Route;
class IndexRoute implements \SeanMorris\Ids\Routable
{
	public function index()
	{
		return 'Hello, world!';
	}
}
EOF;

file_put_contents(
	'./source/Route/IndexRoute.php'
	, $indexRoute
);

$site_conf = <<<EOF
{
	"entrypoint"  : "$enamespace\\\\Route\\\\IndexRoute"
	, "devmode"   : false
	, "databases" : {
		"main" : {
			"connection" : "mysql:dbname=testsite;host=localhost;"
			, "username" : "sean"
			, "password" : ""
		}
	}
	, "public"    : "/home/sean/testsite/public"
	, "logLevel"  : "off"
}
EOF;

file_put_contents(
	'./data/local/sites/' . $domain . '.json'
	, $site_conf
);

unlink('./data/local/sites/example.com.json');