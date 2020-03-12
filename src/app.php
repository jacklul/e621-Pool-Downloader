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
    private $VERSION = '1.2.0';

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
     * Class constructor
     *
     * @param  string $arg
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
        $this->last_api_request = 0;
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
     * @param  $url
     * @param  bool $progress
     * @return mixed
     */
    private function cURL($url, $progress = true)
    {
        if ($this->USE_CURL) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
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
            $result = file_get_contents($url, false, stream_context_create(['http' => ['user_agent' => $this->USER_AGENT]]));
            return $result;
        }
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
     * Get needed post data from e621 API
     *
     * @param  int $post_id
     * @return mixed
     */
    private function getPost($post_id)
    {
        if ($this->last_api_request === time()) {
            sleep(1);
        }

        $result = $this->cURL('https://e621.net/posts.json?tags=id:' . $post_id, false);
        $this->last_api_request = time();

        $result = json_decode($result, true);

        if (is_array($result) && is_array($result['posts']) && count($result['posts']) === 1) {
			return isset($result['posts'][0]['file']) ? $result['posts'][0] : null;
        } elseif (is_array($result) && is_array($result['posts']) && count($result['posts']) === 0) {
            return null;
        } else {
            print("\rEmpty or invalid result from the API!\n");
        }
    }
	
    /**
     * Main function
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

        $posts = $this->getPool();

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

            print("\rPool: " . $this->POOL_NAME . " (" . $this->POOL_IMAGES . " images)\n\n");

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
                    print(" missing image url!\n");
                    continue;
                }

                $fileName = str_pad($fileCount, 3, "0", STR_PAD_LEFT) . '_' . $post['file']['md5'] . '.' . $post['file']['ext'];

                if (!file_exists($downloadDir . '/' . $fileName) || md5_file($downloadDir . '/' . $fileName) != $post['file']['md5']) {
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
            }
            unset($post);

            $removed = 0;
            foreach (new \DirectoryIterator($downloadDir) as $fileInfo) {
                if ($fileInfo->isDot() || $fileInfo->isDir() || $fileInfo->getFilename() == '.poolinfo') {
                    continue;
                }

                $md5 = md5_file($downloadDir . '/' . $fileInfo->getFilename());

                foreach ($posts as $post) {
                    if ($md5 === $post['file']['md5']) {
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
