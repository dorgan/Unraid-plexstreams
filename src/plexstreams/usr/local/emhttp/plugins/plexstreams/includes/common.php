<?php
    define('OS_VERSION', 'Unraid ' . $GLOBALS['unRaidSettings']['version']);
    define('PLUGIN_VERSION', 'v1.1.1');

    function getServers($cfg) {
        $url = 'https://plex.tv/devices.xml?X-Plex-Token=' . $cfg['TOKEN'];

        $servers = getUrl($url);
        if ($servers !== false) {
            $serverList = [];
            if (isset($servers['@attributes'])) {
                $servers = [$servers];
            }
            foreach($servers as $server) {
                if (isset($server['Device']['@attributes'])) {
                    $server['Device'] = [$server['Device']];
                }
                foreach($server['Device'] as $device) {
                    if (isset($device['@attributes']['provides'])) {
                        $providers = explode(',', $device['@attributes']['provides']);
                        if (in_array('server', $providers)) {
                            $server = [
                                'Name' => $device['@attributes']['name'],
                                'IP' => []
                            ];
                            if (isset($device['Connection']['@attibutes'])) {
                                $device['Connection'] = [$device['Connection']];
                            }
                            foreach($device['Connection'] as $connection) {
                                array_push($server['IP'], $connection['@attributes']['uri']);
                            }
                            array_push($serverList, $server);
                        }
                    }
                }
            }
        } else {
            return false;
        }

        return $serverList;
    }

    function generateServerList($cfg, $name, $id, $selected) {
        $servers = getServers($cfg);
        $retVal = '
                <select name="' .$name . '" id="' .$id .'">
        ';
        foreach($servers as $server) {
            foreach($server['IP'] as $ip) {
                $retVal .= '<option value="'  .$ip .'"' .($selected === $ip ? ' selected="selected"' : '') . '>' .$server['Name'] .' (' . $ip .')' . '</option>';
            }
        }
        $retVal .= '</select>';

        return $retVal;
    }

    function getStreams($host, $cfg) {
        $retArray = [];
        $url = $host . "/status/sessions?X-Plex-Token=" . $cfg['TOKEN'] .'&_m=' .mktime();
        if (isset($_REQUEST['dbg'])) {
            v_d($url);
        }
        return getUrl($url);
    }

    function v_d($obj) {
        echo('<pre>');
        var_dump($obj);
        echo('</pre>');
    }

    function getUrl($url) {
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

    function mergeStreams($streams) {
        $mergedStreams = [];

        if (isset($streams['Video'])) {
            if (isset($streams['Video']) && isset($streams['Video']['@attributes'])) {
                $streams['Video'] = [$streams['Video']];
            }
            foreach($streams['Video'] as $idx=>$video) {
                if (isset($video['Media']['@attributes'])) {
                    $video['Media'] = [$video['Media']];
                }
                foreach($video['Media'] as $media) {
                    if ($media['@attributes']['selected'] === '1') {
                        $title = $video['@attributes']['title'] . ' (' . $video['@attributes']['year'] . ')';
                        if (isset($video['@attributes']['parentTitle'])) {
                            $title = $video['@attributes']['parentTitle'] . ' - ' . $title;
                        }
                        if (isset($video['@attributes']['grandparentTitle'])) {
                            $title = $video['@attributes']['grandparentTitle'] . ' - ' . $title;
                        }

                        $duration = $media['Part']['@attributes']['duration'];
                        $lengthInSeconds = $duration / 1000;
                        $lengthInMinutes = ceil($lengthInSeconds / 60 );
                        $lengthSeconds = floor($lengthInSeconds%60);
                        $lengthMinutes = floor(($lengthInSeconds%3600)/60);
                        $lengthHours = floor(($lengthInSeconds%86400)/3600);
                        
                        $currentPosition = floatval((int)$video['@attributes']['viewOffset']);
                        $currentPositionInSeconds = $video['@attributes']['viewOffset'] / 1000;
                        $currentPositionInMinutes = ceil($currentPositionInSeconds / 60);
                        $currentPositionSeconds = floor($currentPositionInSeconds%60);
                        $currentPositionMinutes = floor(($currentPositionInSeconds%3600)/60);
                        $currentPositionHours = floor(($currentPositionInSeconds%86400)/3600);
                        
                        $mergedStream = [
                            'type' => 'video',
                            'title' => $title,
                            'key' => $video['@attributes']['key'],
                            'duration' => $duration,
                            'artUrl' => '/plugins/plexstreams/getImage.php?img=' . urlencode($video['@attributes']['art']),
                            'thumbUrl' => '/plugins/plexstreams/getImage.php?img=' .  urlencode($video['@attributes']['grandparentThumb'] ?? $video['@attributes']['thumb']),
                            'user' => $video['User']['@attributes']['title'],
                            'userAvatar' => $video['User']['@attributes']['thumb'],
                            'state' => $video['Player']['@attributes']['state'],
                            'stateIcon' => 'play',
                            'length' => $duration,
                            'lengthInSeconds' => $lengthInSeconds,
                            'lengthInMinutes' => $lengthInMinutes,
                            'lengthSeconds' => $lengthInSeconds,
                            'lengthMinutes' => $lengthMinuites,
                            'lengthHours' => $lengthHours,
                            'currentPosition' => $currentPosition,
                            'currentPositionInSeconds' =>  $currentPositionInSeconds,
                            'currentPositionInMinutes' =>  $currentPositionInMinutes,
                            'currentPositionSeconds' => $currentPositionSeconds,
                            'currentPositionMinutes' => $currentPositionMinutes,
                            'currentPositionHours' => $currentPositionHours,
                            'percentPlayed' => round(($currentPositionInMinutes/ $lengthInMinutes) * 100, 0),
                            'currentPositionDisplay' => str_pad($currentPositionHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionSeconds, 2, '0', STR_PAD_LEFT),
                            'lengthDisplay' => str_pad($lengthHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthSeconds, 2, '0', STR_PAD_LEFT),
                            'location' => $video['Session']['@attributes']['location'],
                            'address' => $video['Player']['@attributes']['address'],
                            'bandwidth' => round((int)$video['Session']['@attributes']['bandwidth'] / 1000, 1),
                            'streamInfo' => []
                        ];

                        if ($mergedStream['state'] === 'paused') {
                            $mergedStream['stateIcon'] = 'pause';
                        } else if ($mergedStream['state'] !== 'playing') {
                            $mergedStream['stateIcon'] = 'buffer';
                        }

                        foreach ($media['Part']['Stream'] as $stream) {
                            if ($stream['@attributes']['streamType'] === '2') {
                                $mergedStream['streamInfo']['audio'] = $stream;
                                $mergedStream['streamInfo']['audio']['@attributes']['decision'] = $mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? 'direct play';
                            } else if ($stream['@attributes']['streamType'] === '1') {
                                $mergedStream['streamInfo']['video'] = $stream;
                                $mergedStream['streamInfo']['video']['@attributes']['decision'] = $mergedStream['streamInfo']['video']['@attributes']['decision'] ?? 'direct play';
                            }
                        }
                        
                        $mergedStream['streamDecision'] = $media['Part']['@attributes']['decision'];
                        if ($mergedStream['streamDecision'] === 'directplay') {
                            $mergedStream['streamDecision'] = 'Direct Play';
                        }

                        $mergedStreams[] = $mergedStream;
                    }
                }
            }
        }

        if (isset($streams['Track'])) {
            if (isset($streams['Track']) && isset($streams['Track']['@attributes'])) {
                $streams['Track'] = [$streams['Track']];
            }
            foreach($streams['Track'] as $idx=>$audio) {
                if (isset($audio['Media']['@attributes'])) {
                    $audio['Media'] = [$audio['Media']];
                }
                foreach($audio['Media'] as $media) {
                    if ($media['@attributes']['selected'] === '1') {
                        $title = $audio['@attributes']['title'] . ' - ' . $audio['@attributes']['originalTitle'] . '<br/><span style="font-size:8px;">' . $audio['@attributes']['parentTitle'] . '</span>';
                        
                        $duration = $media['Part']['@attributes']['duration'];
                        $lengthInSeconds = $duration / 1000;
                        $lengthInMinutes = ceil($lengthInSeconds / 60 );
                        $lengthSeconds = floor($lengthInSeconds%60);
                        $lengthMinutes = floor(($lengthInSeconds%3600)/60);
                        $lengthHours = floor(($lengthInSeconds%86400)/3600);
                        
                        $currentPosition = floatval((int)$audio['@attributes']['viewOffset']);
                        $currentPositionInSeconds = $audio['@attributes']['viewOffset'] / 1000;
                        $currentPositionInMinutes = ceil($currentPositionInSeconds / 60);
                        $currentPositionSeconds = floor($currentPositionInSeconds%60);
                        $currentPositionMinutes = floor(($currentPositionInSeconds%3600)/60);
                        $currentPositionHours = floor(($currentPositionInSeconds%86400)/3600);

                        $mergedStream = [
                            'type' => 'audio',
                            'title' => $title,
                            'key' => $audio['@attributes']['key'],
                            'duration' => $duration,
                            'artUrl' => '/plugins/plexstreams/getImage.php?img=' . urlencode($audio['@attributes']['art']),
                            'thumbUrl' => '/plugins/plexstreams/getImage.php?img=' .  urlencode($audio['@attributes']['grandparentThumb'] ?? $audio['@attributes']['thumb']),
                            'user' => $audio['User']['@attributes']['title'],
                            'userAvatar' => $audio['User']['@attributes']['thumb'],
                            'state' => $audio['Player']['@attributes']['state'],
                            'stateIcon' => 'play',
                            'length' => $duration,
                            'lengthInSeconds' => $lengthInSeconds,
                            'lengthInMinutes' => $lengthInMinutes,
                            'lengthSeconds' => $lengthInSeconds,
                            'lengthMinutes' => $lengthMinuites,
                            'lengthHours' => $lengthHours,
                            'currentPosition' => $currentPosition,
                            'currentPositionInSeconds' =>  $currentPositionInSeconds,
                            'currentPositionInMinutes' =>  $currentPositionInMinutes,
                            'currentPositionSeconds' => $currentPositionSeconds,
                            'currentPositionMinutes' => $currentPositionMinutes,
                            'currentPositionHours' => $currentPositionHours,
                            'percentPlayed' => round(($currentPositionInMinutes/ $lengthInMinutes) * 100, 0),
                            'currentPositionDisplay' => str_pad($currentPositionHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionSeconds, 2, '0', STR_PAD_LEFT),
                            'lengthDisplay' => str_pad($lengthHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthSeconds, 2, '0', STR_PAD_LEFT),
                            'location' => $audio['Session']['@attributes']['location'],
                            'address' => $audio['Player']['@attributes']['address'],
                            'bandwidth' => round((int)$audio['Session']['@attributes']['bandwidth'] / 1000, 1),
                            'streamInfo' => []
                        ];

                        if ($mergedStream['state'] === 'paused') {
                            $mergedStream['stateIcon'] = 'pause';
                        } else if ($mergedStream['state'] !== 'playing') {
                            $mergedStream['stateIcon'] = 'buffer';
                        }
                        foreach ($media['Part']['Stream'] as $stream) {
                            if ($stream['streamType'] === '2') {
                                $mergedStream['streamInfo']['audio'] = $stream;
                                $mergedStream['streamInfo']['audio']['@attributes']['decision'] = $mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? 'direct play';
                            }
                        }
                        $mergedStream['streamDecision'] = $media['Part']['@attributes']['decision'];
                        if ($mergedStream['streamDecision'] === 'directplay') {
                            $mergedStream['streamDecision'] = 'Direct Play';
                        }

                        $mergedStreams[] = $mergedStream;
                    }
                }
            }
        }

        return $mergedStreams;
    }

?>