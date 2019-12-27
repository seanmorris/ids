![avatar](https://avatars3.githubusercontent.com/u/640101?s=80&v=4)

# SeanMorris/Ids v00.0.0

*/ eye dee ess /*

The PHP/Docker Framework

![seanmorris-ids](https://img.shields.io/badge/seanmorris-ids-red?style=for-the-badge) [![Travis (.org)](https://img.shields.io/travis/seanmorris/ids?style=for-the-badge)](https://travis-ci.org/seanmorris/ids) ![Size badge](https://img.shields.io/github/languages/code-size/seanmorris/ids?style=for-the-badge) [![Apache-2.0 Licence Badge](https://img.shields.io/npm/l/cv3-inject?color=338800&style=for-the-badge)](https://github.com/seanmorris/cv3-inject/blob/master/LICENSE)

The Ids library provides general domain-primitives for developing web based applications. Routing, requests, modeling configuration, logging, sessions, and database access are all abstracted behind simple, expressive interfaces to efficient and powerful code.

To prevent unexpected behaviro, the system is set to die on all errors down to `E_NOTICE`, excluding `E_DEPRECATED` errors generated from the vendor directory.

The project is made to run in docker but doesn't require it. It can be included in any composer project, and used in part or in whole easily.

The philosophy of the Ids project is headlined by security, speed and easy of use, in that order.

## Installation

Include Ids in your project with:

```bash
$ composer require seanmorris/ids
```

Install Ids globally for access to the `idilic` cli tool:

```bash
$ composer global require seanmorris/ids:dev-master
```

Add composer's global `vendor/bin` to your PATH by adding this to your `~/.bashrc`.

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

## Creating a New Ids Project

Create a new project with composer, enter the directory and start php, apache & mysql:

```bash
$ composer create-project seanmorris/ids-project -s dev --remove-vcs
$ cd ids-project
$ make @dev start-bg
```

Thats it!

The companion package that provides a template for new projects can be found here:

https://github.com/seanmorris/ids-project

## Dependencies

* Composer
* Docker
* Docker Compose
* GNU Make
* Git
* Linux or Compatible OS
* PHP
* SimpleTest/SimpleTest
* Minikube (required for kubernetes targets only)

## Dev Tools

The `dev` build target provides facilities for connecting to xdebug and graylog.

### XDebug

XDebug is built into the `dev` images by default. You can configure it by setting `XDEBUG_CONFIG_` environment variables in `.env.dev`. By default it will attempt to connect to port 9000 on `${DHOST_IP}`, which is the machine runing the project.

### GrayLog

`\SeanMorris\Ids\Logger\Gelf` provides a simple interface to graylog. Just add it to the `IDS_LOGGERS_` environment variable to enable it. So long as there is a graylog server available , it will send all logs that meet the verbosity threshold.


The default graylog config for the `dev` target looks like:

```
IDS_LOGGERS_=\SeanMorris\Ids\Logger\Gelf
IDS_GRAYLOG_HOST=graylog
IDS_GRAYLOG_PORT=12201
```

This package comes with a default GELF TCP input in its graylog config backup. You can run `make graylog-restore` after starting graylog for the first time to create the input.

The graylog config can be backed up and restored with the following commands:

```bash
$ make graylog-backup     # alias glbak
$ make graylog-restore    # alias glres
```

Graylog can be started and stopped with the following commands:

```bash
$ make graylog-start      # alias gls
$ make graylog-start-fg   # alias glsf
$ make graylog-start-bg   # alias glsb


$ make graylog-stop       # alias gld

$ make graylog-restart    # alias glr
$ make graylog-restart-fg # alias glrf
$ make graylog-start-bg   # alias glrb
```

## Idilc CLI

Ids comes with the `idilic` command when installed globally with composer. When executed, it will ascend through the current path looking for another project including Ids. When it finds it, it will attach to that project and utilize its facilities.

If it encounters a local `idilic` pass-thru in the project directory, it will hand control to the docker environment where execution will continue.

Run `idilic help` to see actions exposed by any installed packages.

## Multistaging / Environment Targets

### base, prod, dev, and test

By default Ids provides 4 build targets: base, prod, dev, and test. Each exposes different ports, so they may run without conflict in parallel.

* **base** exposes no ports and builds **without** require-dev.

* **prod** exposes port 80 (configurable by `IDS_EXPOSE_HTTP`) and port 443* ( configurable by `IDS_EXPOSE_HTTPS` ) and builds **without** require-dev.

* **test** exposes port 2021 (configurable by `IDS_EXPOSE_HTTP`) and port 3031 ( `IDS_EXPOSE_SQL` ) and builds **with** require-dev.

* **dev** exposes port 2020 ( configurable by `IDS_EXPOSE_HTTP` ) and port 3030 ( `IDS_EXPOSE_SQL` ) and builds **with** require-dev.

\*Not yet implemented.

### Switching Targets

The system will use the TARGET environment variable to decide which build target to use.

If youre on the BASH shell simply run one of the following commands to set the target:

* `make stay@base`
* `make stay@dev`
* `make stay@test`
* `make stay@prod`

### Extending Environments

Additional targets may be specified by creating a new `infra/compose/[TARGET].yml` in your project. Build this file as you'd build any other docker-compose file.

Then create a new `.env.[TARGET]` file in `config/`. Add your defaults here.

If you need some target specific build steps. then add a `FROM base as TARGET` section to any docker files in `infra/docker` that are relevant to that build target. You can expand on any existing build target, you're not limited to extending `base`.

## Build

The project may be built with `make`. Debian users can get this tool by running `apt-get install build-essential`. The build process also requires `docker` and `docker-compose`. `minikube` is required only for kubernetes testing.

The default build target is `base`. run `make stay@dev` or `make stay@test` to switch to the development/testing target. You can use @TARGET at the beginning of any make command to use the target for just that command.

```bash
$ make @dev build # build the project

$ make @dev start # start the services
```

Docker & docker-compose are available here:

* https://docs.docker.com/install/
* https://docs.docker.com/compose/install/

## Start / Stop / Restart

```bash
$ make start      # Start the project in the background,
                  # with no output

$ make start-fg   # Start the project in the foreground.

$ make start-bg   # Start the project in the background,
                  # stream output to foreground.

$ make stop       # Stop all services defined for the target.

$ make stop-all   # Stop all services spawned for the target
                  # even ones no longer in target compose file.

$ make restart    # Stop, then restart the project in the background,
                  # with no output.

$ make restart-fg # Stop, then restart the project in the foreground.

$ make restart-bg # Stop, then restart the project in the background,
                  # stream output to foreground.
```

## Images & Tags

```bash
$ make list-images # List all images for the current project, target & branch.

$ make list-tags   # List all tags for the current project, target & branch.

$ make push-images # push all images for the current project, target & branch.

$ make pull-images # push all images for the current project, target & branch.
```

## Autotagging / Autopublishing / Git Hooks

Register git hooks with `make hooks`.

Image tags are generated automatically on build based on the date, current git tag (falls back to commit hash).

The master branch will generate:

*  repository/project:gitTag-target
*  repository/project:date-target
*  repository/project:latest-target

Branches other than master will generate:

*  repository/project:gitTag-target-branch
*  repository/project:date-target-branch
*  repository/project:latest-target-branch

Images will be built on `git commit` and pushed on `git push` if the current branch appears in the project root `.publishing` file in the form: `BRANCH:TARGET`. For example this file would push images for prod & test when `git push` is run for the master branch:

```
master:test
master:prod
```
## Available Images

Docker images for seanmorris/ids.idilic & seanmorris/ids.server for targets `base`, `dev`, and `test` are available for use
& extension on DockerHub:

* idilic https://hub.docker.com/repository/docker/seanmorris/ids.idilic
* server https://hub.docker.com/repository/docker/seanmorris/ids.server

Pull from the cli with:

```bash
$ docker pull seanmorris/ids.idilic:latest
$ docker pull seanmorris/ids.server:latest
```

or extend in a dockerfile with one of the following:

```Dockerfile
FROM seanmorris/ids.idilic:TAGNAME
FROM seanmorris/ids.server:TAGNAME
```

(it is not recommended to use `latest` in `FROM`)

## Configuration / Environment Variables / Secrets

### Loading Settings

Settings may be provided in environment variables, .env files, or yml files.

#### Environment Variable & Target Files

The following files may be created/modified to configure the system. When the project is built, restarted, etc, they will be checked for modification, and re-built to the root of the projct if need be. The files generated to the root of the project should not be committed to version control.

* `config/.env` - Should not be committed to version control. Contains configuration that applies to the system regardles of the system's target.
* `config/.env.default` - Should be committed to version control. Contains non-secret configurations file and blank/dfault values for variables to be set in `config/.env`.
* `config/.env_TARGET` - Should not be committed to version control. Contains configuration based the system's target.
* `config/.env_TARGET.default` - Should be committed to version control. Contains non-secret configurations file and blank/dfault values for variables to be set in `config/.env_TARGET`.

The values set will be read according to the following precedence (higher takes precedence over lower):

* `.env_TARGET`
* `.env_TARGET.default`
* `.env`
* `.env.default`

Environment variables to be used in configuration should have the the prefix `IDS_`. An environment variable with the name IDS_SOME_VAR and IDS_SOME_OTHERVAR would be accessible within the system with:

```bash
IDS_SOME_VAR=value
IDS_SOME_OTHERVAR=other value
```

```php
<?php
use \SeanMorris\Ids\Settings;

$someVar = Settings::read('some', 'var');
$someOtherVar = Settings::read('some', 'otherVar');
```

### Configuration Objects

They'd also both be accessible as an object, which can be iterated:

```php
<?php

$some = Settings::read('some');

$some->var;
$some->otherVar;

foreach($some as $configKey => $value)
{

}

```

### Configuration Arrays

Arrays can be created by adding a `_` to the end of the environment variable name. The value will be split on whitespace.

```
IDS_ARRAY=first second third
```

```php
<?php
$array = Settings::read('ARRAY');
```

Whitespace will be preserved if the value is quotes:

```
IDS_ARRAY=first "second element" third
```

Quotes can be escaped by doubling:
```
IDS_ARRAY=first "second element ""with quotes inside""." third
```

### Hostname & Port based configuration

Hostname specific environment variables are prefixed with an extra underscore: `IDS__`. Dots and other non-word charaters in the hostname are placed by a single underscore, **except for the hyhpen which is replaced by 3 underscores.** Another double underscore finishes the hostname, and the variable name comes next.

The above environment variables could be overridden for example.com with: `IDS__EXAMPLE_COM__SOME_VAR` and `IDS__EXAMPLE_COM__SOME_OTHERVAR`. They would be accessed in the same way as above:


```
IDS__EXAMPLE_COM__SOME_VAR=overridden value
IDS_SOME_OTHERVAR=other overridden value
```

```php
<?php
use \SeanMorris\Ids\Settings;

$someVar = Settings::read('some', 'var');
$someOtherVar = Settings::read('some', 'otherVar');

$some = Settings::read('some');

$some->var;
$some->otherVar;

```

Example.com could override the variables by port number if they wanted to change some behavior based on whether the user was on SSL. Adding another double underscore between the hostname and the variable name allows us to do that: `IDS__EXAMPLE_COM__80__SOME_VAR` and `IDS__EXAMPLE_COM__80_SOME_OTHERVAR` for HTTP and `IDS__EXAMPLE_COM__443__SOME_VAR` and `IDS__EXAMPLE_COM__443_SOME_OTHERVAR` for HTTPS.

Again, nothing changes in way they are accessed. The code reads them in the same way:

```
IDS__EXAMPLE_COM__80__SOME_VAR=normal value
IDS__EXAMPLE_COM__443__SOME_VAR=value for SSL

IDS__EXAMPLE_COM__443__SOME_VAR=some other value
IDS__EXAMPLE_COM__443_SOME_OTHERVAR=some other value for SSL
```

```php
<?php
use \SeanMorris\Ids\Settings;

$someVar = Settings::read('some', 'var');

// ...and so on

```

### Non-Secret Config

Default values for environment variables that are **non-secrets**, (and thus may be pushed to version control) may be set in `config/.env`. Target specific variables may be set in `config/.env.[TARGET]`. **DO NOT PUT SECRETS SUCH AS PASSWORDS HERE.** These files will be checked into version control.

These files will be used to generate the final .env files in the root of the project when the project is built or started.

### Disposable Secrets

If you need a quick random value to use in the build process (ie when creating a MYSQL password), make sure to add the key to the relevant .env file, but leave the value blank. Add the key name to the `.entropy` file in the root of your project in the form: `CONFIG_KEY:ENTROPY_KEY`. `CONFIG_KEY` is the name of your configuration value, and `ENTROPY_KEY` is an arbitrary string that allows you to re-use the same random value between different configuration keys.

When the project starts, the .env files will be generated to the root of the project if they do not exist already, or if the existing ones are empty. Any keys present in the `.entropy` file will be set to a random 32 character string. This is how the schema name, username, and password are generated for the mysql server for the `make test` command when if the current `TARGET` is `test`.

run `TARGET=test idilic info` to see an example of this.

### Normal Secrets

So long as the .env files exist, and are not empty, the system will not attempt to regenerate them, so any secrets could in theory be added here, in place. This works fine for development, but for production it is recomended that secrets be stored in environtment variables.

Values from environment variables and .env files may accessd via PHP's `getenv()`. Ensure you've set the relevent values in the `environment` section of the target's docker-compose file if you chose not to add the value to a .env file.

### Json/Yaml Configuration

#### Settings

Json/Yaml files may be provided in the `config/` directory, under a directory named `hostname/` or `hostname;port/`, where `hostname` and `port` are the domain and port you expect requests to come in on. The file should then be named `settings.json` or `settings.yml`.  A directory named `_;80` or `;80` will match only for requests on port 80 regardless of the hostname. A directory named `_;`, `_`, or ';' may be provided as a fallback if no others match.

**NOTE**
If any yml config file is loaded, the system stops there and does not look for others. Host or port specific yml files will not be merged with more general wild card ones.

#### Defaults

Default, non-secret values may be provided in a file named `settings.defaults.yml` or `settings.defaults.json`. Defaults are the same as settings except they're stored in version control.

Defaults are loaded by the same rules as standard settings files. Once a singe defaults file is found no more will be loaded.

Settings and Defaults files do not need to be loaded at the same level of generality. `settings.yml` may be found in `_;80` and settings.defaults.yml may be found in `hostname` or vice versa.

Settings always take precedence over defaults.

Environment variables from the shell or .env files take precedence over yml files.

to be continued...

## Schema Diffing & Patching

Ensure you've installed the `idilic` cli tool from the start of the document. Use the following commands to manage your database schema.

`idilic storeSchema [PACKAGE]` - Store the current schema.

`idilic applySchema [PACKAGE]` - Apply the stored schema.

`idilic applySchemas` - Apply the stored schema for all installed packages.


The schema will be stored in `data/global/schema.json`.

## Routing

```
IDS_ENTRYPOINT=SeanMorris\Ids\Test\Route
```

```php
<?php
namespace SeanMorris\Ids\Test\Route;
class RootRoute implements \SeanMorris\Ids\Routable
{
	public
		$routes = [
			'foo' => 'SeanMorris\Ids\Test\Route\FooRoute'
		]
		, $alias = [
			'bar' => 'foo'
		];

	public function index($router)
	{
		return 'index';
	}

	public function otherPage($router)
	{
		return 'not index';
	}
}
```

## Modeling / ORM

```php
<?php
namespace SeanMorris\Ids\Test\Model;
class Foozle extends \SeanMorris\Ids\Model
{
	use Common;

	protected
		$id
		, $class
		, $publicId
		, $value
	;

	protected static
		$table = 'Foozle'
		, $createColumns = [
			'publicId' => 'UNHEX(REPLACE(UUID(), "-", ""))'
		]
		, $readColumns = [
			'publicId' => 'HEX(%s)'
		]
		, $updateColumns = [
			'publicId' => 'UNHEX(%s)'
		]
	;
}
```
## Logging

Logs can be written from anywhere in the system by calling a function coresponging to the desied level of verbosity. There are 6 levels of verbosity available.

```php
<?php
use \SeanMorris\Ids\Log;

Log::trace(...$messages); # Log a message along with a stacktrace.
Log::query(...$messages); # Log query-level information
Log::debug(...$messages); # Log debug information
Log::info(...$messages);  # Log general information
Log::warn(...$messages);  # Issue a warning
Log::error(...$messages); # Issue an error
```

Ids will write logs to a file or handle specified by the `IDS_LOG` config:

```
IDS_LOG=php://stderr
# can also be a file
IDS_LOG=/tmp/ids.log
```

The current level of verbosity can be configured with the `IDS_LOGLEVEL` variable.

**NOTE**: If `LOGLEVEL` is set to `trace`, **every single log entry** will be accompanied by a stack trace. Thic can fill a disk quickly. Use with caution!

```
IDS_LOGLEVEL=trace
IDS_LOGLEVEL=query
IDS_LOGLEVEL=info
IDS_LOGLEVEL=debug
IDS_LOGLEVEL=warn
IDS_LOGLEVEL=error
IDS_LOGLEVEL=off
```

### ANSI Colors

Logging comes with ANSI color turned on by default. Set the following config to disable it if thats causing problems for whatever you're using to read the logs:

```
IDS_LOGCOLOR=0
```

### Censoring Logs

Set the `IDS_LOGCENSOR_` array in your config to define an array of values that should not appear in log files. If these values are found as function arguments, array keys, or object properties, they will be logged as `* censored *` rather than their actual value.

The default censor filter is:

```
IDS_LOGCENSOR_=router password passwd
```

You can test censor filters. Issuing the following command will output your database settings, with no censorship applied:

```bash
$ idilic config databases
```

Adding -vv to the idilic command will force it to print its logs to `STDERR` (without verbosity checks).

```bash
$ idilic -vv config databases
```
We can drop the original output of the command as follows to declutter the terminal:

```bash
$ idilic -vv config databases 1 > /dev/null
```
We can also extract the original output with:

```bash
$ idilic -vv config databases 2 > /dev/null
```

### Redirecting / Forcing CLI Logs

Logging can be forced on the command line with `-v` and `-vv`.

* `-v` will print any logs within the current verbosity thesholds to `STDERR`.
* `-vv` will print any logs REGARDLESS of verbosity thesholds to `STDERR`.

```bash
$ idilic -v  info
$ idilic -vv info
```

Logs of individual commands can be redirected to files or handles with `2>`.

```bash
$ idilic -v  info 2> /tmp/summary$(date +%Y%m%d).log
$ idilic -vv info 2> /tmp/details$(date +%Y%m%d).log
```

If you're more interested in the logs than the output, you can discard the output with:

```bash
$ idilic -v  command 1> /dev/null
$ idilic -vv command 1> /dev/null
```

### Graylog

Logs may be sent to Graylog by providing a custom log handler in the config

```
IDS_LOGGERS_=\SeanMorris\Ids\Logger\Gelf
```

Custom loggers may created by implemeting the `\SeanMorris\Ids\Logger` interface.

```php
<?php
namespace SeanMorris\Ids\Logger;
class AdditionalLogger implements \SeanMorris\Ids\Logger
{
	public static function start($logBlob)
	{/* ... */}

	public static function log($logBlob)
	{/* ... */}
}

```

## Debugging

```
XDEBUG_CONFIG_REMOTE_HOST=${DHOST_IP}
XDEBUG_CONFIG_REMOTE_PORT=9000

XDEBUG_CONFIG_PROFILER_ENABLE=0
XDEBUG_CONFIG_REMOTE_ENABLE=1
```

## Linking

```bash
$ make composer-dumpautoload
$ idilic link
```
## Testing

```bash
$ make @test build test
```

```bash
$ idilic runTests
```
```php
<?php
namespace SeanMorris\ExamplePackage\Test;
class ExampleTest extends \UnitTestCase
{
	public function testSomething()
	{
		// ...
	}
}
```

## File Access

```php
<?php
use \SeanMorris\Ids\Disk;

$file = new File($filename);

//Check if file exists
if($file->check())
{
	// ...
}

//Get the path to the file
$path = $file->name();

//Copy the file to another location.
$copiedFile = $file->copy($newLocation);

// Read byte-by-byte
while(!$file->eof())
{
	$byte = $file->read(1);
}

// Read entire file:
$content = $file->slurp();

// Append
$file->write($content);

// Overwrite
$file->write($content, FALSE);
```

## Migrations*
## Asset Management
## HTTP API*
## IPC / AMPQ*
### Idilic CLI

Idilic commands can be run from the CLI in the form `idilic command` or `idilic Vendor/Package command`. Note that on the CLI package names can be separated by a forward slash (`/`). This is done to prevent the user from being forced to remember to escape backslashes (`\\`).

Run `idilic help` to get a list of available idilic commands.

New commands can be implemented by adding a route class named `RootRoute` under the namespace `Vendor\Package\Idilic\Route` like so:

```php
<?php
namespace SeanMorris\ExamplePackage\Idilic\Route;
class RootRoute implements \SeanMorris\Ids\Routable
{
	/** Help text goes here. */
	public function commandName($router)
	{}
}
```

## Dependency Injection*
## Sessions
## Email*
## Theming / Frontends
## Existing Ids Projects

## Make Commands

Run these from the project root to build and control the project infrastructure.

 * `make build` `make b` - Build the project
 * `make env` `make e` - Print the project's environment config.
 * `make test` `make t`- Run tests.
 * `make test` `make t`- Remove the generated configs, **even if they have been altered.**
 * `make start` `make s`- Start the project services.
 * `make start-fg` `make sf`- Start the project services, hold control of the terminal and stream output.
 * `make start-bg` `make sb`- Start the project services, hold control of the terminal and stream output.
 * `make restart-fg` `make rf` - Restart the project services, hold control of the terminal and stream output.
 * `make restart-bg` `make rb`- Restart the project services, hold control of the terminal and stream output.
 * `make stop` `make d`- Stop the project services.
 * `make stop-all` `make da`- Stop the project services, including any that no longer appear in the compose file.
 * `make kill` `make k`- Immediately kill the project services.
 * `make nuke` `make nk`* - Immediately kill all containers on the host. Not yet implemented.
 * `make current-tag` `ct`- Output the project tag for the current target & branch.
 * `make list-tags` `make lt`- List image tags for the current target & branch.
 * `make list-images` `make li`- List images for the current target & branch.
 * `make push-images` `make psi`- List images for the current target & branch.
 * `make pull-images` `make pli`- List images for the current target & branch.
 * `make hooks` - Initialize git hooks.
 * `make composer-install` `make ci`- Install composer packages.
 * `make composer-update` `make co`- Update composer packages.
 * `make composer-dump-autoload` `make cda`- Regenerate and dump composer autoload files..
 * `make npm install PKG="[PACKAGE]"` `make ni`- Run `npm install` inside the project.
 * `make bash` `make sh`- Get a bash prompt to an `idilic` container.
 * `make run CMD="SERVICE [COMMAND]"` `make r`- Run a command in a service container.
 *
## Dependencies

* Bash
* Composer
* Docker
* Docker Compose
* GNU Make
* Git
* Linux or Compatible OS
* Node
* PHP
* SimpleTest/SimpleTest

## SeanMorris/Ids

### Copyright 2011-2019 Sean Morris

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
