<?php
require_once('./Server.php');
require_once('./PlexServer.php');

class MediaServer implements Server {

    private $server;

    public function __construct($cfgSettings) {
        if ($cfgSettings['type'] === 'plex') {
            $this->server = new PlexServer($cfgSettings);
        }
    }

    public function getGeo($ip) {
        return $this->server->getGeo($ip);
    }

    public function getConfig() {
        return $this->server->getConfig();
    }

    public function getMediaDetails($media) {
        return $this->server->getMediaDetails($media);
    }
}