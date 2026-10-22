#!/bin/sh
set -eu

repo_dir=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
image='docker.io/freshrss/freshrss:1.30.4@sha256:af44385229541bee24aa9148d0ff8f3b40e095373c63a2c0f74e09a27d3c59df'

exec podman run --rm --pull=missing --network=none --read-only \
	--cap-drop=all --security-opt=no-new-privileges --userns=keep-id \
	--volume "$repo_dir:/workspace:ro" --workdir /workspace \
	--entrypoint sh "$image" tests/check.sh
