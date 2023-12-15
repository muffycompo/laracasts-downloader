<?php
/**
 * Http functions
 */

namespace App\Http;

use App\Html\Parser;
use App\Utils\Utils;
use App\Vimeo\VimeoDownloader;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Query;
use Ubench;

/**
 * Class Resolver
 *
 * @package App\Http
 */
class Resolver
{
    /**
     * Guzzle client
     *
     * @var Client
     */
    private Client $client;

    /**
     * Guzzle cookie
     *
     * @var CookieJar
     */
    private CookieJar $cookies;

    /**
     * Ubench lib
     *
     * @var Ubench
     */
    private Ubench $bench;

    /**
     * Retry download on connection fail
     *
     * @var bool
     */
    private bool $retryDownload;

    /**
     * Receives dependencies
     *
     * @param  Client  $client
     * @param  Ubench  $bench
     * @param bool $retryDownload
     */
    public function __construct(Client $client, Ubench $bench, bool $retryDownload = false)
    {
        $this->client = $client;
        $this->cookies = new CookieJar();
        $this->bench = $bench;
        $this->retryDownload = $retryDownload;
    }

    /**
     * Tries to authenticate user.
     *
     * @param string $email
     * @param string $password
     *
     * @return array
     */
    public function login(string $email, string $password): array
    {
        $token = $this->getCsrfToken();

        try {
            $response = $this->client->post(LARACASTS_POST_LOGIN_PATH, [
                'cookies' => $this->cookies,
                'headers' => [
                    "X-XSRF-TOKEN" => $token,
                    'content-type' => 'application/json',
                    'x-requested-with' => 'XMLHttpRequest',
                    'referer' => LARACASTS_BASE_URL,
                ],
                'body' => json_encode([
                    'email' => $email,
                    'password' => $password,
                    'remember' => 1,
                ]),
                'verify' => false,
            ]);

            $html = $response->getBody()->getContents();

            return Parser::getUserData($html);
        } catch (GuzzleException $e) {
            echo $e->getMessage().PHP_EOL;

            return [];
        }
    }

    /**
     * Returns CSRF token
     *
     * @return string
     */
    public function getCsrfToken(): string
    {
        try {
            $this->client->get(LARACASTS_BASE_URL, [
                'cookies' => $this->cookies,
                'headers' => [
                    'content-type' => 'application/json',
                    'accept' => 'application/json',
                    'referer' => LARACASTS_BASE_URL,
                ],
                'verify' => false,
            ]);

            $token = current(
                array_filter($this->cookies->toArray(), function($cookie) {
                    return $cookie['Name'] === 'XSRF-TOKEN';
                })
            );

            return urldecode($token['Value']);
        } catch (GuzzleException $e) {
            echo $e->getMessage().PHP_EOL;

            return '';
        }
    }

    /**
     * Download the episode of the series.
     *
     * @param string $seriesSlug
     * @param array $episode
     *
     * @return bool
     * @throws GuzzleException
     */
    public function downloadEpisode(string $seriesSlug, array $episode): bool
    {
        try {
            $number = sprintf("%02d", $episode['number']);
            $name = $episode['title'];
            $filepath = $this->getFilename($seriesSlug, $number, $name);

            Utils::writeln(
                sprintf(
                    "Download started: %s . . . . Saving on ".SERIES_FOLDER.'/'.$seriesSlug,
                    $number.' - '.$name
                )
            );

            $source = getenv('DOWNLOAD_SOURCE');

            if (! $source or $source === 'laracasts') {
                $downloadLink = $this->getLaracastsLink($seriesSlug, $episode['number']);

                return $this->downloadVideo($downloadLink, $filepath);
            } else {
                $vimeoDownloader = new VimeoDownloader();

                return $vimeoDownloader->download($episode['vimeo_id'], $filepath);
            }
        } catch (RequestException $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * @param string $seriesSlug
     * @param string $number
     * @param string $episodeName
     *
     * @return string
     */
    private function getFilename(string $seriesSlug, string $number, string $episodeName): string
    {
        return BASE_FOLDER
            .DIRECTORY_SEPARATOR
            .SERIES_FOLDER
            .DIRECTORY_SEPARATOR
            .$seriesSlug
            .DIRECTORY_SEPARATOR
            .$number
            .'-'
            .Utils::parseEpisodeName($episodeName)
            .'.mp4';
    }

    /**
     * Returns topics page html
     *
     * @return string
     */
    public function getTopicsHtml(): string
    {
        try {
            return $this->client
                ->get(LARACASTS_BASE_URL . '/' . LARACASTS_TOPICS_PATH, ['cookies' => $this->cookies, 'verify' => false])
                ->getBody()
                ->getContents();
        } catch (GuzzleException $e) {
            echo $e->getMessage().PHP_EOL;

            return '';
        }
    }

    /**
     * Returns html content of specific url
     *
     * @param string $url
     *
     * @return string
     */
    public function getHtml(string $url): string
    {
        try {
            return $this->client
                ->get($url, ['cookies' => $this->cookies, 'verify' => false])
                ->getBody()
                ->getContents();
        } catch (GuzzleException $e) {
            echo $e->getMessage().PHP_EOL;

            return '';
        }
    }

    /**
     * Get Laracasts download link for given episode
     *
     * @param string $serieSlug
     * @param int $episodeNumber
     *
     * @return string
     */
    private function getLaracastsLink(string $seriesSlug, int $episodeNumber): string
    {
        $episodeHtml = $this->getHtml("series/$seriesSlug/episodes/$episodeNumber");

        return Parser::getEpisodeDownloadLink($episodeHtml);
    }

    /**
     * Helper to get the Location header.
     *
     * @param $url
     *
     * @return string
     */
    private function getRedirectUrl($url): string
    {
        try {
            $response = $this->client->get($url, [
                'cookies' => $this->cookies,
                'allow_redirects' => false,
                'verify' => false,
            ]);

            return $response->getHeader('Location')[0];

        } catch (GuzzleException $e) {
            echo $e->getMessage().PHP_EOL;

            return '';
        }
    }

    /**
     * Helper to download the video.
     *
     * @param $downloadUrl
     * @param $saveTo
     *
     * @return bool
     */
    private function downloadVideo($downloadUrl, $saveTo): bool
    {
        $this->bench->start();

        $link = $this->prepareDownloadLink($downloadUrl);

        try {
//            $downloadedBytesSoFar = file_exists($saveTo) ? filesize($saveTo) : 0;

            $this->client->request(
                method: 'GET',
                uri: $link['url'],
                options: [
                    'query' => Query::parse($link['query'], false),
                    'save_to' => fopen($saveTo, 'a'),
                    'progress' => function ($downloadTotal, $downloadedBytes): void {
                        if (php_sapi_name() === "cli") {
                            printf("> Downloaded %s of %s (%d%%)      \r",
                                Utils::formatBytes($downloadedBytes),
                                Utils::formatBytes($downloadTotal),
                                Utils::getPercentage($downloadedBytes, $downloadTotal)
                            );
                        }
                    },
                ]
            );

        } catch (GuzzleException $e) {
            echo $e->getMessage().PHP_EOL;

            return false;
        }

        $this->bench->end();

        Utils::write(
            sprintf(
                "Elapsed time: %s, Memory: %s       ",
                $this->bench->getTime(),
                $this->bench->getMemoryUsage()
            )
        );

        return true;
    }

    /**
     * @param string $url
    */
    private function prepareDownloadLink(string $url): array
    {
        $url = $this->getRedirectUrl($url);
        $url = $this->getRedirectUrl($url);
        $parts = parse_url($url);

        return [
            'query' => $parts['query'],
            'url' => $parts['scheme'].'://'.$parts['host'].$parts['path']
        ];
    }
}
