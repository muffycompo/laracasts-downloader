<?php

/**
 * App start point
 */
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter as Adapter;
use App\System\Controller;

require_once 'bootstrap.php';

$filesystem = new Filesystem(new Adapter(BASE_FOLDER));

try {
    (new Controller($filesystem))->writeSkipFiles();
} catch (FilesystemException $e) {
}
