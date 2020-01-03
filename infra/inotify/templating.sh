#!/usr/bin/bash

# set -eux;

# inotify -e CLOSE_WRITE ./.templating || exit 0 &;

echo Starting in `pwd` > 2;

export EVENT=CLOSE_WRITE;

WATCH=`(sed 's/\t/  /g' |  make @test run \
	CMD='--entrypoint "yq r - -j" idilic | jq -r "to_entries[]  | select(.value) \
		| [.key, (.value | to_entries[] | .key, .value)] | @tsv"' \
		| while read MAPPING; do \
			echo ${MAPPING}
		done; \
) < .templating`

WATCHLIST=

while read DIR PATTERN DESTINATION; do echo $DIR; done <<< $WATCH \
| make run CMD='inotify -e ${EVENT} --fromfile - -rm' \
| while read LOCATION TYPE FILENAME; do    \
	while read DIR PATTERN DESTINATION; do \
		[[ ${#DIR} -gt 2 ]] || continue;
		[[ "$DIR" == `cut -b -${#DIR} <<< "$LOCATION"` ]] \
			|| continue;
		[[ "$FILENAME" == *"$PATTERN"* ]] \
			|| continue;
		echo "Build;$DIR;$PATTERN;$DESTINATION;$LOCATION;$TYPE;$FILENAME;";
		echo Building "$LOCATION$FILENAME"... >2;
		echo "$LOCATION$FILENAME" \
			| make `replace "$PATTERN" "$DESTINATION"`;
	done <<< $WATCH;
done;
