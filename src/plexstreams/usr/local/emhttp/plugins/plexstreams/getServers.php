<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-type: application/json');

    if (!empty($cfg['TOKEN'])) {
        $cfg['FORCE_PLEX_HTTPS'] = $_GET['useSsl'];
        echo(json_encode((Object)array('serverList' => getServers($cfg))));
    } else {
        http_response_code(500);
    }
