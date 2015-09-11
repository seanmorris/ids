#!/usr/bin/env bash

WATCH=$1;
ON_CHANGE=$2;
DELAY=$3;
IS_NUMBER='^[0-9\.]+$';

while true; do
	CHECK_SUM=`ls -alR $WATCH | md5sum | cut -f1 -d ' '`;
	
	if [ "$CHECK_SUM" != "$LAST_CHECK_SUM" ]; then
		echo $'\aTriggered on' `date`;
		sleep 0.08;
		echo "`$ON_CHANGE`";
		echo $'\n';
	fi;

	LAST_CHECK_SUM=$CHECK_SUM;
	sleep $DELAY;
done;