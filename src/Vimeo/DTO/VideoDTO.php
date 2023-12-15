<?php

namespace App\Vimeo\DTO;

class VideoDTO
{
    /**
     * @var string
     */
    private string $masterURL;

    /**
     * @var array
     */
    private array $streams;

    /**
     * @return string
     */
    public function getMasterURL(): string
    {
        return $this->masterURL;
    }

    /**
     * @param  string  $masterURL
     *
     * @return self
     */
    public function setMasterURL($masterURL): static
    {
        $this->masterURL = $masterURL;

        return $this;
    }

    /**
     * @return array
     */
    public function getStreams(): array
    {
        return $this->streams;
    }

    /**
     * @param  array  $streams
     *
     * @return self
     */
    public function setStreams(array $streams): static
    {
        $this->streams = $streams;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getVideoIdByQuality(): ?string
    {
        $id = null;

        foreach ($this->getStreams() as $stream) {
            if ($stream['quality'] === getenv('VIDEO_QUALITY')) {
                $id = explode("-", $stream['id'])[0];
            }
        }

        return $id;
    }
}
