#!/bin/bash
#
# e621 Pool Downloader
#
# (c) Jack'lul <jacklulcat@gmail.com>
#
# For the full copyright and license information,
# please view the LICENSE file that was distributed
# with this source code.
#

SPATH=$(dirname $0)
PATH=$SPATH/runtime:$PATH:
PHP_INI_SCAN_DIR=

if which php >/dev/null; then
	php "$SPATH/dlpool.phar" $@
else
	echo "Install 'php-cli' package first!"
fi

echo Press ENTER key to continue...
read -p "" key
