<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    $mergedStreams = [];

    if (!empty($cfg['TOKEN'])) {
        if ($cfg['HOST'] !== '') {
            $streams = getStreams($cfg);
            $mergedStreams = mergeStreams($streams);
            
            if (isset($_REQUEST['dbg'])) {
                v_d($mergedStreams);
            }
            echo(json_encode($mergedStreams));
        } else {
            http_response_code(500);
        }

    }
