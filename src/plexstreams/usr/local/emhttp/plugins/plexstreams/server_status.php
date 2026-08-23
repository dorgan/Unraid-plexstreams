<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    if (empty(getConfiguredMediaServers($cfg))) {
        http_response_code(500);
        echo(json_encode([]));
        exit;
    }

    $summaries = getServerSummaries($cfg);
    foreach (getConfiguredMediaServers($cfg) as $server) {
        if ($server['provider'] === 'plex') continue;
        $response = mediaServerRequest($server, '/System/Info/Public', 5);
        $info = $response['statusCode'] >= 200 && $response['statusCode'] < 300 ? json_decode($response['body'], true) : [];
        $summaries[] = ['host' => $server['baseUrl'], 'alias' => $server['name'], 'name' => $server['name'], 'provider' => $server['provider'], 'online' => !empty($info), 'version' => $info['Version'] ?? '', 'claimed' => null, 'liveTv' => false, 'tuners' => false];
    }
    echo(json_encode($summaries));
