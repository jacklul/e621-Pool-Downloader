::
:: e621 Pool Downloader
::
:: Copyright (c) 2016 Jack'lul <https://jacklul.com>
::
:: For the full copyright and license information,
:: please view the LICENSE file that was distributed
:: with this source code.
::

@echo off
TITLE e621 Batch Reverse Search

SET SPATH=%~dp0
SET PATH=%SPATH%\runtime\;%PATH%

php "%SPATH%/dlpool.phar" %*

echo Press ENTER key to continue...
set /p key=""