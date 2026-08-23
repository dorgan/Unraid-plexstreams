<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');
    
    $host = $_GET['host'] ?? '';
    $imagePath = $_GET['img'] ?? '';
    $mediaServer = getMediaServerById($cfg, $_GET['server'] ?? '');
    if ($mediaServer !== null && $mediaServer['provider'] !== 'plex') {
        $itemId = trim($_GET['item'] ?? '');
        $imageType = trim($_GET['type'] ?? 'Primary');
        if ($itemId === '' || !in_array($imageType, ['Primary', 'Backdrop', 'Thumb'], true)) {
            http_response_code(400);
            exit;
        }
        $response = mediaServerRequest($mediaServer, '/Items/' . rawurlencode($itemId) . '/Images/' . rawurlencode($imageType), 10);
        if ($response['body'] === false || $response['statusCode'] < 200 || $response['statusCode'] >= 300 || preg_match('#^image/(?:png|jpe?g|gif|webp)#i', $response['contentType']) !== 1) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $response['contentType']);
        echo $response['body'];
        exit;
    }
    if (empty($cfg['TOKEN'])) {
        http_response_code(500);
        exit;
    }
    if (!isConfiguredPlexHost($host, $cfg)) {
        http_response_code(403);
        exit;
    }

    $url = buildPlexImageUrl($host, $imagePath, $cfg['TOKEN']);
    if ($url === false) {
        http_response_code(400);
        exit;
    }

    if (isset($_GET['dbg'])) {
        debugLog($cfg, 'Fetching Plex image', ['url' => $url]);
    }
    # Check if the client already has the requested item
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) or isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 12800);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $out = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($out === false || $statusCode < 200 || $statusCode >= 300) {
        http_response_code(502);
        exit;
    }
    if (preg_match('#image/png|image/.*icon|image/jpe?g|image/gif#', $contentType) !== 1) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $contentType);
    echo substr($out, $headerSize);
?>
