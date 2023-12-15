<?php

namespace App\Vimeo\DTO;

use GuzzleHttp\Psr7;
use Psr\Http\Message\UriInterface;

class MasterDTO
{
    /** @var array */
    private array $videos;

    /** @var array */
    private array $audios;

    /** @var string */
    private string $masterURL;

    /** @var string */
    private string $baseURL;

    /** @var string */
    private $clipId;

    /**
     * @return array
     */
    public function getVideos(): array
    {
        return array_map(function($video) {
            $video['extension'] = '.m4v';

            return $video;
        }, $this->videos);
    }

    /**
     * @param array $videos
     *
     * @return self
     */
    public function setVideos(array $videos): static
    {
        $this->videos = $videos;

        return $this;
    }

    /**
     * @return array
     */
    public function getAudios(): array
    {
        return array_map(function($audio) {
            $audio['extension'] = '.m4a';

            return $audio;
        }, $this->audios);
    }

    /**
     * @param array $audios
     *
     * @return self
     */
    public function setAudios(array $audios): static
    {
        $this->audios = $audios;

        return $this;
    }

    /**
     * Get video by id or the one with the highest quality
     *
     * @param string|null $id
     *
     * @return array
     */
    public function getVideoById(?string $id): array
    {
        $videos = $this->getVideos();

        if (! is_null($id)) {
            $ids = array_column($videos, 'id');
            $key = array_search($id, $ids);

            if ($key !== false) {
                return $videos[$key];
            }
        }

        usort($videos, function($a, $b) {
            return $a['height'] <=> $b['height'];
        });

        return end($videos);
    }

    public function getAudio()
    {
        $audios = $this->getAudios();

        usort($audios, function($a, $b) {
            return $a['bitrate'] <=> $b['bitrate'];
        });

        return end($audios);
    }

    /**
     * @return UriInterface
     */
    public function getMasterURL(): UriInterface
    {
        return Psr7\Utils::uriFor($this->masterURL);
    }

    /**
     * @param string $masterURL
     *
     * @return $this
     */
    public function setMasterURL(string $masterURL): static
    {
        $this->masterURL = $masterURL;

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseURL(): string
    {
        return $this->baseURL;
    }

    /**
     * @param string $baseURL
     *
     * @return self
     */
    public function setBaseURL(string $baseURL): static
    {
        $this->baseURL = $baseURL;

        return $this;
    }

    /**
     * Make final URL from combination of absolute and relate ones
     * @return string
     */
    public function resolveURL($url): string
    {
        return (string) Psr7\UriResolver::resolve(
            $this->getMasterURL(),
            Psr7\Utils::uriFor($this->getBaseURL().$url)
        );
    }

    /**
     * @return string
     */
    public function getClipId(): string
    {
        return $this->clipId;
    }

    /**
     * @param string $clipId
     *
     * @return self
     */
    public function setClipId(string $clipId): static
    {
        $this->clipId = $clipId;

        return $this;
    }

}
