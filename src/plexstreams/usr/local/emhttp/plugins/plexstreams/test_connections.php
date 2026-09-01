<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    function connectionTestResponse($statusCode, $payload) {
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }

    function enablePlexHttpsDiscovery() {
        $configFile = '/boot/config/plugins/plexstreams/plexstreams.cfg';
        $contents = @file_get_contents($configFile);
        if ($contents === false) return false;
        $updated = preg_match('/^FORCE_PLEX_HTTPS\s*=.*$/m', $contents)
            ? preg_replace('/^FORCE_PLEX_HTTPS\s*=.*$/m', 'FORCE_PLEX_HTTPS="1"', $contents)
            : rtrim($contents) . "\nFORCE_PLEX_HTTPS=\"1\"\n";
        $temporaryFile = $configFile . '.tmp';
        return @file_put_contents($temporaryFile, $updated, LOCK_EX) !== false && @rename($temporaryFile, $configFile);
    }

    function testPlexConnectionWithRemoteFallback($server, $cfg) {
        $result = testMediaServerConnection($server);
        if ($result['success']) return $result;
        $httpsUrl = getPlexHttpsFallbackUrl($server['baseUrl']);
        if ($httpsUrl !== '') {
            $httpsResult = testMediaServerConnection(['provider' => 'plex', 'baseUrl' => $httpsUrl, 'apiKey' => $server['apiKey']]);
            if ($httpsResult['success']) {
                $httpsEnabled = enablePlexHttpsDiscovery();
                return ['success' => true, 'url' => $server['baseUrl'], 'httpsUrl' => $httpsUrl, 'httpsEnabled' => $httpsEnabled, 'message' => $server['baseUrl'] . ' is not currently reachable by this Unraid server, but ' . $httpsUrl . ' is reachable.' . ($httpsEnabled ? ' Plex HTTPS discovery has been enabled.' : ' HTTPS works, but Plex HTTPS discovery could not be saved.')];
            }
        }
        $discoveryCfg = $cfg;
        $discoveryCfg['TOKEN'] = $server['apiKey'];
        $serverList = getServers($discoveryCfg);
        $selectedIsLocal = false;
        $remoteUrls = [];
        if (is_array($serverList)) {
            foreach ($serverList as $discoveredServer) {
                foreach ($discoveredServer['Connections'] ?? [] as $connection) {
                    $url = normalizeMediaServerUrl($connection['uri'] ?? '');
                    if ($url === $server['baseUrl'] && (string)($connection['local'] ?? '0') === '1') $selectedIsLocal = true;
                    if ($url !== '' && (string)($connection['local'] ?? '0') !== '1') $remoteUrls[$url] = true;
                }
            }
        }
        if ($selectedIsLocal) {
            foreach (array_keys($remoteUrls) as $remoteUrl) {
                $remoteResult = testMediaServerConnection(['provider' => 'plex', 'baseUrl' => $remoteUrl, 'apiKey' => $server['apiKey']]);
                if ($remoteResult['success']) {
                    return ['success' => true, 'url' => $server['baseUrl'], 'remoteUrl' => $remoteUrl, 'message' => $server['baseUrl'] . ' is not currently reachable by this Unraid server, but remote server ' . $remoteUrl . ' is reachable.'];
                }
            }
        }
        return $result;
    }

    $mode = $_POST['mode'] ?? 'server';
    if ($mode === 'all') {
        $results = [];
        foreach (getConfiguredMediaServers($cfg) as $server) {
            $result = $server['provider'] === 'plex' ? testPlexConnectionWithRemoteFallback($server, $cfg) : testMediaServerConnection($server);
            $result['id'] = $server['id'];
            $result['name'] = $server['name'];
            $result['provider'] = $server['provider'];
            $results[] = $result;
        }
        connectionTestResponse(200, ['results' => $results]);
    }

    $server = json_decode($_POST['server'] ?? '', true);
    if (!is_array($server)) {
        connectionTestResponse(400, ['error' => 'Invalid server test request.']);
    }
    $server['provider'] = strtolower(trim((string)($server['provider'] ?? '')));
    $server['baseUrl'] = normalizeMediaServerUrl($server['baseUrl'] ?? '');
    $server['apiKey'] = (string)($server['apiKey'] ?? '');
    if ($server['baseUrl'] === '') {
        connectionTestResponse(400, ['error' => 'Enter a valid http(s) server URL first.']);
    }

    $result = $server['provider'] === 'plex' ? testPlexConnectionWithRemoteFallback($server, array_merge($cfg, ['FORCE_PLEX_HTTPS' => ($_POST['useSsl'] ?? '0') === '1' ? '1' : '0'])) : testMediaServerConnection($server);
    connectionTestResponse($result['success'] ? 200 : 422, $result);
