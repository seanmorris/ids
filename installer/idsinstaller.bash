#!/usr/bin/env bash
DOMAIN=$1;
BIN_DIR="$(cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )";
ROOT_DIR="$(dirname $BIN_DIR)";
INSTALL_DIR="$BIN_DIR/../installer";
EMPTY_DIR="$INSTALL_DIR/empty";

echo "Initializing directory structure...";
cp -r -n $EMPTY_DIR/* .;

echo "Setting permissions...";
chmod 777 ./temporary;
chmod 766 ./temporary/log.txt;
chmod 777 ./public/Static/Dynamic;
chmod 777 ./public/Static/Dynamic/Min;

if [ ! -f ./composer.json ]; then
	echo "Getting composer ready...";
	composer init;
	composer config repositories.seanmorris-ids path $ROOT_DIR;
	composer require seanmorris/ids:dev-master;
	composer update;

	echo "Getting autoloader ready...";
	php $INSTALL_DIR/setup_composer.php "`pwd`/composer.json" "`composer config name`" $DOMAIN;
fi;

composer dump-autoload;