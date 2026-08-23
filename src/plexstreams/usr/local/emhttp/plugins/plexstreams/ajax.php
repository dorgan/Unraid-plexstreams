<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');
    
    header('Content-Type: application/json');
    global $display;

    $mergedStreams = [];
    if (!empty(getConfiguredMediaServers($cfg))) {
        if (!empty(getConfiguredMediaServers($cfg))) {
            $docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
            require_once "$docroot/webGui/include/Wrappers.php";
            extract(parse_plugin_cfg('dynamix',true));

            $mergedStreams = getAllMergedStreams($cfg);
            foreach ($mergedStreams as &$stream) {
                unset($stream['sessionId']);
            }
            unset($stream);
            echo(json_encode($mergedStreams));
        } else {
            http_response_code(500);
        }

    }
