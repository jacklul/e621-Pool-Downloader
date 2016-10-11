<?php

$buildRoot = "build";
$pharName = "dlpool.phar";

if (ini_get("phar.readonly") == 0) {
    if (!is_dir($buildRoot)) {
        mkdir($buildRoot);
    }

    echo "Building...\n";

    if (file_exists($pharName)) {
        unlink($pharName);
    }

    $phar = new Phar($buildRoot . '/' . $pharName, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $pharName);
    $phar["dlpool.php"] = file_get_contents("dlpool.php");
    $phar->setStub($phar->createDefaultStub("dlpool.php"));

    if (file_exists("dlpool.bat")) {
        copy("dlpool.bat", $buildRoot . "/dlpool.bat");
    }

    if (file_exists("dlpool.sh")) {
        copy("dlpool.sh", $buildRoot . "/dlpool.sh");
    }
}
