<?php

global $options;

/**
 * App start point
 */

use App\Downloader;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter as Adapter;

require_once 'bootstrap.php';

/*
 * Dependencies
 */
$client = new GuzzleHttp\Client([
    'base_url' => LARACASTS_BASE_URL
]);
$filesystem = new Filesystem(new Adapter(BASE_FOLDER));
$bench = new Ubench();

/*
 * App
 */
$app = new Downloader(
    httpClient: $client,
    system: $filesystem,
    bench: $bench,
    retryDownload: RETRY_DOWNLOAD
);

try {
    $app->start($options);
} catch (FilesystemException $e) {
    echo 'ERROR: '.$e->getMessage();
}
