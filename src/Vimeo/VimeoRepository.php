<?php

namespace App\Vimeo;

use App\Vimeo\DTO\MasterDTO;
use App\Vimeo\DTO\VideoDTO;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class VimeoRepository
{
    /** @var Client */
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param $vimeoId
     * @return VideoDTO
     * @throws GuzzleException
     */
    public function get($vimeoId): VideoDTO
    {
        $content = $this->client->get("https://player.vimeo.com/video/$vimeoId", [
            'headers' => [
                'Referer' => 'https://laracasts.com/',
            ],
        ])
            ->getBody()
            ->getContents();

        preg_match('/"streams":(\[{.+?}])/', $content, $streams);

        preg_match('/"(?:google_skyfire|akfire_interconnect_quic)":({.+?})/', $content, $cdns);

        $vimeo = new VideoDTO();

        return $vimeo->setMasterURL(json_decode($cdns[1], true)['url'])
            ->setStreams(json_decode($streams[1], true));
    }

    /**
     * @param VideoDTO $video
     * @return MasterDTO
     * @throws GuzzleException
     */
    public function getMaster(VideoDTO $video): MasterDTO
    {
        $content = $this->client->get($video->getMasterURL())
            ->getBody()
            ->getContents();

        $data = json_decode($content, true);

        $master = new MasterDTO();
        return $master
            ->setMasterURL($video->getMasterURL())
            ->setBaseURL($data['base_url'])
            ->setClipId($data['clip_id'])
            ->setAudios($data['audio'])
            ->setVideos($data['video']);
    }
}
