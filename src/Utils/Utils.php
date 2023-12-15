<?php
/**
 * Utilities
 */

namespace App\Utils;

/**
 * Class Utils
 *
 * @package App\Utils
 */
class Utils
{
    /**
     * New line supporting cli or browser.
     *
     * @return string
     */
    public static function newLine(): string
    {
        if (php_sapi_name() == "cli") {
            return "\n";
        }

        return "<br>";
    }

    /**
     * Counts the episodes from the array.
     *
     * @param $array
     * @return int
     */
    public static function countEpisodes($array): int
    {
        $total = 0;

        foreach ($array as $series) {
            $total += count($series['episodes']);
        }

        return $total;
    }

    /**
     * Compare two arrays and returns the diff array.
     *
     * @param $onlineListArray
     * @param $localListArray
     * @return array
     */
    public static function compareLocalAndOnlineSeries($onlineListArray, $localListArray): array
    {
        $seriesCollection = new SeriesCollection([]);

        foreach ($onlineListArray as $seriesSlug => $series) {

            if (array_key_exists($seriesSlug, $localListArray)) {
                if ($series['episode_count'] == count($localListArray[$seriesSlug])) {
                    continue;
                }

                $episodes = $series['episodes'];
                $series['episodes'] = [];

                foreach ($episodes as $episode) {
                    if (! in_array($episode['number'], $localListArray[$seriesSlug])) {
                        $series['episodes'][] = $episode;
                    }
                }

                $seriesCollection->add($series);
            } else {
                $seriesCollection->add($series);
            }
        }

        return $seriesCollection->get();
    }

    /**
     * Echo's text in a nice box.
     *
     * @param $text
     */
    public static function box($text): void
    {
        echo self::newLine();
        echo "====================================" . self::newLine();
        echo $text . self::newLine();
        echo "====================================" . self::newLine();
    }

    /**
     * Echo's a message.
     *
     * @param $text
     */
    public static function write($text): void
    {
        echo "> " . $text . self::newLine();
    }

    /**
     * Remove specials chars that windows does not support for filenames.
     *
     * @param $name
     * @return mixed
     */
    public static function parseEpisodeName($name): mixed
    {
        return preg_replace('/[^A-Za-z0-9\- _]/', '', $name);
    }

    /**
     * Echo's a message in a new line.
     *
     * @param $text
     */
    public static function writeln($text): void
    {
        echo self::newLine();
        echo "> " . $text . self::newLine();
    }

    /**
     * Convert bytes to precision
     *
     * @param $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes($bytes, int $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Calculate a percentage
     *
     * @param $cur
     * @param $total
     * @return float
     */
    public static function getPercentage($cur, $total): float
    {
        try
        {
            return round(($cur / $total) * 100);
        }
        catch (\DivisionByZeroError $e)
        {
            return floatval(0);
        }
    }

    public static function showProgressBar($downloadTotal, $downloadedBytes): void
    {
        if (php_sapi_name() === "cli") {
            printf("> Downloaded %s of %s (%d%%)      \r",
                Utils::formatBytes($downloadedBytes),
                Utils::formatBytes($downloadTotal),
                Utils::getPercentage($downloadedBytes, $downloadTotal)
            );
        }
    }
}
