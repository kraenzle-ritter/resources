#!/bin/bash

#!/bin/bash

SRC="resources/views"
DEST="vendor/orchestra/testbench-core/laravel/packages/kraenzle-ritter/components/resources/views"

mkdir -p "$DEST"
rsync -a "$SRC/" "$DEST/"
