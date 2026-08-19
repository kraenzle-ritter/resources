#!/bin/bash
set -euo pipefail

SRC="resources/views"
DEST="vendor/orchestra/testbench-core/laravel/packages/kraenzle-ritter/resources/resources/views"

mkdir -p "$DEST"
rsync -a "$SRC/" "$DEST/"
