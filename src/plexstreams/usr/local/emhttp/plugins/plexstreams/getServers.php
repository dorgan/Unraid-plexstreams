<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-type: application/json');

    $token = $_POST['token'] ?? $cfg['TOKEN'] ?? '';
    $useSsl = $_POST['useSsl'] ?? '0';

    if (empty($token)) {
        http_response_code(400);
        echo(json_encode(array('error' => 'A Plex token is required before servers can be discovered.')));
        exit;
    }

    if ($useSsl !== '0' && $useSsl !== '1') {
        http_response_code(400);
        echo(json_encode(array('error' => 'Invalid SSL preference.')));
        exit;
    }

    $cfg['TOKEN'] = $token;
    $cfg['FORCE_PLEX_HTTPS'] = $useSsl;
    $serverList = getServers($cfg);

    if ($serverList === false) {
        debugLog($cfg, 'Plex server discovery failed');
        http_response_code(502);
        echo(json_encode(array('error' => 'Unable to reach Plex server discovery. Check that Unraid can access plex.tv.')));
        exit;
    }

    echo(json_encode((Object)array('serverList' => $serverList)));
