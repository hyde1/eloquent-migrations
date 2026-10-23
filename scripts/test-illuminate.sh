#!/usr/bin/env bash
#
# Runs the test suite against another Illuminate version, in a temporary copy of the project:
# composer.json, composer.lock and vendor/ are left untouched.
#
# Usage: scripts/test-illuminate.sh <illuminate version constraint> [phpunit arguments...]
#   scripts/test-illuminate.sh ^13.0
#   scripts/test-illuminate.sh 13.x-dev --testsuite=integration
#   DB_CONNECTION=pgsql scripts/test-illuminate.sh 13.x-dev
#
set -euo pipefail

constraint="${1:?Usage: $0 <illuminate version constraint> [phpunit arguments...]}"
shift

root="$(cd "$(dirname "$0")/.." && pwd)"
workdir="$(mktemp -d "${TMPDIR:-/tmp}/elmigrator-illuminate.XXXXXX")"
trap 'rm -rf "$workdir"' EXIT

rsync -a --exclude vendor --exclude .git --exclude .phpunit.cache "$root/" "$workdir/"
cd "$workdir"

composer config minimum-stability dev
composer config prefer-stable true
composer require --no-interaction --no-progress --with-all-dependencies \
    "illuminate/database:$constraint" \
    "illuminate/console:$constraint" \
    "illuminate/filesystem:$constraint"
composer show | grep -E '^(illuminate/database|symfony/console) '

vendor/bin/phpunit "$@"
