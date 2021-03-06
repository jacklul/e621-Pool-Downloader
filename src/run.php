<?php
/**
 * e621 Pool Downloader
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed
 * with this source code.
 */

/**
 * Include the script
 */
require_once "app.php";

/**
 * Run it
 */
try {
    $app = new jacklul\e621dlpool\App((isset($argv[1]) ? $argv[1] : ''));
    $app->run();
} catch (\Exception $e) {
    print($e);
}
