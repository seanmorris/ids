# SeanMorris/Ids

*/ eye dee ess /*

The PHP/Docker Framework

![seanmorris-ids](https://img.shields.io/badge/seanmorris-ids-red?style=for-the-badge) [![Travis (.org)](https://img.shields.io/travis/seanmorris/ids?style=for-the-badge)](https://travis-ci.org/seanmorris/ids) ![Size badge](https://img.shields.io/github/languages/code-size/seanmorris/ids?style=for-the-badge)

The Ids library provides general domain-primitives for developing web based applications. Routing, requests, modeling configuration, logging, sessions, and database access are all abstracted behind simple, expressive interfaces to efficient and powerful code.

The project is made to run in docker but doesn't require it. It can be included in any composer project, and used in part or in whole easily.

## Installation

Include Ids in your project with:

```bash
$ composer require seanmorris/ids
```

Install Ids globally for access to the `idilic` cli tool:

```bash
$ composer global require seanmorris/ids
```

## Dev Tools

The `dev` build target provides facilities for connecting to xdebug and graylog.

## Idilc CLI

Ids comes with the `idilic` command when installed globally with composer. When executed, it will ascend through the current path looking for another project including Ids. When it finds it, it will attach to that project and utilize its facilities.

If it encounters a local `idilic` pass-thru in the project directory, it will hand control to the docker environment where execution will continue.

Run `idilic help` to see actions exposed by any installed packages.

## Multistaging / Environment Targets

### base, prod, dev, and test

By default Ids provides 4 build targets: base, prod, dev, and test. Each exposes different ports, so they may run without conflict in parallel.

* **base** exposes no ports and builds **without** require-dev.

* **prod** exposes port 80 (configurable by `IDS_EXPOSE_HTTP`) and port 443* ( configurable by `IDS_EXPOSE_HTTPS` ) and builds **without** require-dev.

* **test** exposes port 1001 (configurable by `IDS_EXPOSE_HTTP`) and port 1101 ( `IDS_EXPOSE_SQL` ) and builds **with** require-dev.

* **dev** exposes port 1000 ( configurable by `IDS_EXPOSE_HTTP` ) and port 1100 ( `IDS_EXPOSE_SQL` ) and builds **with** require-dev.

\*Not yet implemented.

### Switching Targets

The system will use the TARGET environment variable to decide which build target to use.

If youre on the BASH shell simply run `TARGET=base`, `TARGET=test`, `TARGET=dev`, `TARGET=prod`.

### Extending Environments

Additional targets may be specified by creating a new `infra/compose/[TARGET].yml` in your project. Build this file as you'd build any other docker-compose file.

If you need some target specific build steps. then add a `FROM base as TARGET` section to any docker files in `infra/docker` that are relevant to that build target. You can expand on any existing build target, you're not limited to extending `base`.

## Build

The project may be built with `make`. Debian users can get this tool by running `apt-get install build-essential`. The build process also requires `docker` and `docker-compose`. `minikube` is required only for kubernetes testing.

The default build target is `base`. run `TARGET=dev` or `TARGET=test` to switch to the development/testing target.

```bash
$ TARGET=dev # Select a target
$ make       # build the project
$ make start # start the services
```

Docker & docker-compose are available here:

* https://docs.docker.com/install/
* https://docs.docker.com/compose/install/

## Autotagging / Autopublishing / Git Hooks

Register git hooks with `make hooks`.

Image tags are generated automatically on build based on the date, current git tag (falls back to commit hash), and target. "-branch" will be omitted for master.

*  repository/project:gitTag-target-branch
*  repository/project:date-target-branch
*  repository/project:latest-target-branch

Images will be built on `git commit` and pushed on `git push` if the current branch and environment appear in the project root `.publishing` file in the form: `BRANCH:TARGET`.

## Configuration / Environment Variables / Secrets

### Non-Secret Config

Default values for environment variables that are **non-secrets**, (and thus may be pushed to version control) may be set in `infra/env/.env`. Target specific variables may be set in `infra/env/.env.[TARGET]`. **DO NOT PUT SECRETS SUCH AS PASSWORDS HERE.** These files will be checked into versio control.

These files will be used to generate the final .env files in the root of the project when the project is built or started.

### Disposable Secrets

If you need a quick random value to use in the build process (ie when creating a MYSQL password), make sure to add the key to the relevant .env file, but leave the value blank. Add the key name to the `.entropy` file in the root of your project in the form: `CONFIG_KEY:ENTROPY_KEY`. `CONFIG_KEY` is the name of your configuration value, and `ENTROPY_KEY` is an arbitrary string that allows you to re-use the same random value between different configuration keys.

When the project starts, the .env files will be generated to the root of the project if they do not exist already, or if the existing ones are empty. Any keys present in the `.entropy` file will be set to a random 32 character string. This is how the schema name, username, and password are generated for the mysql server for the `make test` command when `TARGET=test`.

run `TARGET=test idilic info` to see an example of this.

### Normal Secrets

So long as the .env files exist, and are not empty, the system will not attempt to regenerate them, so any secrets could in theory be added here, in place. This works fine for development, but for production it is recomended that secrets be stored in environtment variables.

Values from environment variables and .env files may accessd via PHP's `getenv()`. Ensure you've set the relevent values in the `environment` section of the target's docker-compose file if you chose not to add the value to a .env file.

## Available Images

Docker images for seanmorris/ids.idilic & seanmorris/ids.server for targets `base`, `dev`, and `test` are available for use
& extenstion on DockerHub:

https://hub.docker.com/repository/docker/seanmorris/ids.idilic
https://hub.docker.com/repository/docker/seanmorris/ids.server

Pull from the cli with:

```bash
$ docker pull seanmorris/ids.idilic:latest
$ docker pull seanmorris/ids.server:latest
```

or extend in a dockerfile with one of the following:

```Dockerfile
FROM seanmorris/ids.idilic:TAGNAME # CLI interface
FROM seanmorris/ids.server:TAGNAME # HTTP interface
```

## Start / Stop / Restart

```bash
$ make start      # Start the project in the background with no output
$ make start-fg   # Start the project in the foreground
$ make start-bg   # Start the project in the background, stream output to foreground

$ make restart    # Restart the project in the background with no output
$ make restart-fg # Restart the project in the foreground
$ make restart-bg # Restart the project in the background, stream output to foreground

$ make stop       # Stop all services defined for target
$ make stop-all   # Stop all services spawned for target
                  # even ones no longer in target compose file.
```

## Images & Tags

```bash
$ make list-images # List all images for the current project, target & branch.
$ make list-tags   # List all tags for the current project, target & branch.

$ make push-images # push all images for the current project, target & branch.
$ make pull-images # push all images for the current project, target & branch.
```

(it is not recommended to use `latest` in `FROM`)

to be continued...

## Creating a New Ids Project
## Linking
## Routing
## Modeling / ORM
## Schema Diffing & Patching
## Migrations
## HTTP API
## IPC / AMPQ
## Solr & Redis
## Idilic CLI
## Logging
## Dependency Injection
## File Access
## Sessions
## Email
## Debugging
## Settings
## Testing
