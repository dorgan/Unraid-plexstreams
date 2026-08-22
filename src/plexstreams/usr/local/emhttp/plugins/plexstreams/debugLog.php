<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    $events = [
        'oauth_started' => 'Started Plex OAuth token request',
        'oauth_pin_received' => 'Received Plex OAuth PIN',
        'oauth_pin_failed' => 'Failed to request Plex OAuth PIN',
        'oauth_token_received' => 'Received Plex OAuth token',
        'oauth_token_failed' => 'Failed to retrieve Plex OAuth token'
    ];
    $event = $_POST['event'] ?? '';

    if (!isset($events[$event])) {
        http_response_code(400);
        echo(json_encode(['error' => 'Unknown debug event.']));
        exit;
    }

    debugLog($cfg, $events[$event]);
    echo(json_encode(['ok' => true]));
?>