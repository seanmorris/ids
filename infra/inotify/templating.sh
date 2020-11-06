#!/usr/bin/env bash

set -eu;
set -x;

echo Starting in `pwd` > 2;

WATCH=`make @test template-patterns`;

echo -e "$WATCH" 1>2;

while read DIR PATTERN DESTINATION; do echo $DIR; done <<< "$WATCH" \
| make run CMD='inotify -e CLOSE_WRITE --fromfile - -rm' \
| while read LOCATION TYPE FILENAME; do               \
	while read TEMPLATE DESTINATION; do               \
		[[ ${#TEMPLATE} -gt 2 ]] || continue;         \
		[[ "$TEMPLATE" == "$LOCATION$FILENAME" ]]     \
			|| continue;                              \
		make $DESTINATION;                            \
		break;                                        \
	done <<< "$WATCH";                                \
done;
