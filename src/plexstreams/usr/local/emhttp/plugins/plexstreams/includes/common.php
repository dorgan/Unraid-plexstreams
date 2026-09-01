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

    function getLiveTvChannelContext($source, $cfg, $ratingKey) {
        static $cache = null;
        $cacheFile = '/boot/config/plugins/plexstreams/livetv-context-cache.json';
        $cacheKey = ($source['@host'] ?? '') . ':' . $ratingKey;

        if ($ratingKey === '' || empty($source['@host']) || empty($cfg['TOKEN'])) {
            return [];
        }

        if ($cache === null) {
            $savedCache = @file_get_contents($cacheFile);
            $cache = is_string($savedCache) ? json_decode($savedCache, true) : [];
            if (!is_array($cache)) {
                $cache = [];
            }
        }

        if (isset($cache[$cacheKey]) && $cache[$cacheKey]['expiresAt'] >= time()) {
            return $cache[$cacheKey]['context'];
        }

        $url = rtrim($source['@host'], '/') . '/library/metadata/' . rawurlencode($ratingKey) . '?X-Plex-Token=' . urlencode($cfg['TOKEN']);
        $metadata = getXml($url, 2);
        $mediaItems = $metadata['MediaContainer']['Video']['Media'] ?? $metadata['Video']['Media'] ?? [];
        $context = ['channel' => '', 'network' => ''];
        foreach (normalizeXmlList($mediaItems) as $mediaItem) {
            $attributes = $mediaItem['@attributes'] ?? [];
            $context['channel'] = $attributes['channelCallSign'] ?? '';
            $context['network'] = $attributes['network'] ?? '';
            if ($context['channel'] !== '' || $context['network'] !== '') {
                break;
            }
        }

        $cache[$cacheKey] = ['expiresAt' => time() + 3600, 'context' => $context];
        @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
        return $context;
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

    function terminatePlexSession($host, $sessionId, $reason, $token) {
        $query = http_build_query([
            'sessionId' => $sessionId,
            'reason' => $reason,
            'X-Plex-Token' => $token
        ]);
        $handle = curl_init(rtrim($host, '/') . '/status/sessions/terminate?' . $query);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($handle, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($handle);
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        return [
            'success' => $response !== false && $statusCode >= 200 && $statusCode < 300,
            'statusCode' => $statusCode,
            'error' => $error
        ];
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
                'provider' => 'plex',
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

    function getPlexContainerCount($host, $path, $token) {
        $response = getXml(rtrim($host, '/') . $path . (strpos($path, '?') === false ? '?' : '&') . 'X-Plex-Token=' . urlencode($token), 5);
        $attributes = $response['MediaContainer']['@attributes'] ?? $response['@attributes'] ?? [];

        return isset($attributes['totalSize']) ? (int)$attributes['totalSize'] : (int)($attributes['size'] ?? 0);
    }

    function getServerDetails($cfg) {
        $details = [];

        foreach (getConfiguredHosts($cfg) as $host) {
            $host = rtrim($host, '/');
            $root = getXml($host . '/?X-Plex-Token=' . urlencode($cfg['TOKEN']), 5);
            $rootAttributes = $root['MediaContainer']['@attributes'] ?? $root['@attributes'] ?? [];
            $sectionResponse = getXml($host . '/library/sections?X-Plex-Token=' . urlencode($cfg['TOKEN']), 5);
            $sections = normalizeXmlList($sectionResponse['MediaContainer']['Directory'] ?? $sectionResponse['Directory'] ?? []);
            $libraries = [];

            foreach ($sections as $section) {
                $attributes = $section['@attributes'] ?? [];
                $type = $attributes['type'] ?? '';
                if (!in_array($type, ['movie', 'show', 'artist'], true)) {
                    continue;
                }

                $libraries[$type] = ($libraries[$type] ?? 0) + getPlexContainerCount(
                    $host,
                    '/library/sections/' . rawurlencode($attributes['key']) . '/all?X-Plex-Container-Start=0&X-Plex-Container-Size=0',
                    $cfg['TOKEN']
                );

                if ($type === 'show') {
                    $libraries['episode'] = ($libraries['episode'] ?? 0) + getPlexContainerCount(
                        $host,
                        '/library/sections/' . rawurlencode($attributes['key']) . '/all?type=4&X-Plex-Container-Start=0&X-Plex-Container-Size=0',
                        $cfg['TOKEN']
                    );
                }
                if ($type === 'artist') {
                    $libraries['album'] = ($libraries['album'] ?? 0) + getPlexContainerCount(
                        $host,
                        '/library/sections/' . rawurlencode($attributes['key']) . '/all?type=9&X-Plex-Container-Start=0&X-Plex-Container-Size=0',
                        $cfg['TOKEN']
                    );
                }
            }

            $dvrResponse = getXml($host . '/livetv/dvrs?X-Plex-Token=' . urlencode($cfg['TOKEN']), 5);
            $dvrs = normalizeXmlList($dvrResponse['MediaContainer']['Dvr'] ?? $dvrResponse['Dvr'] ?? []);
            $tuners = 0;
            foreach ($dvrs as $dvr) {
                foreach (normalizeXmlList($dvr['Device'] ?? []) as $device) {
                    if (($device['@attributes']['state'] ?? '') === 'enabled') {
                        $tuners += (int)($device['@attributes']['tuners'] ?? 0);
                    }
                }
            }

            $details[$host] = [
                'host' => $host,
                'remoteAccess' => ($rootAttributes['myPlexMappingState'] ?? '') === 'mapped' ? 'direct' : 'unknown',
                'libraries' => $libraries,
                'activeLiveTv' => getPlexContainerCount($host, '/livetv/sessions', $cfg['TOKEN']),
                'tuners' => $tuners
            ];
        }

        return array_values($details);
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
        $player = $item['Player']['@attributes'] ?? [];
        $user = $item['User']['@attributes'] ?? [];
        $session = $item['Session']['@attributes'] ?? [];
        $itemAttributes = $item['@attributes'] ?? [];
        $userTitle = $user['title'] ?? '';
        $userIsUnknown = $userTitle === '';

        return [
            '@host' => $source['@host'],
            'serverHost' => $source['@host'],
            'alias' => getServerAlias($source['shortHost'], $cfg),
            'id' => $media['@attributes']['id'],
            'type' => $type,
            'player' => $player['product'] ?? 'Plex',
            'client' => [
                'product' => $player['product'] ?? 'Plex',
                'name' => $player['title'] ?? '',
                'platform' => $player['platform'] ?? '',
                'device' => $player['device'] ?? '',
                'machineIdentifier' => $player['machineIdentifier'] ?? ''
            ],
            'connection' => [
                'location' => $session['location'] ?? '',
                'relayed' => ($player['relayed'] ?? '0') === '1'
            ],
            'mediaIdentity' => [
                'seriesTitle' => $itemAttributes['grandparentTitle'] ?? '',
                'season' => $itemAttributes['parentIndex'] ?? null,
                'episode' => $itemAttributes['index'] ?? null,
                'episodeTitle' => $itemAttributes['title'] ?? ''
            ],
            'title' => $title,
            'titleString' => $titleString,
            'key' => $item['@attributes']['key'],
            'duration' => $duration,
            'artUrl' => buildStreamImageUrl($source['@host'], $artPath),
            'thumbUrl' => buildStreamImageUrl($source['@host'], $thumbPath),
            'user' => $userIsUnknown ? 'Unknown' : $userTitle,
            'userIsUnknown' => $userIsUnknown,
            'userAvatar' => $user['thumb'] ?? '',
            'state' => $player['state'] ?? 'buffering',
            'stateIcon' => getStateIcon($player['state'] ?? 'buffering'),
            'length' => $duration,
            'location' => $session['location'] ?? '',
            'address' => $player['address'] ?? '',
            'bandwidth' => round((int)($session['bandwidth'] ?? 0) / 1000, 1),
            'clientIdentifier' => $player['machineIdentifier'] ?? '',
            'sessionId' => $session['id'] ?? $session['sessionKey'] ?? $itemAttributes['sessionKey'] ?? null
        ];
    }

    function getAudioChannelLabel($attributes) {
        $channels = (int)($attributes['channels'] ?? $attributes['audioChannels'] ?? 0);
        if ($channels === 1) {
            return 'Mono';
        }
        if ($channels === 2) {
            return 'Stereo';
        }
        if ($channels === 6) {
            return '5.1';
        }
        if ($channels === 8) {
            return '7.1';
        }
        return $channels > 0 ? $channels . ' ch' : '';
    }

    function getVideoQualityLabel($attributes, $fallbackResolution = '', $fallbackCodec = '') {
        $height = $attributes['height'] ?? '';
        $scanType = strtolower($attributes['scanType'] ?? '');
        preg_match('/\b\d{3,4}[pi]\b/i', $attributes['displayTitle'] ?? '', $displayResolution);
        $resolution = $displayResolution[0] ?? ($height ? $height . ($scanType === 'interlaced' ? 'i' : 'p') : ($attributes['videoResolution'] ?? $fallbackResolution));
        $codec = $attributes['codec'] ?? $attributes['videoCodec'] ?? $fallbackCodec;
        return trim($resolution . ' ' . strtoupper($codec));
    }

    function getAudioQualityLabel($attributes, $fallbackCodec = '', $fallbackChannels = '') {
        $codec = $attributes['codec'] ?? $attributes['audioCodec'] ?? $fallbackCodec;
        preg_match('/\b(?:[57]\.1|Stereo|Mono)\b/i', $attributes['displayTitle'] ?? '', $displayChannels);
        $channels = $displayChannels[0] ?? getAudioChannelLabel($attributes) ?: $fallbackChannels;
        return trim(strtoupper($codec) . ($channels ? ' ' . $channels : ''));
    }

    function getPlaybackQuality($mediaAttributes, $videoAttributes, $audioAttributes, $transcodeAttributes) {
        $sourceVideoAttributes = $videoAttributes;
        $sourceAudioAttributes = $audioAttributes;
        if (!empty($transcodeAttributes['sourceVideoCodec'])) {
            $sourceVideoAttributes['codec'] = $transcodeAttributes['sourceVideoCodec'];
        }
        if (!empty($transcodeAttributes['sourceAudioCodec'])) {
            $sourceAudioAttributes['codec'] = $transcodeAttributes['sourceAudioCodec'];
        }
        $sourceVideo = getVideoQualityLabel($sourceVideoAttributes, $mediaAttributes['videoResolution'] ?? '', $mediaAttributes['videoCodec'] ?? '');
        $sourceAudio = getAudioQualityLabel($sourceAudioAttributes, $mediaAttributes['audioCodec'] ?? '', getAudioChannelLabel($mediaAttributes));
        $videoChanged = ($transcodeAttributes['videoDecision'] ?? '') === 'transcode';
        $audioChanged = ($transcodeAttributes['audioDecision'] ?? '') === 'transcode';

        return [
            'videoSource' => $sourceVideo,
            'videoOutput' => $videoChanged ? getVideoQualityLabel($mediaAttributes, '', $transcodeAttributes['videoCodec'] ?? '') : $sourceVideo,
            'videoTranscoded' => $videoChanged,
            'audioSource' => $sourceAudio,
            'audioOutput' => $audioChanged ? getAudioQualityLabel($mediaAttributes, $transcodeAttributes['audioCodec'] ?? '', getAudioChannelLabel($mediaAttributes)) : $sourceAudio,
            'audioTranscoded' => $audioChanged
        ];
    }

    function getSubtitleState($subtitleAttributes, $transcodeAttributes) {
        if (empty($subtitleAttributes)) {
            return ['state' => 'off', 'label' => 'Off'];
        }
        $decision = strtolower($transcodeAttributes['subtitleDecision'] ?? $subtitleAttributes['decision'] ?? 'directplay');
        if (strpos($decision, 'burn') !== false) {
            return ['state' => 'burned', 'label' => 'Burned in'];
        }
        if ($decision === 'transcode') {
            return ['state' => 'converted', 'label' => 'Converted'];
        }
        return ['state' => 'direct', 'label' => 'Direct play'];
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
                                    $video['@attributes']['parentThumb'] ?? $video['@attributes']['grandparentThumb'] ?? $video['@attributes']['thumb'] ?? $artThumb
                                ),
                                [
                                'endSecondsFromNow' => $timing['endSecondsFromNow']
                                ],
                                getPlaybackTimingFields($timing),
                                ['streamInfo' => []]
                            );

                            $mergedStream['locationDisplay'] = getLocationDisplay($mergedStream['location'], $mergedStream['address']);
                            $videoAttributes = [];
                            $audioAttributes = [];
                            $subtitleAttributes = [];
                            foreach (normalizeXmlList($media['Part']['Stream'] ?? []) as $stream) {
                                if ($stream['@attributes']['streamType'] === '2') {
                                    $mergedStream['streamInfo']['audio'] = $stream;
                                    $mergedStream['streamInfo']['audio']['@attributes']['decision'] = $mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? 'direct play';
                                    $audioAttributes = $stream['@attributes'];
                                } else if ($stream['@attributes']['streamType'] === '1') {
                                    $mergedStream['streamInfo']['video'] = $stream;
                                    $mergedStream['streamInfo']['video']['@attributes']['decision'] = $mergedStream['streamInfo']['video']['@attributes']['decision'] ?? 'direct play';
                                    $videoAttributes = $stream['@attributes'];
                                } else if ($stream['@attributes']['streamType'] === '3' && ($stream['@attributes']['selected'] ?? '0') === '1') {
                                    $subtitleAttributes = $stream['@attributes'];
                                }
                            }

                            $transcodeAttributes = $video['TranscodeSession']['@attributes'] ?? [];
                            $mergedStream['streamDecision'] = formatStreamDecision($media['Part']['@attributes']['decision']);
                            $mergedStream['playbackQuality'] = getPlaybackQuality($media['@attributes'], $videoAttributes, $audioAttributes, $transcodeAttributes);
                            $mergedStream['subtitle'] = getSubtitleState($subtitleAttributes, $transcodeAttributes);
                            $mergedStream['liveContext'] = [
                                'channel' => $media['@attributes']['channelCallSign'] ?? '',
                                'network' => $media['@attributes']['network'] ?? '',
                                'programTitle' => $duration === null ? ($video['@attributes']['title'] ?? '') : ''
                            ];
                            if ($duration === null && $mergedStream['liveContext']['channel'] === '' && $mergedStream['liveContext']['network'] === '') {
                                $mergedStream['liveContext'] = array_merge(
                                    $mergedStream['liveContext'],
                                    getLiveTvChannelContext($streams, $cfg, $video['@attributes']['ratingKey'] ?? '')
                                );
                            }

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

    function normalizeMediaServerUrl($url) {
        $url = rtrim(trim((string)$url), '/');
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return '';
        }
        return $url;
    }

    function getConfiguredMediaServers($cfg) {
        $servers = [];
        $registryValue = $cfg['MEDIA_SERVERS'] ?? '';
        $registry = json_decode($registryValue, true);
        if (!is_array($registry) && $registryValue !== '') {
            $decodedRegistry = base64_decode($registryValue, true);
            $registry = $decodedRegistry === false ? null : json_decode($decodedRegistry, true);
        }
        $hasRegistry = is_array($registry) && isset($registry['servers']) && is_array($registry['servers']);
        if ($hasRegistry) {
            foreach ($registry['servers'] as $server) {
                $provider = strtolower($server['provider'] ?? '');
                $baseUrl = normalizeMediaServerUrl($server['baseUrl'] ?? '');
                if (in_array($provider, ['jellyfin', 'emby'], true) && $baseUrl !== '' && !empty($server['apiKey'])) {
                    $servers[] = [
                        'id' => preg_replace('/[^a-zA-Z0-9_-]/', '-', $server['id'] ?? ($provider . '-' . substr(sha1($baseUrl), 0, 10))),
                        'provider' => $provider,
                        'name' => trim($server['name'] ?? '') ?: ucfirst($provider),
                        'baseUrl' => $baseUrl,
                        'apiKey' => (string)$server['apiKey']
                    ];
                }
            }
        }

        // Preserve every existing Plex installation without requiring migration.
        if (!empty($cfg['TOKEN'])) {
            foreach (getConfiguredHosts($cfg) as $host) {
                $host = normalizeMediaServerUrl($host);
                if ($host !== '') {
                    $shortHost = parse_url($host, PHP_URL_HOST) ?: $host;
                    $servers[] = ['id' => 'plex-' . substr(sha1($host), 0, 10), 'provider' => 'plex', 'name' => getServerAlias($shortHost, $cfg) ?: $shortHost, 'baseUrl' => $host, 'apiKey' => $cfg['TOKEN']];
                }
            }
        }

        if (!$hasRegistry) {
            foreach (['JELLYFIN' => 'jellyfin', 'EMBY' => 'emby'] as $prefix => $provider) {
                $baseUrl = normalizeMediaServerUrl($cfg[$prefix . '_HOST'] ?? '');
                $apiKey = trim($cfg[$prefix . '_API_KEY'] ?? '');
                if ($baseUrl !== '' && $apiKey !== '') {
                    $servers[] = ['id' => $provider . '-' . substr(sha1($baseUrl), 0, 10), 'provider' => $provider, 'name' => trim($cfg[$prefix . '_NAME'] ?? '') ?: ucfirst($provider), 'baseUrl' => $baseUrl, 'apiKey' => $apiKey];
                }
            }
        }
        return $servers;
    }

    function getMediaServerById($cfg, $serverId) {
        foreach (getConfiguredMediaServers($cfg) as $server) {
            if (hash_equals($server['id'], (string)$serverId)) {
                return $server;
            }
        }
        return null;
    }

    function mediaServerRequest($server, $path, $timeout = 10) {
        $url = rtrim($server['baseUrl'], '/') . '/' . ltrim($path, '/');
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout, CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Emby-Token: ' . $server['apiKey']]]);
        $body = curl_exec($handle);
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: '';
        $error = curl_error($handle);
        $errorCode = curl_errno($handle);
        curl_close($handle);
        return ['body' => $body, 'statusCode' => $statusCode, 'contentType' => $contentType, 'url' => $url, 'error' => $error, 'errorCode' => $errorCode];
    }

    function testMediaServerConnection($server, $timeout = 7) {
        $provider = $server['provider'] ?? '';
        $baseUrl = normalizeMediaServerUrl($server['baseUrl'] ?? '');
        if (!in_array($provider, ['plex', 'jellyfin', 'emby'], true) || $baseUrl === '') {
            return ['success' => false, 'url' => $baseUrl, 'message' => 'A valid media-server URL is required.'];
        }

        if ($provider === 'plex') {
            $token = trim((string)($server['apiKey'] ?? ''));
            if ($token === '') {
                return ['success' => false, 'url' => $baseUrl, 'message' => 'A Plex account token is required.'];
            }
            $url = $baseUrl . '/identity?X-Plex-Token=' . rawurlencode($token);
            $handle = curl_init($url);
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
            $body = curl_exec($handle);
            $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            $success = $body !== false && $statusCode >= 200 && $statusCode < 300 && parseXml($body) !== false;
            return ['success' => $success, 'url' => $baseUrl, 'statusCode' => $statusCode, 'message' => $success ? 'Reachable from this Unraid server.' : 'Not reachable from this Unraid server.' . ($error !== '' ? ' ' . $error : '')];
        }

        $response = mediaServerRequest($server, '/System/Info/Public', $timeout);
        $info = $response['statusCode'] >= 200 && $response['statusCode'] < 300 ? json_decode($response['body'], true) : null;
        $success = is_array($info);
        return ['success' => $success, 'url' => $baseUrl, 'statusCode' => $response['statusCode'], 'message' => $success ? 'Reachable from this Unraid server.' : 'Not reachable from this Unraid server.' . ($response['error'] !== '' ? ' ' . $response['error'] : '')];
    }

    function getPlexHttpsFallbackUrl($url) {
        $url = normalizeMediaServerUrl($url);
        return stripos($url, 'http://') === 0 ? 'https://' . substr($url, 7) : '';
    }

    function mediaServerPost($server, $path) {
        $handle = curl_init(rtrim($server['baseUrl'], '/') . '/' . ltrim($path, '/'));
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['X-Emby-Token: ' . $server['apiKey']]]);
        $body = curl_exec($handle);
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        return ['success' => $body !== false && $statusCode >= 200 && $statusCode < 300, 'statusCode' => $statusCode, 'error' => $error];
    }

    function mediaServerImageUrl($server, $itemId, $kind) {
        if ($itemId === '') return '';
        return '/plugins/plexstreams/getImage.php?server=' . rawurlencode($server['id']) . '&item=' . rawurlencode($itemId) . '&type=' . rawurlencode($kind);
    }

    function ticksToMilliseconds($ticks) { return $ticks === null || $ticks === '' ? null : (int)round(((float)$ticks) / 10000); }

    function mapEmbyLikeSession($server, $session, $display) {
        $item = $session['NowPlayingItem'] ?? null;
        if (!is_array($item) || empty($item['Id'])) return null;
        $playState = $session['PlayState'] ?? [];
        $transcoding = $session['TranscodingInfo'] ?? [];
        $mediaStreams = $item['MediaStreams'] ?? [];
        $duration = ticksToMilliseconds($item['RunTimeTicks'] ?? null);
        $timing = formatPlaybackTiming($duration, ticksToMilliseconds($playState['PositionTicks'] ?? 0) ?? 0, $display, false, '');
        $type = strtolower($item['Type'] ?? '') === 'audio' ? 'audio' : 'video';
        $playMethod = strtolower($playState['PlayMethod'] ?? '');
        $isTranscoding = !empty($transcoding) || $playMethod === 'transcode';
        $decision = $isTranscoding ? 'Transcode' : ($playMethod === 'directstream' ? 'Direct Stream' : 'Direct Play');
        $video = []; $audio = []; $subtitle = [];
        foreach ($mediaStreams as $mediaStream) {
            if (($mediaStream['Type'] ?? '') === 'Video') $video = $mediaStream;
            if (($mediaStream['Type'] ?? '') === 'Audio') $audio = $mediaStream;
            if (($mediaStream['Type'] ?? '') === 'Subtitle' && (($mediaStream['IsExternal'] ?? false) === false)) $subtitle = $mediaStream;
        }
        $title = $item['Name'] ?? 'Unknown';
        if (!empty($item['SeriesName'])) $title = $item['SeriesName'] . ' - ' . $title;
        $state = !empty($playState['IsPaused']) ? 'paused' : 'playing';
        $remote = $session['RemoteEndPoint'] ?? '';
        $location = $remote !== '' ? 'WAN' : 'LAN';
        $itemId = (string)$item['Id'];
        return array_merge([
            'id' => $server['id'] . '-' . ($session['Id'] ?? sha1($itemId . ($session['DeviceId'] ?? ''))),
            'provider' => $server['provider'], 'serverId' => $server['id'], 'serverHost' => $server['baseUrl'], '@host' => $server['baseUrl'],
            'alias' => $server['name'], 'type' => $type, 'title' => $title, 'titleString' => $title, 'key' => $itemId,
            'artUrl' => mediaServerImageUrl($server, $item['BackdropItemId'] ?? $itemId, 'Backdrop'),
            'thumbUrl' => mediaServerImageUrl($server, $item['ThumbItemId'] ?? $itemId, 'Primary'),
            'user' => $session['UserName'] ?? 'Unknown', 'userIsUnknown' => empty($session['UserName']), 'userAvatar' => '',
            'state' => $state, 'stateIcon' => getStateIcon($state), 'duration' => $duration, 'length' => $duration,
            'location' => $location, 'address' => $remote, 'locationDisplay' => $remote !== '' ? getLocationDisplay($location, $remote) : $location,
            'bandwidth' => round((float)($transcoding['Bitrate'] ?? $item['Bitrate'] ?? 0) / 1000000, 1),
            'streamDecision' => $decision, 'streamInfo' => ['audio' => ['@attributes' => ['decision' => $isTranscoding && !($transcoding['IsAudioDirect'] ?? false) ? 'transcode' : 'direct play']]],
            'playbackQuality' => ['videoSource' => getVideoQualityLabel($video), 'videoOutput' => $isTranscoding ? getVideoQualityLabel($transcoding, '', $transcoding['VideoCodec'] ?? '') : getVideoQualityLabel($video), 'videoTranscoded' => $isTranscoding, 'audioSource' => getAudioQualityLabel($audio), 'audioOutput' => getAudioQualityLabel($transcoding, $transcoding['AudioCodec'] ?? '') ?: getAudioQualityLabel($audio), 'audioTranscoded' => $isTranscoding && !($transcoding['IsAudioDirect'] ?? false)],
            'subtitle' => empty($subtitle) ? ['state' => 'off', 'label' => 'Off'] : ['state' => 'direct', 'label' => 'Direct play'],
            'client' => ['product' => $session['Client'] ?? ucfirst($server['provider']), 'name' => $session['DeviceName'] ?? '', 'platform' => $session['DeviceType'] ?? '', 'device' => $session['DeviceName'] ?? ''],
            'connection' => ['location' => strtolower($location), 'relayed' => false],
            'mediaIdentity' => ['seriesTitle' => $item['SeriesName'] ?? '', 'season' => $item['ParentIndexNumber'] ?? null, 'episode' => $item['IndexNumber'] ?? null, 'episodeTitle' => $item['Name'] ?? ''],
            'liveContext' => ['channel' => $item['ChannelName'] ?? '', 'network' => '', 'programTitle' => !empty($item['IsLive']) ? $title : ''],
            'sessionId' => $session['Id'] ?? null, 'clientIdentifier' => $session['DeviceId'] ?? '', 'canTerminate' => !empty($session['SupportsRemoteControl'])
        ], getPlaybackTimingFields($timing));
    }

    function getEmbyLikeStreams($server, $display, $cfg) {
        $response = mediaServerRequest($server, '/Sessions');
        if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
            debugLog($cfg, ucfirst($server['provider']) . ' session request failed', ['server' => $server['id'], 'statusCode' => $response['statusCode'], 'errorCode' => $response['errorCode'], 'error' => $response['error']]);
            return [];
        }
        $sessions = json_decode($response['body'], true);
        if (!is_array($sessions)) return [];
        return array_values(array_filter(array_map(function ($session) use ($server, $display) { return mapEmbyLikeSession($server, $session, $display); }, $sessions)));
    }

    function getAllMergedStreams($cfg) {
        global $display;
        $streams = getMergedStreams($cfg);
        $configuredServers = getConfiguredMediaServers($cfg);
        $providers = [];
        foreach ($configuredServers as $server) {
            $providers[] = ['id' => $server['id'], 'provider' => $server['provider'], 'baseUrl' => $server['baseUrl']];
            if ($server['provider'] !== 'plex') {
                $streams = array_merge($streams, getEmbyLikeStreams($server, $display ?? ['time' => '%R', 'date' => '%c'], $cfg));
            }
        }
        debugLog($cfg, 'Retrieved streams across configured media servers', ['servers' => $providers, 'streamCount' => count($streams)]);
        return $streams;
    }

?>
