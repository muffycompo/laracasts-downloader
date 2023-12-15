<?php

namespace App\Vimeo;

use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class VimeoDownloader
{
    /** @var VimeoRepository */
    private VimeoRepository $repository;

    /** @var Client */
    public Client $client;

    public function __construct()
    {
        $this->client = new Client();

        $this->repository = new VimeoRepository($this->client);
    }

    /**
     * @param $vimeoId
     * @param $filepath
     * @return bool
     * @throws GuzzleException
     */
    public function download($vimeoId, $filepath): bool
    {
        $video = $this->repository->get($vimeoId);

        $master = $this->repository->getMaster($video);

        $sources = [];
        $sources[] = $master->getVideoById($video->getVideoIdByQuality());
        $sources[] = $master->getAudio();

        $filenames = [];

        foreach ($sources as $source) {
            $filename = $master->getClipId().$source['extension'];
            $this->downloadSource(
                $master->resolveURL($source['base_url']),
                $source,
                $filename
            );
            $filenames[] = $filename;
        }

        return $this->mergeSources($filenames[0], $filenames[1], $filepath);
    }

    /**
     * @param $baseURL
     * @param $sourceData
     * @param $filepath
     * @return void
     * @throws GuzzleException
     */
    private function downloadSource($baseURL, $sourceData, $filepath): void
    {
        file_put_contents($filepath, base64_decode($sourceData['init_segment'], true));

        $segmentURLs = array_map(function($segment) use ($baseURL) {
            return $baseURL.$segment['url'];
        }, $sourceData['segments']);

        $sizes = array_column($sourceData['segments'], 'size');

        $this->downloadSegments($segmentURLs, $filepath, $sizes);
    }

    /**
     * @param $segmentURLs
     * @param $filepath
     * @param $sizes
     * @return void
     * @throws GuzzleException
     */
    private function downloadSegments($segmentURLs, $filepath, $sizes): void
    {
        $type = str_contains($filepath, 'm4v') ? 'video' : 'audio';

        Utils::writeln("Downloading $type...");

        $bytesDownloaded = 0;

        $totalBytes = array_sum($sizes);

        foreach ($segmentURLs as $index => $segmentURL) {
            $this->client->request('GET', $segmentURL, [
                'save_to' => fopen($filepath, 'a'),
                'progress' => function ($downloadTotal, $downloadedBytes) use ($bytesDownloaded, $totalBytes): void {
                    Utils::showProgressBar(
                        downloadTotal: $downloadTotal,
                        downloadedBytes: $downloadedBytes,
                        bytesDownloaded: $bytesDownloaded,
                        totalBytes: $totalBytes
                    );
                },
            ]);

            $bytesDownloaded += $sizes[$index];
        }
    }

    /**
     * @param string $videoPath
     * @param string $audioPath
     * @param string $outputPath
     *
     * @return bool
     */
    private function mergeSources(string $videoPath, string $audioPath, string $outputPath): bool
    {
        $code = 0;
        $output = [];

        if (PHP_OS === 'WINNT'){
            $command = "ffmpeg -i \"$videoPath\" -i \"$audioPath\" -vcodec copy -acodec copy -strict -2 \"$outputPath\" 2> nul";
        } else {
            $command = "ffmpeg -i '$videoPath' -i '$audioPath' -vcodec copy -acodec copy -strict -2 '$outputPath' >/dev/null 2>&1";
        }

        exec($command, $output, $code);

        if ($code === 0) {
            unlink($videoPath);
            unlink($audioPath);

            return true;
        }

        return false;
    }
}
