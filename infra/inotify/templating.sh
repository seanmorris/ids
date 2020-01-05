#!/usr/bin/env bash

set -eu;

# make ${TARGET}\+inotify build

# inotify -e CLOSE_WRITE ./.templating || exit 0 &;

echo Starting in `pwd` > 2;

export EVENT=CLOSE_WRITE;

WATCH=`(sed 's/\t/  /g' |  make @test run \
	CMD='--entrypoint "yq r - -j" idilic | jq -r "to_entries[]  | select(.value) \
		| [.key, (.value | to_entries[] | .key, .value)] | @tsv"' \
		| while read MAPPING; do echo ${MAPPING}; done; \
) < .templating`

echo -e "$WATCH" 1>2;

WATCHLIST=

while read DIR PATTERN DESTINATION; do echo $DIR; done <<< $WATCH \
| make run CMD='inotify -e ${EVENT} --fromfile - -rm'      \
| while read LOCATION TYPE FILENAME; do                    \
	while read DIR PATTERN DESTINATION; do                 \
		[[ ${#DIR} -gt 2 ]] || continue;                   \
		[[ "$DIR" == `cut -b -${#DIR} <<< "$LOCATION"` ]]  \
			|| continue;                                   \
		[[ "$FILENAME" == *"$PATTERN"* ]]                  \
			|| continue;                                   \
		echo "$LOCATION$FILENAME"                          \
			| make `replace "$PATTERN" "$DESTINATION"`;    \
		break;                                             \
	done <<< $WATCH;                                       \
done;
