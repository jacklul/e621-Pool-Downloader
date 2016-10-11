#!/bin/bash

SPATH=$(dirname $0)
PATH=$PATH:$SPATH/runtime

if which php >/dev/null; then
	php "$SPATH/dlpool.phar" $@
else
	echo "Install 'php-cli' package first!"
fi

echo Press ENTER key to continue...
read -p "" key
