<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    if (empty(getConfiguredMediaServers($cfg))) {
        http_response_code(500);
        echo(json_encode([]));
        exit;
    }

    $details = !empty($cfg['TOKEN']) && !empty(getConfiguredHosts($cfg)) ? getServerDetails($cfg) : [];
    foreach (getConfiguredMediaServers($cfg) as $server) {
        if ($server['provider'] !== 'plex') {
            $details[] = ['host' => $server['baseUrl'], 'remoteAccess' => 'unknown', 'libraries' => [], 'activeLiveTv' => 0, 'tuners' => 0];
        }
    }
    echo(json_encode($details));
