<?php
/**
 * Dom Parser
 */

namespace App\Html;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Parser
 *
 * @package App\Html
 */
class Parser
{
    /**
     * Return list of topics data
     *
     * @param string $html
     * @return array
     */
    public static function getTopicsData($html): array
    {
        $data = self::getData($html);

        return array_map(function($topic) {
            return [
                'slug' => str_replace(LARACASTS_BASE_URL . '/topics/', '', $topic['path']),
                'path' => $topic['path'],
                'episode_count' => $topic['episode_count'],
                'series_count' => $topic['series_count']
            ];
        }, $data['props']['topics']);
    }


    public static function getSeriesData($seriesHtml): array
    {
        $data = self::getData($seriesHtml);

        return self::extractSeriesData($data['props']['series']);
    }

    /**
     * Return full list of series for given topic HTML page.
     *
     * @param string $html
     * @return array
     */
    public static function getSeriesDataFromTopic(string $html): array
    {
        $data = self::getData($html);

        $allSeries = $data['props']['topic']['series'];

        return array_combine(
            array_column($allSeries, 'slug'),
            array_map(function($series) {
                return self::extractSeriesData($series);
            }, $allSeries)
        );
    }

    /**
     * Only extracts data we need for each series and returns them
     *
     * @param array $series
     * @return array
     */
    public static function extractSeriesData(array $series): array
    {
        return [
            'slug' => $series['slug'],
            'path' => LARACASTS_BASE_URL . $series['path'],
            'episode_count' => $series['episodeCount'],
            'is_complete' => $series['complete']
        ];
    }

    /**
     * Return full list of episodes for given series HTML page.
     *
     * @param string $episodeHtml
     * @param number[] $filteredEpisodes
     * @return array
     */
    public static function getEpisodesData(string $episodeHtml, array $filteredEpisodes = []): array
    {
        $episodes = [];

        $data = self::getData($episodeHtml);

        $chapters = $data['props']['series']['chapters'];

        foreach ($chapters as $chapter) {
            foreach ($chapter['episodes'] as $episode) {
                // TODO: It's not the parser responsibility to filter episodes
                if (! empty($filteredEpisodes) and ! in_array($episode['position'], $filteredEpisodes)) {
                    continue;
                }

                // vimeoId is null for upcoming episodes
                if (! $episode['vimeoId']) {
                    continue;
                }

                $episodes[] = [
                    'title' => $episode['title'],
                    'vimeo_id' => $episode['vimeoId'],
                    'number' => $episode['position']
                ];
            }
        }

        return $episodes;
    }

    public static function getEpisodeDownloadLink($episodeHtml)
    {
        $data = self::getData($episodeHtml);

        return $data['props']['downloadLink'];
    }

    public static function extractLarabitsSeries($html): array
    {
        $html = str_replace('\/', '/', html_entity_decode($html));

        preg_match_all('"\/series\/([a-z-]+-larabits)"', $html, $matches);

        return array_unique($matches[1]);
    }

    public static function getCsrfToken($html): string
    {
        preg_match('/"csrfToken": \'([^\s]+)\'/', $html, $matches);

        return $matches[1];
    }

    public static function getUserData($html): array
    {

        $data = self::getData($html);

        $props = $data['props'];

        return [
            'error' => empty($props['errors']) ? null : $props['errors']['auth'],
            'signedIn' => $props['auth']['signedIn'],
            'data' => $props['auth']['user']
        ];
    }

    /**
     * Returns decoded version of data-page attribute in HTML page
     *
     * @param string $html
     * @return array
     */
    private static function getData($html): array
    {
        $parser = new Crawler($html);

        $data = $parser->filter("#app")->attr('data-page');

        return json_decode($data, true);
    }
}
