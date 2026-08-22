<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    header('Content-Type: application/json');

    function terminateStreamResponse($statusCode, $data) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        terminateStreamResponse(405, ['error' => 'Method not allowed.']);
    }

    $host = rtrim(trim($_POST['host'] ?? ''), '/');
    $streamId = trim($_POST['streamId'] ?? '');
    $clientIdentifier = trim($_POST['clientIdentifier'] ?? '');
    $reason = trim(preg_replace('/[\x00-\x1F\x7F]/', ' ', $_POST['reason'] ?? ''));
    $reason = substr($reason, 0, 250) ?: 'Stream stopped by server administrator.';

    if (empty($cfg['TOKEN']) || $host === '' || $streamId === '' || $clientIdentifier === '' || !isConfiguredPlexHost($host, $cfg)) {
        terminateStreamResponse(400, ['error' => 'Invalid stream termination request.']);
    }

    $stream = null;
    foreach (getMergedStreams($cfg) as $candidate) {
        if (rtrim($candidate['serverHost'] ?? '', '/') === $host && (string)($candidate['id'] ?? '') === $streamId && (string)($candidate['clientIdentifier'] ?? '') === $clientIdentifier) {
            $stream = $candidate;
            break;
        }
    }

    if (!$stream || empty($stream['sessionId'])) {
        terminateStreamResponse(404, ['error' => 'The stream is no longer active.']);
    }

    $result = terminatePlexSession($host, $stream['sessionId'], $reason, $cfg['TOKEN']);
    if (!$result['success']) {
        debugLog($cfg, 'Failed to terminate Plex stream', [
            'host' => $host,
            'statusCode' => $result['statusCode'],
            'error' => $result['error']
        ]);
        terminateStreamResponse(502, ['error' => 'Plex could not terminate this stream.']);
    }

    $sessionEnded = false;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        if ($attempt > 0) {
            usleep(500000);
        }

        $sessionEnded = true;
        foreach (getMergedStreams($cfg) as $candidate) {
            if (rtrim($candidate['serverHost'] ?? '', '/') === $host && (string)($candidate['sessionId'] ?? '') === (string)$stream['sessionId']) {
                $sessionEnded = false;
                break;
            }
        }

        if ($sessionEnded) {
            break;
        }
    }

    if (!$sessionEnded) {
        debugLog($cfg, 'Plex accepted termination request but stream remained active', ['host' => $host]);
        terminateStreamResponse(409, ['error' => 'Plex did not end this stream. Check Plex Pass and server permissions.']);
    }

    debugLog($cfg, 'Terminated Plex stream', ['host' => $host]);
    echo json_encode(['success' => true, 'verified' => true]);