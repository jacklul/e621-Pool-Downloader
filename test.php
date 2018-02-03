<?php

$argv[1] = 'build/test/';

if (!is_dir($argv[1])) {
    mkdir($argv[1], 0755, true);
}

file_put_contents('build/test/.poolinfo', 'ID=378');

$argv[1] = realpath($argv[1]);

require_once("build/dlpool.phar");
