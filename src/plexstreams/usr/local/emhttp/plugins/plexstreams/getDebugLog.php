<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');

    header('Content-Type: application/json');

    if (($cfg['DEBUG_LOGGING'] ?? '0') !== '1') {
        http_response_code(403);
        echo(json_encode(['error' => 'Debug logging is disabled.']));
        exit;
    }

    $logFile = '/boot/config/plugins/plexstreams/plexstreams.log';
    if (!file_exists($logFile)) {
        echo(json_encode(['log' => '']));
        exit;
    }

    $maxBytes = 256 * 1024;
    $size = filesize($logFile);
    $handle = fopen($logFile, 'r');
    if ($handle === false) {
        http_response_code(500);
        echo(json_encode(['error' => 'Unable to read the debug log.']));
        exit;
    }

    if ($size > $maxBytes) {
        fseek($handle, -$maxBytes, SEEK_END);
        fgets($handle);
    }

    $log = stream_get_contents($handle);
    fclose($handle);
    echo(json_encode(['log' => $log === false ? '' : $log]));
?>