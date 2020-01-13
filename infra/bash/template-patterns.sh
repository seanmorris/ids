#!/usr/bin/env bash

set -eu;
# set -x;

FOR_GENERATED=${1:-};

(sed 's/\t/  /g' | make -s @test run \
	CMD='--entrypoint "yq r - -j" idilic | jq -r "to_entries[]  | select(.value) \
		| [.key, (.value | to_entries[] | .key, .value)] | @tsv"' \
		| while IFS=$'\t' read -r PREFIX FIND REPLACE; do \
			test -f "$PREFIX" && { \
				GENERATED=`echo $PREFIX | replace $FIND $REPLACE`; \
				(test -z $FOR_GENERATED) \
					&& echo -ne $PREFIX'\t';
				(test -z $FOR_GENERATED) \
					&& echo $GENERATED; \
				(test "$GENERATED" == "$FOR_GENERATED") \
					&& echo $PREFIX; \
				(test "-t" == "$FOR_GENERATED") \
					&& echo -n "$PREFIX "; \
				(test "-g" == "$FOR_GENERATED") \
					&& echo -n "$GENERATED "; \
			}; \
			test -d "$PREFIX" && { \
				find $PREFIX -name "*$FIND*" | { \
					while read TEMPLATE; do\
						GENERATED=`echo $TEMPLATE | replace $FIND $REPLACE`; \
						(test -z $FOR_GENERATED) \
							&& echo -ne $TEMPLATE'\t';
						(test -z $FOR_GENERATED) \
							&& echo $GENERATED; \
						(test "$GENERATED" == "$FOR_GENERATED") \
							&& echo $TEMPLATE; \
						(test "-t" == "$FOR_GENERATED") \
							&& echo -n "$TEMPLATE "; \
						(test "-g" == "$FOR_GENERATED") \
							&& echo -n "$GENERATED "; \
					done; \
				}; \
			}; \
	done; \
) < .templating || true;
