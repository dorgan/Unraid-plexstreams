<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    if (empty($cfg['TOKEN']) || empty(getConfiguredHosts($cfg))) {
        http_response_code(500);
        echo(json_encode([]));
        exit;
    }

    echo(json_encode(getServerDetails($cfg)));