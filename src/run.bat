::
:: e621 Pool Downloader
::
:: (c) Jack'lul <jacklulcat@gmail.com>
::
:: For the full copyright and license information,
:: please view the LICENSE file that was distributed
:: with this source code.
::

@echo off
TITLE e621 Batch Reverse Search

SET SPATH=%~dp0
SET PATH=%SPATH%\runtime\;%PATH%
SET PHP_INI_SCAN_DIR=

php "%SPATH%/dlpool.phar" %*

echo Press ENTER key to continue...
set /p key=""
