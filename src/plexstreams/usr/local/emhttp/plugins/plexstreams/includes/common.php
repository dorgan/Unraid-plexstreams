<?php
    if (isset($GLOBALS['unRaidSettings'])) {
        define('OS_VERSION', 'Unraid ' . $GLOBALS['unRaidSettings']['version']);
    }
    define('PLUGIN_VERSION', '2023.03.26');

    function maskDebugValue($value) {
        $value = (string)$value;
        $length = strlen($value);

        return str_repeat('*', max(4, $length - 4)) . ($length > 4 ? substr($value, -4) : '');
    }

    function sanitizeDebugData($value, $field = '') {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = sanitizeDebugData($item, (string)$key);
            }
            return $sanitized;
        }

        if (preg_match('/token|api[_-]?key|authorization|password|secret|^key$/i', $field)) {
            return maskDebugValue($value);
        }

        if (is_string($value)) {
            return preg_replace_callback(
                '/([?&](?:X-Plex-Token|token|api[_-]?key|authorization|password|secret|key)=)([^&#\s]+)/i',
                function ($matches) {
                    return $matches[1] . maskDebugValue($matches[2]);
                },
                $value
            );
        }

        return $value;
    }

    function debugLog($cfg, $message, $context = []) {
        if (($cfg['DEBUG_LOGGING'] ?? '0') !== '1') {
            return;
        }

        $entry = [
            'timestamp' => gmdate('c'),
            'message' => $message,
            'context' => sanitizeDebugData($context)
        ];
        $logFile = '/boot/config/plugins/plexstreams/plexstreams.log';
        if (file_exists($logFile) && filesize($logFile) >= 2 * 1024 * 1024) {
            @rename($logFile, $logFile . '.1');
        }
        @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    function getGeo($ip) {
        static $cache = null;
        $cacheFile = '/boot/config/plugins/plexstreams/geoip-cache.json';
        if ($cache === null) {
            $savedCache = @file_get_contents($cacheFile);
            $cache = is_string($savedCache) ? json_decode($savedCache, true) : [];
            if (!is_array($cache)) {
                $cache = [];
            }
        }
        if (isset($cache[$ip]) && $cache[$ip]['expiresAt'] >= time()) {
            return $cache[$ip]['location'];
        }

        $url = 'https://plex.tv/api/v2/geoip?ip_address=' . $ip;
        $resp = getXml($url, 2);
        if (isset($resp['@attributes'])) {
            $location = $resp['@attributes']['city'] . ', ' . (isset($resp['@attributes']['subdivision']) ? $resp['@attributes']['subdivision'] . ' ' : '') . $resp['@attributes']['code'];
            $cache[$ip] = ['expiresAt' => time() + 3600, 'location' => $location];
            @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
            return $location;
        }
    }

    function getServers($cfg) {
        $url = 'https://plex.tv/devices.xml?X-Plex-Token=' . $cfg['TOKEN'];
        $url2 = 'https://plex.tv/api/resources?X-Plex-Token=' .$cfg['TOKEN'] . ($cfg['FORCE_PLEX_HTTPS'] === '1' ? '&includeHttps=1' : '');
        debugLog($cfg, 'Starting Plex server discovery', ['devicesUrl' => $url, 'resourcesUrl' => $url2]);
        $servers = getXml($url);
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
                            $serverList[$device['@attributes']['clientIdentifier']] = [
                                'Name' => $device['@attributes']['name'],
                                'Identifier' => $device['@attributes']['clientIdentifier'],
                                'Connections' => []
                            ];
                        }
                    }
                }
                if (count($serverList) > 0) {
                    $connections = getXml($url2);
                    if ($connections !== false) {
                        foreach($connections['Device'] as $device) {
                            $identifier = $device['@attributes']['clientIdentifier'];
                            if (isset($serverList[$identifier])) {
                                foreach($device['Connection'] as $connection) {
                                    if (isset($connection['@attributes'])) {
                                        array_push($serverList[$identifier]['Connections'], $connection['@attributes']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            return false;
        }

        debugLog($cfg, 'Completed Plex server discovery', ['serverCount' => count($serverList)]);
        return $serverList;
    }

    function getConfiguredHosts($cfg) {
        return array_values(array_filter(array_map('trim', array_merge(
            explode(',', $cfg['HOST'] ?? ''),
            explode(',', $cfg['CUSTOM_SERVERS'] ?? '')
        )), function($host) {
            return $host !== '';
        }));
    }

    function isConfiguredPlexHost($host, $cfg) {
        $host = rtrim($host, '/');
        foreach (getConfiguredHosts($cfg) as $configuredHost) {
            if ($host === rtrim($configuredHost, '/')) {
                return true;
            }
        }

        return false;
    }

    function buildPlexImageUrl($host, $imagePath, $token) {
        if (!is_string($imagePath) || strpos($imagePath, '/') !== 0) {
            return false;
        }

        return rtrim($host, '/') . $imagePath . (strpos($imagePath, '?') === false ? '?' : '&') . 'X-Plex-Token=' . urlencode($token);
    }

    function buildStreamImageUrl($host, $imagePath) {
        if (!is_string($imagePath) || $imagePath === '') {
            return '';
        }

        $parts = parse_url($imagePath);
        if ($parts !== false && isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return $imagePath;
        }

        return '/plugins/plexstreams/getImage.php?img=' . urlencode($imagePath) . '&host=' . urlencode($host);
    }

    function getLiveTvChannelThumb($source, $cfg, $ratingKey) {
        static $cache = null;
        $cacheFile = '/boot/config/plugins/plexstreams/livetv-art-cache.json';
        $cacheKey = ($source['@host'] ?? '') . ':' . $ratingKey;

        if ($ratingKey === '' || empty($source['@host']) || empty($cfg['TOKEN'])) {
            return '';
        }

        if ($cache === null) {
            $savedCache = @file_get_contents($cacheFile);
            $cache = is_string($savedCache) ? json_decode($savedCache, true) : [];
            if (!is_array($cache)) {
                $cache = [];
            }
        }

        if (isset($cache[$cacheKey]) && $cache[$cacheKey]['expiresAt'] >= time()) {
            return $cache[$cacheKey]['thumb'];
        }

        $url = rtrim($source['@host'], '/') . '/library/metadata/' . rawurlencode($ratingKey) . '?X-Plex-Token=' . urlencode($cfg['TOKEN']);
        $metadata = getXml($url, 2);
        $attributes = $metadata['MediaContainer']['Metadata']['@attributes']
            ?? $metadata['MediaContainer']['Video']['@attributes']
            ?? $metadata['Metadata']['@attributes']
            ?? $metadata['Video']['@attributes']
            ?? [];
        $thumb = $attributes['channelThumb'] ?? '';

        if ($thumb === '') {
            $mediaItems = $metadata['MediaContainer']['Video']['Media'] ?? $metadata['Video']['Media'] ?? [];
            foreach (normalizeXmlList($mediaItems) as $mediaItem) {
                $thumb = $mediaItem['@attributes']['channelThumb'] ?? '';
                if ($thumb !== '') {
                    break;
                }
            }
        }

        if ($thumb !== '') {
            $cache[$cacheKey] = ['expiresAt' => time() + 3600, 'thumb' => $thumb];
            @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
        }

        return $thumb;
    }

    function setPluginPageVisibility($pagePath, $visible) {
        $disabledPath = $pagePath . '.off';
        if (!$visible && file_exists($pagePath)) {
            rename($pagePath, $disabledPath);
        } else if ($visible && file_exists($disabledPath)) {
            rename($disabledPath, $pagePath);
        }
    }

    function getStreams($cfg) {
        $hosts = getConfiguredHosts($cfg);
        $streams = [];

        foreach($hosts as $host) {
            if (!empty($cfg['TOKEN'])) {
                $streams[] = $host . "/status/sessions?X-Plex-Token=" . $cfg['TOKEN'] . '&_m=' . time();
            }
        }

        return !empty($cfg['TOKEN']) && !empty($streams) ? getXmlBatch($streams, $cfg) : [];
    }

    function getMergedStreams($cfg) {
        $mergedStreams = mergeStreams(getStreams($cfg), $cfg);
        debugLog($cfg, 'Retrieved active Plex streams', ['streams' => $mergedStreams]);
        return $mergedStreams;
    }

    function getServerHost($url) {
        $urlParts = parse_url($url);
        if ($urlParts === false || !isset($urlParts['scheme'], $urlParts['host'])) {
            return '';
        }

        return $urlParts['scheme'] . '://' . $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');
    }

    function getServerSummaries($cfg) {
        $servers = [];
        $urls = [];
        $identityUrls = [];

        foreach (getConfiguredHosts($cfg) as $host) {
            $normalizedHost = rtrim($host, '/');
            $shortHost = parse_url($normalizedHost, PHP_URL_HOST) ?: $normalizedHost;
            $servers[$normalizedHost] = [
                'host' => $normalizedHost,
                'alias' => getServerAlias($shortHost, $cfg) ?: $shortHost,
                'name' => getServerAlias($shortHost, $cfg) ?: $shortHost,
                'online' => false,
                'version' => '',
                'claimed' => null,
                'liveTv' => false,
                'tuners' => false
            ];
            $urls[] = $normalizedHost . '/?X-Plex-Token=' . urlencode($cfg['TOKEN']);
            $identityUrls[] = $normalizedHost . '/identity?X-Plex-Token=' . urlencode($cfg['TOKEN']);
        }

        foreach (getXmlBatch($urls, $cfg) as $response) {
            $host = getServerHost($response['url']);
            if ($host === '' || !isset($servers[$host])) {
                continue;
            }

            $attributes = $response['content']['@attributes'] ?? [];
            $servers[$host]['name'] = $attributes['friendlyName'] ?? $servers[$host]['name'];
            $servers[$host]['online'] = true;
            $servers[$host]['version'] = $attributes['version'] ?? '';
            $servers[$host]['claimed'] = ($attributes['claimed'] ?? '') === '1';
            $servers[$host]['liveTv'] = (int)($attributes['livetv'] ?? 0) > 0;
            $servers[$host]['tuners'] = ($attributes['allowTuners'] ?? '') === '1';
        }

        foreach (getXmlBatch($identityUrls, $cfg) as $response) {
            $host = getServerHost($response['url']);
            if ($host !== '' && isset($servers[$host])) {
                $servers[$host]['claimed'] = ($response['content']['@attributes']['claimed'] ?? '') === '1';
            }
        }

        return array_values($servers);
    }

    function parseXml($response) {
        if ($response === false || $response === '') {
            return false;
        }

        $xml = simplexml_load_string($response);
        return $xml === false ? false : json_decode(json_encode($xml), true);
    }

    function getXml($url, $timeout = 30) {
        $arrContextOptions = array(
            "http" => array(
                "method" => "GET",
                "header" => "Content-Type: application/xml; charset=utf-8;\r\nConnection: close\r\nCache-Control: no-cache, no-store, must-revalidate, max-age=0\r\nPragma: no-cache\r\n",
                "ignore_errors" => true,
                "timeout" => (float)$timeout
            ),
            "ssl" => array(
                "allow_self_signed" => true,
                "verify_peer" => false,
                "verify_peer_name" => false,
            )
        );
        return parseXml(@file_get_contents($url, false, stream_context_create($arrContextOptions)));
    }

    function createBatchResponse($effectiveUrl, $response) {
        $urlParts = parse_url($effectiveUrl);
        $content = parseXml($response);

        if ($urlParts === false || !isset($urlParts['scheme'], $urlParts['host']) || $content === false) {
            return false;
        }

        $url = $urlParts['scheme'] . '://' . $urlParts['host'];
        if (isset($urlParts['port'])) {
            $url .= ':' . $urlParts['port'];
        }
        $url .= $urlParts['path'] ?? '';
        if (isset($urlParts['query'])) {
            $url .= '?' . $urlParts['query'];
        }

        return array('url' => $url, 'content' => $content);
    }

    function getCurlFailureType($responseCode, $errorCode) {
        if ($responseCode > 0) {
            return 'http';
        }

        $transportFailures = [
            CURLE_OPERATION_TIMEDOUT => 'timeout',
            CURLE_COULDNT_RESOLVE_HOST => 'dns',
            CURLE_COULDNT_CONNECT => 'connection',
            CURLE_SSL_CONNECT_ERROR => 'tls',
            60 => 'tls'
        ];

        return $transportFailures[$errorCode] ?? 'transport';
    }

    function getXmlBatch($urls, $cfg = []) {
        $rets = [];
        $multi = [];
        $mh = curl_multi_init();
        foreach($urls as $idx=>$url) {
            $id = 'streams-' . $idx;
            $multi[$id] = curl_init();
            curl_setopt($multi[$id], CURLOPT_URL, $url);
            curl_setopt($multi[$id], CURLOPT_HEADER, 0);
            curl_setopt($multi[$id], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($multi[$id], CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($multi[$id], CURLOPT_SSL_VERIFYSTATUS, 0);
            curl_setopt($multi[$id], CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($multi[$id], CURLOPT_TIMEOUT, 30);
            curl_setopt($multi[$id], CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($multi[$id], CURLOPT_RETURNTRANSFER, 1);
            curl_multi_add_handle($mh, $multi[$id]);
        }

        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach($multi as $idx=>$handle) {
            $responseCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($responseCode >= 200 && $responseCode < 300) {
                $response = createBatchResponse(curl_getinfo($handle, CURLINFO_EFFECTIVE_URL), curl_multi_getcontent($handle));
                if ($response !== false) {
                    $rets[$idx] = $response;
                }
            } else {
                $errorCode = curl_errno($handle);
                debugLog($cfg, 'Plex stream request failed', [
                    'url' => curl_getinfo($handle, CURLINFO_EFFECTIVE_URL),
                    'responseCode' => $responseCode,
                    'failureType' => getCurlFailureType($responseCode, $errorCode),
                    'errorCode' => $errorCode,
                    'error' => curl_error($handle)
                ]);
            }
            curl_multi_remove_handle($mh, $handle);
            curl_close($handle);
        }

        curl_multi_close($mh);
        return $rets;
    }

    function formatPlaybackTiming($duration, $viewOffset, $display, $roundEndSeconds, $emptyProgress) {
        if ($duration === null) {
            return [
                'lengthInSeconds' => null,
                'lengthInMinutes' => null,
                'lengthSeconds' => null,
                'lengthMinutes' => null,
                'lengthHours' => null,
                'currentPosition' => null,
                'currentPositionInSeconds' => null,
                'currentPositionInMinutes' => null,
                'currentPositionSeconds' => null,
                'currentPositionMinutes' => null,
                'currentPositionHours' => null,
                'percentPlayed' => $emptyProgress,
                'currentPositionDisplay' => null,
                'lengthDisplay' => null,
                'endSecondsFromNow' => null,
                'endTime' => null
            ];
        }

        $lengthInSeconds = $duration / 1000;
        $lengthInMinutes = ceil($lengthInSeconds / 60);
        $lengthSeconds = floor((int)$lengthInSeconds % 60);
        $lengthMinutes = floor(((int)$lengthInSeconds % 3600) / 60);
        $lengthHours = floor(((int)$lengthInSeconds % 86400) / 3600);
        $currentPosition = (float)(int)$viewOffset;
        $currentPositionInSeconds = $viewOffset / 1000;
        $currentPositionInMinutes = ceil($currentPositionInSeconds / 60);
        $currentPositionSeconds = floor((int)$currentPositionInSeconds % 60);
        $currentPositionMinutes = floor(((int)$currentPositionInSeconds % 3600) / 60);
        $currentPositionHours = floor(((int)$currentPositionInSeconds % 86400) / 3600);
        $endSecondsFromNow = $lengthInSeconds - $currentPositionInSeconds;

        if ($roundEndSeconds) {
            $endSecondsFromNow = ceil($endSecondsFromNow);
        }

        $endTime = date('h:i A', strtotime('+ ' . $endSecondsFromNow . ' seconds'));
        if ($display['time'] == '%R' && $display['date'] != '%c') {
            $endTime = date('H:i', strtotime('+ ' . $endSecondsFromNow . ' seconds'));
        }

        return [
            'lengthInSeconds' => $lengthInSeconds,
            'lengthInMinutes' => $lengthInMinutes,
            'lengthSeconds' => $lengthSeconds,
            'lengthMinutes' => $lengthMinutes,
            'lengthHours' => $lengthHours,
            'currentPosition' => $currentPosition,
            'currentPositionInSeconds' => $currentPositionInSeconds,
            'currentPositionInMinutes' => $currentPositionInMinutes,
            'currentPositionSeconds' => $currentPositionSeconds,
            'currentPositionMinutes' => $currentPositionMinutes,
            'currentPositionHours' => $currentPositionHours,
            'percentPlayed' => $lengthInMinutes > 0 ? round(($currentPositionInMinutes / $lengthInMinutes) * 100, 0) : $emptyProgress,
            'currentPositionDisplay' => str_pad($currentPositionHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionSeconds, 2, '0', STR_PAD_LEFT),
            'lengthDisplay' => str_pad($lengthHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthSeconds, 2, '0', STR_PAD_LEFT),
            'endSecondsFromNow' => $endSecondsFromNow,
            'endTime' => $endTime
        ];
    }

    function getServerAlias($shortHost, $cfg) {
        $aliasKey = 'ALIAS-' . str_replace('.', '_', $shortHost);
        return $cfg[$aliasKey] ?? '';
    }

    function getLocationDisplay($location, $address) {
        $location = strtoupper($location);
        return $location . ' (' . $address . ($location !== 'LAN' ? ' - ' . getGeo($address) : '') . ')';
    }

    function getStateIcon($state) {
        if ($state === 'paused') {
            return 'pause';
        }

        return $state === 'playing' ? 'play' : 'buffer';
    }

    function getPlaybackTimingFields($timing) {
        unset($timing['endSecondsFromNow']);
        return $timing;
    }

    function createStreamBase($source, $cfg, $media, $item, $type, $title, $titleString, $duration, $artPath, $thumbPath) {
        $player = $item['Player']['@attributes'];
        $user = $item['User']['@attributes'];
        $session = $item['Session']['@attributes'];

        return [
            '@host' => $source['@host'],
            'serverHost' => $source['@host'],
            'alias' => getServerAlias($source['shortHost'], $cfg),
            'id' => $media['@attributes']['id'],
            'type' => $type,
            'player' => $player['product'],
            'title' => $title,
            'titleString' => $titleString,
            'key' => $item['@attributes']['key'],
            'duration' => $duration,
            'artUrl' => buildStreamImageUrl($source['@host'], $artPath),
            'thumbUrl' => buildStreamImageUrl($source['@host'], $thumbPath),
            'user' => $user['title'],
            'userAvatar' => $user['thumb'],
            'state' => $player['state'],
            'stateIcon' => getStateIcon($player['state']),
            'length' => $duration,
            'location' => $session['location'],
            'address' => $player['address'],
            'bandwidth' => round((int)$session['bandwidth'] / 1000, 1)
        ];
    }

    function normalizeXmlList($value) {
        if (!is_array($value)) {
            return [];
        }

        return isset($value['@attributes']) ? [$value] : $value;
    }

    function getVideoTitle($video, $media) {
        $attributes = $video['@attributes'];
        if (isset($media['@attributes']['origin'])) {
            return $attributes['title'];
        }

        $title = $attributes['title'] . (isset($attributes['year']) ? ' (' . $attributes['year'] . ')' : '');
        if (isset($attributes['parentTitle'])) {
            $title = $attributes['parentTitle'] . ' - ' . $title;
        }
        if (isset($attributes['grandparentTitle']) && $attributes['grandparentTitle'] !== $title) {
            $title = $attributes['grandparentTitle'] . ' - ' . $title;
        }

        return $title;
    }

    function getAudioTitles($audio) {
        $attributes = $audio['@attributes'];
        return [
            'title' => $attributes['title'] . ' - ' . $attributes['originalTitle'] . '<br/><span style="font-size:8px;">' . $attributes['parentTitle'] . '</span>',
            'titleString' => $attributes['title'] . ' - ' . $attributes['originalTitle'] . ' - ' . $attributes['parentTitle']
        ];
    }

    function formatStreamDecision($decision) {
        return $decision === 'directplay' ? 'Direct Play' : $decision;
    }

    function mergeStreams($allStreams, $cfg) {
        global $display;

        $mergedStreams = [];
        $videoStreams = [];
        foreach($allStreams as $idx=>$details) {
            $urlParts = parse_url($details['url']);
            if ($urlParts !== false) {
                $source = (is_array($details['content'])) ? $details['content'] : [];
                $source['@host'] = $urlParts['scheme'] . '://' . $urlParts['host'] . ':' . $urlParts['port'];
                $source['shortHost'] = $urlParts['host'];
                if (stripos($idx, 'streams-') !== false) {
                    $videoStreams[] = $source;
                }
            }
        }

        foreach($videoStreams as $streams) {
            if (isset($streams['Video'])) {
                foreach(normalizeXmlList($streams['Video']) as $idx=>$video) {
                    
                    foreach(normalizeXmlList($video['Media'] ?? []) as $media) {
                        if (isset($media['@attributes']['selected']) && $media['@attributes']['selected'] === '1') {
                            $title = getVideoTitle($video, $media);
                            $duration = $media['Part']['@attributes']['duration'] ?? null;
                            $timing = formatPlaybackTiming($duration, $video['@attributes']['viewOffset'], $display, true, 0);
                            $channelThumb = $video['@attributes']['channelThumb']
                                ?? $media['@attributes']['channelThumb']
                                ?? $media['Part']['@attributes']['channelThumb']
                                ?? '';
                            if ($channelThumb === '' && $duration === null) {
                                $channelThumb = $video['@attributes']['grandparentThumb']
                                    ?? $video['@attributes']['thumb']
                                    ?? '';
                                $metadataThumb = getLiveTvChannelThumb($streams, $cfg, $video['@attributes']['ratingKey'] ?? '');
                                $channelThumb = $metadataThumb ?: $channelThumb;
                            }
                            $artThumb = $channelThumb ?: ($video['@attributes']['art'] ?? '');

                            $mergedStream = array_merge(
                                createStreamBase(
                                    $streams,
                                    $cfg,
                                    $media,
                                    $video,
                                    'video',
                                    $title,
                                    $title,
                                    $duration,
                                    $artThumb,
                                    $video['@attributes']['grandparentThumb'] ?? $video['@attributes']['thumb'] ?? $artThumb
                                ),
                                [
                                'endSecondsFromNow' => $timing['endSecondsFromNow']
                                ],
                                getPlaybackTimingFields($timing),
                                ['streamInfo' => []]
                            );

                            $mergedStream['locationDisplay'] = getLocationDisplay($mergedStream['location'], $mergedStream['address']);
                            
                            foreach (normalizeXmlList($media['Part']['Stream'] ?? []) as $stream) {
                                if ($stream['@attributes']['streamType'] === '2') {
                                    $mergedStream['streamInfo']['audio'] = $stream;
                                    $mergedStream['streamInfo']['audio']['@attributes']['decision'] = $mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? 'direct play';
                                } else if ($stream['@attributes']['streamType'] === '1') {
                                    $mergedStream['streamInfo']['video'] = $stream;
                                    $mergedStream['streamInfo']['video']['@attributes']['decision'] = $mergedStream['streamInfo']['video']['@attributes']['decision'] ?? 'direct play';
                                }
                            }
                            
                            $mergedStream['streamDecision'] = formatStreamDecision($media['Part']['@attributes']['decision']);

                            if ($mergedStream['streamDecision'] === 'transcode') {
                                if ($mergedStream['streamInfo']['video']['@attributes']['decision'] === 'transcode') {
                                    $mergedStream['streamInfo']['video']['@attributes']['decision'] .= $video['TranscodeSession']['@attributes']['transcodeHwRequested'] === '1' ?  ' (HW)' : '' . '<br/>' . $mergedStream['streamInfo']['video']['@attributes']['displayTitle'] . ' -> ' . $media['@attributes']['videoResolution'];
                                }
                                if ($mergedStream['streamInfo']['audio']['@attributes']['decision'] === 'transcode') {
                                    $mergedStream['streamInfo']['audio']['@attributes']['decision'] .= ' (' . $video['TranscodeSession']['@attributes']['sourceAudioCodec'] . ' -> ' . $video['TranscodeSession']['@attributes']['audioCodec'] .')';
                                }
                            }

                            $mergedStreams[] = $mergedStream;
                        }
                    }
                }
            }
            if (isset($streams['Track'])) {
                foreach(normalizeXmlList($streams['Track']) as $idx=>$audio) {
                    
                    foreach(normalizeXmlList($audio['Media'] ?? []) as $media) {
                        foreach(normalizeXmlList($media['Part'] ?? []) as $part) {
                            foreach (normalizeXmlList($part['Stream'] ?? []) as $stream) {
                                if ($stream['@attributes']['selected'] === '1') {
                                    $titles = getAudioTitles($audio);
                                    $title = $titles['title'];
                                    $titleString = $titles['titleString'];
                                    $duration = $part['@attributes']['duration'];
                                    $timing = formatPlaybackTiming($duration, $audio['@attributes']['viewOffset'], $display, false, '');
                                    $mergedStream = array_merge(
                                        createStreamBase(
                                            $streams,
                                            $cfg,
                                            $media,
                                            $audio,
                                            'audio',
                                            $title,
                                            $titleString,
                                            $duration,
                                            $audio['@attributes']['art'],
                                            $audio['@attributes']['grandparentThumb'] ?? $audio['@attributes']['thumb']
                                        ),
                                        getPlaybackTimingFields($timing),
                                        ['streamInfo' => []]
                                    );
                                    if ($mergedStream['location'] === null) {
                                        if ($audio['Player']['@attributes']['local'] == "1") {
                                            $mergedStream['location'] = 'LAN';
                                        }
                                    }

                                    $mergedStream['locationDisplay'] = getLocationDisplay($mergedStream['location'], $mergedStream['address']);
                                    $mergedStream['streamDecision'] = formatStreamDecision($part['@attributes']['decision'] ?? 'Direct Play');

                                    $mergedStream['streamInfo']['audio'] = $stream;
                                    $mergedStream['streamInfo']['audio']['@attributes']['decision'] = $mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? 'direct play';

                                    $mergedStreams[] = $mergedStream;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $mergedStreams;
    }

?>