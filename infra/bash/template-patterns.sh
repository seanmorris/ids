#!/usr/bin/env bash

set -eu;

WATCH=`(sed 's/\t/  /g' |  make @test run \
	CMD='--entrypoint "yq r - -j" idilic | jq -r "to_entries[]  | select(.value) \
		| [.key, (.value | to_entries[] | .key, .value)] | @tsv"' \
		| while read MAPPING; do echo ${MAPPING}; done; \
) < .templating`
