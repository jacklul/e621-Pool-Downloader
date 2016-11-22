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

define("ROOT", dirname(str_replace("phar://", "", __DIR__)));

class e621_Pool_Downloader {
    /**
     * App name
     *
     * @var int
     */
    private $NAME = 'e621 Pool Downloader';

    /**
     * App version
     *
     * @var int
     */
    private $VERSION = '1.0.3';

    /**
     * App update URL
     *
     * @var string
     */
    private $UPDATE_URL = 'https://api.github.com/repos/jacklul/e621-Pool-Downloader/releases/latest';

    /**
     * Script start time
     *
     * @var int
     */
    private $START_TIME = 0;

    /**
     * Script location
     *
     * @var string
     */
    private $WORK_DIR = ROOT;

    /**
     * Is the script being run on Linux?
     *
     * @var bool
     */
    private $IS_LINUX = false;

    /**
     * Is cURL available?
     *
     * @var bool
     */
    private $USE_CURL = true;

    /**
     * Line buffer (for download progress handler)
     *
     * @var string
     */
    private $LINE_BUFFER = '';

    /**
     * Pool ID
     *
     * @var int
     */
    private $POOL_ID = 0;

    /**
     * Pool name
     *
     * @var string
     */
    private $POOL_NAME = '';

    /**
     * Number of images in the pool
     *
     * @var int
     */
    private $POOL_IMAGES = 0;

    /**
     * Number of pages in the pool
     *
     * @var int
     */
    private $POOL_PAGES = 0;

    /**
     * Class constructor
     *
     * @param string $arg
     * @throws Exception
     */
    public function __construct($arg = '')
    {
        if (!function_exists('curl_version')) {
            $this->USE_CURL = false;
        }

        $this->WORK_DIR = getcwd();

        if (!empty($arg)) {
            if (is_numeric($arg)) {
                $this->POOL_ID = $arg;
            } elseif (is_string($arg) && is_numeric($poolID = $this->parseInput($arg))) {
                $this->POOL_ID = $poolID;
            } elseif (is_string($arg)) {
                $this->WORK_DIR = $arg;

                $this->setPoolIDFromFile($this->WORK_DIR);
            }
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
            $this->IS_LINUX = true;
        }

        set_time_limit(0);
        error_reporting(E_ERROR);
        date_default_timezone_set(date_default_timezone_get());

        $this->START_TIME = microtime(true);
    }

    private function setPoolIDFromFile($path)
    {
        if (file_exists($path . '/.poolinfo')) {
            $info = parse_ini_file($path . '/.poolinfo');
            if (!empty($info['ID'])) {
                $this->POOL_ID = $info['ID'];
            } else {
                die("Info file does not contain Pool ID!\n");
            }
        } else {
            die("Provided directory does not contain pool info file!\n");
        }
    }

    /**
     * Flush console line
     */
    private function flushLine()
    {
        print("\r" . str_repeat(' ', 55) . "\r");
    }

    /**
     * cURL progress callback
     *
     * @param $resource
     * @param $download_size
     * @param $downloaded
     * @param $upload_size
     * @param $uploaded
     */
    private function cURLProgress($resource = null, $download_size = 0, $downloaded = 0, $upload_size = 0, $uploaded = 0)
    {
        $total = 0;
        $progress = 0;

        /* fallback for different cURL version which does not use $resource parameter */
        if (is_numeric($resource)) {
            $uploaded = $upload_size;
            $upload_size = $downloaded;
            $downloaded = $download_size;
            $download_size = $resource;
        }

        if ($download_size > 0) {
            $total = $download_size;
            $progress = $downloaded;
        } elseif ($upload_size > 0) {
            $total = $upload_size;
            $progress = $uploaded;
        }

        if ($total > 0) {
            print (str_repeat(' ', 10) . "\r" . $this->LINE_BUFFER . ' ' . round(($progress * 100) / $total, 0)) . "%";
        }

        usleep(100);
    }

    /**
     * Parse user input
     *
     * @param $string
     * @return mixed
     */
    private function parseInput($string) {
        if (preg_match("/e621\.net\/pool\/show\/(.*)/", $string, $matches)) {
            $string = $matches[1];
        }

        return $string;
    }
    /**
     * Perform simple cURL download request
     *
     * @param $url
     * @param bool $progress
     * @return mixed
     */
    private function cURL($url, $progress = true)
    {
        if ($this->USE_CURL) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->NAME);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            if ($progress) {
                curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);
            }

            return curl_exec($ch);
        } else {
            print(str_repeat(' ', 10) . "\r" . $this->LINE_BUFFER);
            $result = file_get_contents($url, false, stream_context_create(['http' => ['user_agent' => $this->NAME]]));
            return $result;
        }
    }

    /**
     * Get needed data from e621 API
     *
     * @param int $page
     * @return mixed
     */
    private function getPoolPage($page = 1)
    {
        $result = $this->cURL('https://e621.net/pool/show.json?id=' . $this->POOL_ID . '&page=' . $page, false);
        $result = json_decode($result, true);

        if (is_array($result)) {
            if (!empty($result) && !empty($result['posts'])) {
                if (!empty($result['name'])) {
                    $this->POOL_NAME = $result['name'];
                } else {
                    $this->POOL_NAME = $this->POOL_ID;
                }

                $this->POOL_IMAGES = $result['post_count'];
                $this->POOL_PAGES = ceil($result['post_count'] / 24);

                $posts = $result['posts'];

                if ($page == 1) {
                    for ($i = 2; $i <= $this->POOL_PAGES; $i++) {
                        $result = $this->getPoolPage($i);

                        if ($page == 1) {
                            foreach ($result as $post) {
                                array_push($posts, $post);
                            }
                        }
                    }
                }

                return $posts;
            } else {
                return false;
            }
        } else {
            die("\rEmpty or invalid result from the API!\n");
        }
    }

    /**
     * Main function
     */
    public function run()
    {
        print("e621 Pool Downloader by Jack'lul <jacklul.com>  (v" . $this->VERSION . ") \n\n");

        if (!empty($this->UPDATE_URL)) {
            $updatecheckfile = ROOT . '/.updatecheck';

            if (!file_exists($updatecheckfile) || file_get_contents($updatecheckfile) < time() - 300) {
                file_put_contents($updatecheckfile, time());
                if (!$this->IS_LINUX) {
                    exec('attrib +H "' . $updatecheckfile . '"');
                }

                print("Checking for updates...");

                $ch = curl_init($this->UPDATE_URL);
                curl_setopt($ch, CURLOPT_USERAGENT, $this->NAME);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                $update_check = curl_exec($ch);
                curl_close($ch);

                if (!empty($update_check)) {
                    $update_check = json_decode($update_check, true);
                }

                $REMOTE_VERSION = $update_check['tag_name'];

                if ($REMOTE_VERSION !== "" && version_compare($this->VERSION, $REMOTE_VERSION, '<')) {
                    print("\r" . 'New version available - v' . $REMOTE_VERSION . "!\nDownload: https://github.com/jacklul/e621-Pool-Downloader/releases/latest\n\n");
                } else {
                    print("\r" . str_repeat(" ", 50) . "\r");
                }
            }
        }

        if (empty($this->POOL_ID)) {
            print("Please enter either:\n");
            print("- Pool URL / ID\n");
            print("- Path to downloaded pool\n\n");

            print("> ");

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $this->POOL_ID = $this->parseInput(trim(stream_get_line(STDIN, 255, PHP_EOL)));
            } else {
                $this->POOL_ID = $this->parseInput(trim(readline('')));
            }

            if (file_exists($this->POOL_ID . '/.poolinfo')) {
                $this->WORK_DIR = realpath($this->POOL_ID);
                $this->setPoolIDFromFile($this->WORK_DIR);
            } elseif (empty($this->POOL_ID) || !is_numeric($this->POOL_ID)) {
                die("\nInvalid input!\n");
            }

            print("\n");
        } else {
            print("Pool ID: " . $this->POOL_ID . "\n\n");
        }

        print("Getting pool info...");

        $posts = $this->getPoolPage();

        if ($posts) {
            $this->POOL_NAME = preg_replace('/[^a-z0-9_]/i', '', $this->POOL_NAME);
            $this->POOL_NAME = str_replace('_', ' ', $this->POOL_NAME);
			$this->POOL_NAME = preg_replace('!\s+!', ' ', $this->POOL_NAME);

            $infoFile = $this->WORK_DIR . '/' . $this->POOL_NAME . '/.poolinfo';

            if ($this->WORK_DIR == ROOT || $this->WORK_DIR == getcwd()) {
                $downloadDir = $this->WORK_DIR . '/' . $this->POOL_NAME;

                if (!is_dir($this->WORK_DIR . '/' . $this->POOL_NAME)) {
                    mkdir($this->WORK_DIR . '/' . $this->POOL_NAME);
                }

                if (!file_exists($infoFile)) {
                    file_put_contents($infoFile, 'ID=' . $this->POOL_ID . "\n");
                    if (!$this->IS_LINUX) {
                        exec('attrib +H "' . $infoFile . '"');
                    }
                }
            } else {
                $downloadDir = $this->WORK_DIR;
            }

            print("\rPool: " . $this->POOL_NAME . " (" . $this->POOL_IMAGES . " images, " . $this->POOL_PAGES . " pages)\n\n");

            $fileCount = 0;
            $filesDownloaded = 0;
            foreach ($posts as $post) {
                $fileCount++;
                $fileName = str_pad($fileCount, 3, "0", STR_PAD_LEFT) . '_' . $post['md5'] . '.' . $post['file_ext'];

                if (!file_exists($downloadDir . '/' . $fileName) || md5_file($downloadDir . '/' . $fileName) != $post['md5']) {
                    $this->LINE_BUFFER = 'Downloading image #' . $fileCount . '...';
                    $contents = $this->cURL($post['file_url']);

                    if ($contents) {
                        $filesDownloaded++;
                        $file = fopen($downloadDir . '/' . $fileName, 'wb');
                        fwrite($file, $contents);
                        fclose($file);
                    } else {
                        die("File download failed!\n");
                    }
                }
            }

            if ($filesDownloaded > 0) {
                $this->flushLine();
                print("Downloaded " . $filesDownloaded . " images.\n\n");
            } else {
                print("Nothing to download.\n\n");
            }
        } else {
            $this->flushLine();
            print("\rPool not found: " . $this->POOL_ID . "\n\n");
        }

        print("Finished in " . round(microtime(true) - $this->START_TIME, 3) . " seconds.\n");
    }
}

/**
 * Run it
 */
try {
    $app = new e621_Pool_Downloader((isset($argv[1]) ? $argv[1] : ''));
    $app->run();
} catch (\Exception $e) {
    print($e);
}
