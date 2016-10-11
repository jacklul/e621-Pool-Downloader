@echo off
TITLE e621 Pool Downloader

SET SPATH=%~dp0
SET PATH=%PATH%;%SPATH%\runtime\

php "%SPATH%/dlpool.phar" %*

echo Press ENTER key to continue...
set /p key=""
