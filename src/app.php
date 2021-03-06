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

namespace jacklul\e621dlpool;

define("ROOT", dirname(str_replace("phar://", "", __DIR__)));

/**
 * Class App
 *
 * @property int last_api_request
 */
class App
{
    /**
     * App name
     *
     * @var string
     */
    private $NAME = 'e621 Pool Downloader';

    /**
     * App version
     *
     * @var int
     */
    private $VERSION = '1.5.0';

    /**
     * App update URL
     *
     * @var string
     */
    private $UPDATE_URL = 'https://api.github.com/repos/jacklul/e621-Pool-Downloader/releases/latest';

    /**
     * User-Agent
     *
     * @var string
     */
    private $USER_AGENT = "e621 Pool Downloader (https://github.com/jacklul/e621-Pool-Downloader)";

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
     * Login for authorization
     *
     * @var string
     */
    private $LOGIN = '';

    /**
     * API key for authorization
     *
     * @var string
     */
    private $API_KEY = '';

    /**
     * Is posts prefetching enabled?
     *
     * @var string
     */
    private $PREFETCH = true;

    /**
     * Cache for posts data
     *
     * @var string
     */
    private $POSTS_CACHE = [];

    /**
     * Class constructor
     *
     * @param string $arg
     *
     * @throws Exception
     */
    public function __construct($arg = '')
    {
        if (!extension_loaded('curl')) {
            die("Required extension 'php-curl' not found!\n");
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

        /** @noinspection TypeUnsafeComparisonInspection */
        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
            $this->IS_LINUX = true;
        }

        set_time_limit(0);
        error_reporting(E_ERROR);
        date_default_timezone_set(date_default_timezone_get());

        $this->START_TIME = microtime(true);
        $this->last_api_request = 0;

        if (file_exists(ROOT . '/config.cfg')) {
            $config = parse_ini_file(ROOT . '/config.cfg');

            if (isset($config['LOGIN'])) {
                $this->LOGIN = $config['LOGIN'];
            }

            if (isset($config['API_KEY'])) {
                $this->API_KEY = $config['API_KEY'];
            }

            if (isset($config['PREFETCH'])) {
                $this->PREFETCH = (bool)$config['PREFETCH'];
            }
        }
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
            print(str_repeat(' ', 10) . "\r" . $this->LINE_BUFFER . ' ' . round(($progress * 100) / $total, 0)) . "%";
        }

        usleep(100);
    }

    /**
     * Parse user input
     *
     * @param  $string
     *
     * @return mixed
     */
    private function parseInput($string)
    {
        if (preg_match("/e621\.net\/pools\/(.*)/", $string, $matches)) {
            $string = $matches[1];
        }

        return $string;
    }

    /**
     * Perform simple cURL download request
     *
     * @param string $url
     * @param bool   $progress
     * @param bool   $e621auth
     *
     * @return mixed
     */
    private function cURL($url, $progress = true, $e621auth = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($e621auth && !empty($this->LOGIN) && !empty($this->API_KEY)) {
            curl_setopt($ch, CURLOPT_USERPWD,  $this->LOGIN . ":" . $this->API_KEY);
        }

        if ($progress) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);
        }

        return curl_exec($ch);
    }

    /**
     * Get needed data from e621 API
     *
     * @return mixed
     */
    private function getPool()
    {
        $result = $this->cURL('https://e621.net/pools.json?search[id]=' . $this->POOL_ID, false);
        $this->last_api_request = time();

        $result = json_decode($result, true);

        if (count($result) === 1 && is_array($result[0])) {
            $result = $result[0];
        }

        if (is_array($result)) {
            if (!empty($result) && !empty($result['post_ids'])) {
                if (!empty($result['name'])) {
                    $this->POOL_NAME = $result['name'];
                } else {
                    $this->POOL_NAME = $this->POOL_ID;
                }

                $this->POOL_IMAGES = $result['post_count'];

                $posts = [];
                foreach ($result['post_ids'] as $post) {
                    $posts[] = ['id' => $post];
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
     * Prefetch all posts data
     *
     * @param array $posts
     *
     * @return mixed
     * @throws \Exception
     */
    private function prefetchPosts($posts)
    {
        if (count($posts) > 100) {
            throw new \Exception('Cannot prefetch more than 100 posts at once!');
        }

        if ($this->last_api_request === time()) {
            sleep(1);
        }

        $posts_str = implode(',', $posts);

        $result = $this->cURL('https://e621.net/posts.json?tags=id:' . $posts_str, false, true);
        $this->last_api_request = time();

        $result = json_decode($result, true);

        if (is_array($result) && is_array($result['posts']) && count($result['posts']) > 0) {
            foreach ($result['posts'] as $post) {
                if (isset($post['id'], $post['file'])) {
                    $this->POSTS_CACHE[$post['id']] = $post;
                }
            }

            return true;
        } elseif (is_array($result) && is_array($result['posts']) && count($result['posts']) === 0) {
            return null;
        } else {
            print("\rEmpty or invalid result from the API!\n");

            print_r($result);
            exit;
        }
    }

    /**
     * Get needed post data from e621 API
     *
     * @param int $post_id
     *
     * @return mixed
     */
    private function getPost($post_id)
    {
        if (isset($this->POSTS_CACHE[$post_id])) {
            return $this->POSTS_CACHE[$post_id];
        }

        if ($this->last_api_request === time()) {
            sleep(1);
        }

        $result = $this->cURL('https://e621.net/posts.json?tags=id:' . $post_id, false, true);
        $this->last_api_request = time();

        $result = json_decode($result, true);

        if (is_array($result) && is_array($result['posts']) && count($result['posts']) === 1) {
            return isset($result['posts'][0]['file']) ? $result['posts'][0] : null;
        } elseif (is_array($result) && is_array($result['posts']) && count($result['posts']) === 0) {
            return null;
        } else {
            print("\rEmpty or invalid result from the API!\n");

            print_r($result);
            exit;
        }
    }

    /**
     * Main function
     *
     * @throws \Exception
     */
    public function run()
    {
        print($this->NAME . " (v" . $this->VERSION . ") \n\n");

        if (!empty($this->UPDATE_URL)) {
            $updatecheckfile = ROOT . '/.updatecheck';

            if (!file_exists($updatecheckfile) || filemtime($updatecheckfile) + 300 < time()) {
                touch($updatecheckfile);
                if (!$this->IS_LINUX) {
                    exec('attrib +H "' . $updatecheckfile . '"');
                }

                print("Checking for updates...");

                $ch = curl_init($this->UPDATE_URL);
                curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                /** @noinspection CurlSslServerSpoofingInspection */
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                /** @noinspection CurlSslServerSpoofingInspection */
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

        $posts = $this->getPool();

        if ($posts) {
            $this->POOL_NAME = preg_replace('/[^a-z0-9_]/i', '', $this->POOL_NAME);
            $this->POOL_NAME = str_replace('_', ' ', $this->POOL_NAME);
            $this->POOL_NAME = preg_replace('!\s+!', ' ', $this->POOL_NAME);

            $infoFile = $this->WORK_DIR . '/' . $this->POOL_NAME . '/.poolinfo';

            if ($this->WORK_DIR == ROOT || $this->WORK_DIR == getcwd()) {
                $downloadDir = $this->WORK_DIR . '/' . $this->POOL_NAME;

                if (!is_dir($this->WORK_DIR . '/' . $this->POOL_NAME)) {
                    /** @noinspection MkdirRaceConditionInspection */
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

            print("\rPool: " . $this->POOL_NAME . " (" . $this->POOL_IMAGES . " images)\n\n");

            if ($this->PREFETCH) {
                $this->LINE_BUFFER = 'Prefetching posts data...';
                print($this->LINE_BUFFER);

                $posts_simple = [];
                foreach ($posts as $post) {
                    $posts_simple[] = $post['id'];
                }

                $page = 1;
                $perPage = 100;
                $totalPages = ceil(count($posts_simple) / $perPage);
                while (count($posts_simple) > 0) {
                    $new_posts = array_slice($posts_simple, $perPage * ($page - 1), $perPage);

                    if (count($new_posts) === 0) {
                        break;
                    }

                    print("\r" . $this->LINE_BUFFER . ' page ' . $page . '/' . $totalPages);
                    $page++;

                    $this->prefetchPosts($new_posts);
                }

                print("\r" . $this->LINE_BUFFER . ' done      ' . "\n\n");
            }

            $filesList = [];
            $fileCount = 0;
            $filesDownloaded = 0;
            foreach ($posts as &$post) {
                $fileCount++;
                $this->LINE_BUFFER = 'Fetching image #' . $fileCount . '...';
                print($this->LINE_BUFFER);

                $post = $this->getPost($post['id']);
                if ($post === null) {
                    print(" post does not exist!\n");
                    continue;
                }

                if (empty($post['file']['url'])) {
                    print(" missing image url - authentication might be required!\n");
                    continue;
                }

                $fileName = str_pad($fileCount, 3, "0", STR_PAD_LEFT) . '_' . $post['file']['md5'] . '.' . $post['file']['ext'];

                if (!file_exists($downloadDir . '/' . $fileName) || md5_file($downloadDir . '/' . $fileName) !== $post['file']['md5']) {
                    $this->LINE_BUFFER .= ' downloading post #' . $post['id'] . '...';

                    $contents = $this->cURL($post['file']['url']);

                    if ($contents) {
                        $filesDownloaded++;
                        $file = fopen($downloadDir . '/' . $fileName, 'wb');
                        fwrite($file, $contents);
                        fclose($file);
                        print("\r" . $this->LINE_BUFFER . " done\n");
                    } else {
                        print("\r" . $this->LINE_BUFFER . " fail\n");
                    }
                } else {
                    print("\r" . $this->LINE_BUFFER . " no download required\n");
                }

                $filesList[] = $fileName;
            }
            unset($post);

            $removed = 0;
            foreach (new \DirectoryIterator($downloadDir) as $fileInfo) {
                if ($fileInfo->isDot() || $fileInfo->isDir() || $fileInfo->getFilename() == '.poolinfo') {
                    continue;
                }

                $md5 = md5_file($downloadDir . '/' . $fileInfo->getFilename());

                foreach ($posts as $post) {
                    if ($md5 === $post['file']['md5'] && in_array($fileInfo->getFilename(), $filesList, true)) {
                        continue 2;
                    }
                }

                $destination_original = $downloadDir . '/deleted/' . $md5;

                if (file_exists($destination_original . '.' . $fileInfo->getExtension())) {
                    $i = 0;
                    do {
                        $i++;
                        $destination = $destination_original . '_' . $i;
                    } while (file_exists($destination . '.' . $fileInfo->getExtension()));

                    $destination_original = $destination;
                }

                if (!is_dir($downloadDir . '/deleted/')) {
                    /** @noinspection MkdirRaceConditionInspection */
                    mkdir($downloadDir . '/deleted/');
                }

                rename($downloadDir . '/' . $fileInfo->getFilename(), $destination_original . '.' . $fileInfo->getExtension());

                $removed++;
            }

            $this->flushLine();

            if ($filesDownloaded > 0) {
                print("\nDownloaded " . $filesDownloaded . " images.\n");
            } else {
                print("\nNothing to download.\n");
            }

            if ($removed > 0) {
                print("Removed " . $removed . " images.\n");
            }

            print("\n");
        } else {
            $this->flushLine();
            print("\rPool not found: " . $this->POOL_ID . "\n\n");
        }

        print("Finished in " . round(microtime(true) - $this->START_TIME, 3) . " seconds.\n");
    }
}
