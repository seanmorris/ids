#!/bin/bash
test ! -z "$IDS_APT_PROXY_HOST$IDS_APT_PROXY_PORT"                         \
&& timeout 1 bash -c "</dev/tcp/$IDS_APT_PROXY_HOST"/"$IDS_APT_PROXY_PORT" \
&& echo "http://$IDS_APT_PROXY_HOST:$IDS_APT_PROXY_PORT"                   \
|| echo DIRECT;
