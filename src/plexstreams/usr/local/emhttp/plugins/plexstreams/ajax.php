<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');
    $mergedStreams = [];

    if (!empty($cfg['TOKEN'])) {
        $host =  (substr($cfg['HOST'], -1) !== '/' ? $cfg['HOST'] : substr($cfg['HOST'],0,-1));
        if (isset($_REQUEST['host'])) {
            $host = $_REQUEST['host'];
        }

        $streams = getStreams($host, $cfg);
        $mergedStreams = mergeStreams($streams);
        header('Content-type: application/json');
        echo(json_encode($mergedStreams));
    }
