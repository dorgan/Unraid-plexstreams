<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    if (empty($cfg['TOKEN'])) {
        http_response_code(500);
        echo(json_encode([]));
        exit;
    }

    $hosts = getConfiguredHosts($cfg);
    if (empty($hosts)) {
        http_response_code(500);
        echo(json_encode([]));
        exit;
    }

    echo(json_encode(getServerSummaries($cfg)));