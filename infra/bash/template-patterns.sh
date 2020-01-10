#!/usr/bin/env bash

set -eu;
# set -x;

FOR_GENERATED=${1:-};

(sed 's/\t/  /g' |  make @test run \
	CMD='--entrypoint "yq r - -j" idilic | jq -r "to_entries[]  | select(.value) \
		| [.key, (.value | to_entries[] | .key, .value)] | @tsv"' \
		| while IFS=$'\t' read -r PREFIX FIND REPLACE; do \
			test -f $PREFIX && { \
				GENERATED=`echo $PREFIX | replace $FIND $REPLACE`; \
				(test -z $FOR_GENERATED) \
					&& echo -ne $PREFIX'\t';
				(test -z $FOR_GENERATED || test "$GENERATED" == "$FOR_GENERATED") \
					&& echo $GENERATED; \
			}; \
			test -d $PREFIX && { \
				find $PREFIX -name "*$FIND*" | { \
					while read TEMPLATE; do\
						GENERATED=`echo $TEMPLATE | replace $FIND $REPLACE`; \
						(test -z $FOR_GENERATED) \
							&& echo -ne $PREFIX'\t';
						(test -z $FOR_GENERATED || test "$GENERATED" == "$FOR_GENERATED") \
							&& echo $GENERATED; \
					done; \
				}; \
			}; \
	done; \
) < .templating;

