<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-type: application/json');

    $mergedStreams = [];

    if (!empty($cfg['TOKEN'])) {
        if ($cfg['HOST'] !== '') {
            $host =  (substr($cfg['HOST'], -1) !== '/' ? $cfg['HOST'] : substr($cfg['HOST'],0,-1));
            if (isset($_REQUEST['host'])) {
                $host = $_REQUEST['host'];
            }

            $streams = getStreams($host, $cfg);
            
            $mergedStreams = mergeStreams($streams);
            if (isset($_REQUEST['dbg'])) {
                v_d($mergedStreams);
            }
            echo(json_encode($mergedStreams));
        } else {
            http_response_code(500);
        }

    }
