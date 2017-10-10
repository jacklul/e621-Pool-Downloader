#!/bin/bash
#
# e621 Pool Downloader
#
# Copyright (c) 2016 Jack'lul <https://jacklul.com>
#
# For the full copyright and license information,
# please view the LICENSE file that was distributed
# with this source code.
#

SPATH=$(dirname $0)
PATH=$SPATH/runtime:$PATH:

if which php >/dev/null; then
	php "$SPATH/dlpool.phar" $@
else
	echo "Install 'php-cli' package first!"
fi

echo Press ENTER key to continue...
read -p "" key