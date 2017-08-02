<?php
/**
 * e621 Pool Downloader
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed
 * with this source code.
 */

/**
 * Include the script
 */
require_once("app.php");

/**
 * Run it
 */
try {
    $app = new App((isset($argv[1]) ? $argv[1] : ''));
    $app->run();
} catch (\Exception $e) {
    print($e);
}
