<?php
/**
 * System Controller
 */
namespace App\System;

use App\Utils\Utils;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * Class Controller
 * @package App\System
 */
class Controller
{
    /**
     * Flysystem lib
     * @var Filesystem
     */
    private Filesystem $system;

    /**
     * Receives dependencies
     *
     * @param Filesystem $system
     */
    public function __construct(Filesystem $system)
    {
        $this->system = $system;
    }

    /**
     * Get the series
     *
     * @param bool $skip
     *
     * @return array
     * @throws FilesystemException
     */
    public function getSeries(bool $skip = false): array
    {
        $list  = $this->system->listContents(SERIES_FOLDER, true);
        $array = [];

        foreach ($list as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            }

            //skip folder, we only want the files
            if (str_starts_with($entry['filename'], '._')) {
                continue;
            }

            $series   = substr($entry['dirname'], strlen(SERIES_FOLDER) + 1);
            $episode = (int)substr($entry['filename'], 0, strpos($entry['filename'], '-'));

            $array[$series][] = $episode;
        }

        // TODO: #Issue# returns array with index 0
        if($skip) {
            foreach($this->getSkippedSeries() as $skipSeries => $episodes) {
                if(!isset($array[$skipSeries])) {
                    $array[$skipSeries] = $episodes;
                    continue;
                }

                $array[$skipSeries] = array_filter(
                    array_unique(
                        array_merge($array[$skipSeries], $episodes)
                    )
                );
            }
        }

        return $array;
    }

    /**
     * run write commands
     * @throws FilesystemException
     */
    public function writeSkipFiles(): void
    {
        Utils::box('Creating skip files');

        $this->writeSkipSeries();

        Utils::write('Skip files for series created');
    }

    /**
     * Create skip file to lessons
     * @throws FilesystemException
     */
    private function writeSkipSeries(): void
    {
        $file = SERIES_FOLDER . '/.skip';

        $series = serialize($this->getSeries(true));

        if($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, $series);
    }

    /**
     * Get skipped series
     * @return array
     * @throws FilesystemException
     */
    private function getSkippedSeries(): array
    {
        return $this->getSkippedData(SERIES_FOLDER . '/.skip');
    }

    /**
     * Read skip file
     *
     * @param $pathToSkipFile
     * @return array|mixed
     * @throws FilesystemException
     */
    private function getSkippedData($pathToSkipFile): mixed
    {
        if ($this->system->has($pathToSkipFile)) {
            $content = $this->system->read($pathToSkipFile);

            return unserialize($content);
        }

        return [];
    }

    /**
     * Create series folder if not exists.
     *
     * @param $seriesSlug
     * @throws FilesystemException
     */
    public function createSeriesFolderIfNotExists($seriesSlug): void
    {
        $this->createFolderIfNotExists(SERIES_FOLDER . '/' . $seriesSlug);
    }

    /**
     * Create folder if not exists.
     *
     * @param $folder
     * @throws FilesystemException
     */
    public function createFolderIfNotExists($folder): void
    {
        if ($this->system->has($folder) === false) {
            $this->system->createDirectory($folder);
        }
    }

    /**
     * Create cache file
     *
     * @param array $data
     * @throws FilesystemException
     */
    public function setCache(array $data): void
    {
        $file = 'cache.php';

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
    }

    /**
     * Get cached items
     *
     * @return array
     * @throws FilesystemException
     */
    public function getCache(): array
    {
        $file = 'cache.php';

        return $this->system->has($file)
            ? require $file
            : [];
    }
}
