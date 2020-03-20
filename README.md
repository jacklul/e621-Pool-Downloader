# e621 Pool Downloader

A script that can download a whole pool of images from [e621.net](https://e621.net) image board, also makes updating pools for ongoing comics easier!

## Requirements:

#### Windows

Package comes with compiled **PHP 5.6 library** (x86 Non Thread Safe)

You will need **Visual C++ 2012 Redistributable (x86)** for it to run - https://www.microsoft.com/en-us/download/details.aspx?id=30679

#### Linux

Install **PHP library** - `sudo apt-get install php-cli`

_cURL extension is optional, but recommended -  `sudo apt-get install php-curl`_

## Usage:

### Downloading:
- Launch the app by using a start script (Windows: **run.bat**, Linux: **run.sh**)
- Enter pool address in format: `https://e621.net/pool/show/378`, or just ID: `378` and hit enter
- Script will retrieve information about the pool and start downloading it to the current working directory

_Current working directory = directory the script was started from, usually it will be downloading to the directory in which the script is._

### Updating:
- Launch the app by using a start script (Windows: **run.bat**, Linux: **run.sh**)
- Enter path to locally downloaded pool, for example: `/home/jacklul/Downloads/Cruelty` and hit enter
- Script will retrieve information about the pool and start updating it - missing and corrupted files will be re-downloaded, newly added files will be downloaded
- Files that do not belong to the pool will be moved to `deleted` directory

**Script accepts arguments which can be either pool URL/ID or path to downloaded pool.**

_Moving directory over a launch script works well too!_

### Downloading blocked content - logging in:

- Rename `config.cfg.example` to `config.cfg`
- Fill your login details inside it

## License

See [LICENSE](https://github.com/jacklul/e621-Pool-Downloader/blob/master/LICENSE).
