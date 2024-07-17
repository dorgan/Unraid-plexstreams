<?php

require_once('./Server.php');

class PlexServer implements Server {
    private $host;
    private $identifier;
    private $port;
    private $token = '';
    private $name;

    public function __construct($cfg) {
        $this->name = $cfg['name'];
        $this->token = $cfg['token'];
        $this->identifier = $cfg['identifier'];
        $this->host = $cfg['host'];
    }

    public function getGeo($ip) {
        $url = 'https://plex.tv/api/v2/geoip?ip_address=' . $ip;
        $resp = getUrl($url);
        if (isset($resp['@attributes'])) {
            return $resp['@attributes']['city'] . ', ' . (isset($resp['@attributes']['subdivision']) ? $resp['@attributes']['subdivision'] . ' ' : '' ) . $resp['@attributes']['code'];
        } else {
            return false;
        }
    }

    public function getConfig() {
        return [
            'name'       => $this->name,
            'host'       => $this->host,
            'port'       => $this->port,
            'identifier' => $this->identifier,
            'token'      => $this->token,
        ];
    }

    public function getMediaDetails($media) {
        if (!empty($this->token)) {
            $url = urldecode($this->host) . urldecode($media) . '?X-Plex-Token=' . $this->token;
            $details = getUrl($url);
            $video = $details['Video'];
            $videoAttr = $video['@attributes'];
            $title = $videoAttr['title'];
            $directors = [];
            $genres = [];
    
            if (isset($video['Genre']['@attributes'])) {
                $video['Genre'] = [$video['Genre']];
            }
            if (isset($video['Director']['@attributes'])) {
                $video['Director'] = [$video['Director']];
            }
            if (isset($video['Genre'])) {
                foreach ($video['Genre'] as $genre) {
                    array_push($genres, $genre['@attributes']['tag']);
                }
            }
            $director = [];
            if (isset($video['Director'])) {
                foreach($video['Director'] as $director) {
                    array_push($directors, $director['@attributes']['tag']);
                }
            }
            
            return [
                'title'     => $title,
                'video'     => $video,
                'videoAttr' => $videoAttr,
                'genres'    => $genres,
                'directors' => $directors,
            ];
        }
    }

    private function getUrl($url) {
        $arrContextOptions=array(
            "http" => array(
                "method" => "GET",
                "header" => 
                    "Content-Type: application/xml; charset=utf-8;\r\n".
                    "Connection: close\r\n".
                    "Cache-Control: no-cache, no-store, must-revalidate, max-age=0\r\n".
                    "Pragma: no-cache\r\n",
                "ignore_errors" => true,
                "timeout" => (float)30.0
            ),
            "ssl"=>array(
                "allow_self_signed"=>true,
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            )
        );
        return json_decode(json_encode(simplexml_load_string(file_get_contents($url, false, stream_context_create($arrContextOptions)))), TRUE);
    }
}