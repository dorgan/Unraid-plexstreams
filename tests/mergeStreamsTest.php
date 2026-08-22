<?php
require_once __DIR__ . '/../src/plexstreams/usr/local/emhttp/plugins/plexstreams/includes/common.php';

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function findStreamByType($streams, $type) {
    foreach ($streams as $stream) {
        if ($stream['type'] === $type) {
            return $stream;
        }
    }

    throw new RuntimeException('Missing ' . $type . ' stream.');
}

$display = ['time' => '%R', 'date' => '%c'];
$cfg = ['ALIAS-plex_test' => 'Test Plex'];
$allStreams = [
    'streams-0' => [
        'url' => 'http://plex.test:32400/status/sessions',
        'content' => [
            'Video' => [
                '@attributes' => [
                    'title' => 'Episode',
                    'year' => '2026',
                    'parentTitle' => 'Season 1',
                    'grandparentTitle' => 'Example Show',
                    'viewOffset' => '60000',
                    'key' => '/library/metadata/100',
                    'art' => '/library/metadata/100/art',
                    'thumb' => '/library/metadata/100/thumb'
                ],
                'Player' => ['@attributes' => ['product' => 'Plex Web', 'state' => 'playing', 'address' => '192.168.1.10']],
                'User' => ['@attributes' => ['title' => 'Test User', 'thumb' => 'https://plex.test/avatar']],
                'Session' => ['@attributes' => ['location' => 'lan', 'bandwidth' => '21300']],
                'TranscodeSession' => ['@attributes' => ['transcodeHwRequested' => '1', 'sourceAudioCodec' => 'dca', 'audioCodec' => 'aac']],
                'Media' => [
                    '@attributes' => ['selected' => '1', 'id' => 'video-media', 'videoResolution' => '1080'],
                    'Part' => [
                        '@attributes' => ['duration' => '120000', 'decision' => 'transcode'],
                        'Stream' => [
                            ['@attributes' => ['streamType' => '1', 'decision' => 'transcode', 'displayTitle' => '1080p']],
                            ['@attributes' => ['streamType' => '2', 'decision' => 'transcode']]
                        ]
                    ]
                ]
            ]
        ]
    ],
    'streams-1' => [
        'url' => 'http://plex.test:32400/status/sessions',
        'content' => [
            'Track' => [
                '@attributes' => [
                    'title' => 'Track',
                    'originalTitle' => 'Artist',
                    'parentTitle' => 'Album',
                    'viewOffset' => '30000',
                    'key' => '/library/metadata/200',
                    'art' => '/library/metadata/200/art',
                    'thumb' => '/library/metadata/200/thumb'
                ],
                'Player' => ['@attributes' => ['product' => 'Plexamp', 'state' => 'paused', 'address' => '192.168.1.11', 'local' => '1']],
                'User' => ['@attributes' => ['title' => 'Test User', 'thumb' => 'https://plex.test/avatar']],
                'Session' => ['@attributes' => ['location' => 'lan', 'bandwidth' => '320']],
                'Media' => [
                    '@attributes' => ['id' => 'audio-media'],
                    'Part' => [
                        '@attributes' => ['duration' => '240000', 'decision' => 'directplay'],
                        'Stream' => ['@attributes' => ['selected' => '1', 'decision' => 'direct play']]
                    ]
                ]
            ]
        ]
    ],
    'streams-2' => [
        'url' => 'http://plex.test:32400/status/sessions',
        'content' => [
            'Video' => [
                '@attributes' => [
                    'title' => 'Live Channel',
                    'viewOffset' => '0',
                    'key' => '/library/metadata/300',
                    'grandparentThumb' => 'https://metadata-static.plex.tv/channel.jpg'
                ],
                'Player' => ['@attributes' => ['product' => 'Plex Web', 'state' => 'playing', 'address' => '192.168.1.12']],
                'User' => ['@attributes' => ['title' => 'Test User', 'thumb' => 'https://plex.test/avatar']],
                'Session' => ['@attributes' => ['location' => 'lan', 'bandwidth' => '1800']],
                'Media' => [
                    '@attributes' => ['selected' => '1', 'id' => 'live-media'],
                    'Part' => [
                        '@attributes' => ['decision' => 'directplay'],
                        'Stream' => ['@attributes' => ['streamType' => '2', 'decision' => 'direct play']]
                    ]
                ]
            ]
        ]
    ]
];

$streams = mergeStreams($allStreams, $cfg);
$video = findStreamByType($streams, 'video');
$audio = findStreamByType($streams, 'audio');

assertSameValue('http', getCurlFailureType(502, 0), 'HTTP failure type');
assertSameValue('timeout', getCurlFailureType(0, CURLE_OPERATION_TIMEDOUT), 'timeout failure type');
assertSameValue('dns', getCurlFailureType(0, CURLE_COULDNT_RESOLVE_HOST), 'DNS failure type');
assertSameValue('connection', getCurlFailureType(0, CURLE_COULDNT_CONNECT), 'connection failure type');
assertSameValue('tls', getCurlFailureType(0, CURLE_SSL_CONNECT_ERROR), 'TLS failure type');
assertSameValue('transport', getCurlFailureType(0, 0), 'generic transport failure type');
assertSameValue(['https://plex.example:32400'], getConfiguredHosts([
    'HOST' => ' https://plex.example:32400 ',
    'CUSTOM_SERVERS' => ''
]), 'configured hosts');
assertSameValue(true, isConfiguredPlexHost('https://plex.example:32400/', ['HOST' => 'https://plex.example:32400']), 'configured image host');
assertSameValue(false, isConfiguredPlexHost('https://external.example', ['HOST' => 'https://plex.example:32400']), 'external image host');
assertSameValue('https://plex.example:32400/library/metadata/1/thumb?X-Plex-Token=token', buildPlexImageUrl('https://plex.example:32400', '/library/metadata/1/thumb', 'token'), 'image URL');
assertSameValue(false, buildPlexImageUrl('https://plex.example:32400', 'https://external.example/image', 'token'), 'absolute image URL');
assertSameValue('/plugins/plexstreams/getImage.php?img=%2Flibrary%2Fmetadata%2F1%2Fthumb&host=https%3A%2F%2Fplex.example%3A32400', buildStreamImageUrl('https://plex.example:32400', '/library/metadata/1/thumb'), 'relative stream image URL');
assertSameValue('https://metadata-static.plex.tv/channel.jpg', buildStreamImageUrl('https://plex.example:32400', 'https://metadata-static.plex.tv/channel.jpg'), 'external channel image URL');
assertSameValue('Live Event', getVideoTitle(
    ['@attributes' => ['title' => 'Live Event', 'parentTitle' => 'Channel']],
    ['@attributes' => ['origin' => 'livetv']]
), 'live-origin video title');
assertSameValue(3, count($streams), 'stream count');
assertSameValue('Test Plex', $video['alias'], 'video alias');
assertSameValue('http://plex.test:32400', $video['serverHost'], 'video server host');
assertSameValue('Example Show - Season 1 - Episode (2026)', $video['title'], 'video title');
assertSameValue('transcode', $video['streamDecision'], 'video stream decision');
assertSameValue('playing', $video['state'], 'video state');
assertSameValue('LAN (192.168.1.10)', $video['locationDisplay'], 'video location');
assertSameValue('transcode (HW)', $video['streamInfo']['video']['@attributes']['decision'], 'video decision');
assertSameValue('Test Plex', $audio['alias'], 'audio alias');
assertSameValue('Track - Artist - Album', $audio['titleString'], 'audio title');
assertSameValue('Direct Play', $audio['streamDecision'], 'audio stream decision');
assertSameValue('pause', $audio['stateIcon'], 'audio state icon');
assertSameValue('LAN (192.168.1.11)', $audio['locationDisplay'], 'audio location');
assertSameValue('direct play', $audio['streamInfo']['audio']['@attributes']['decision'], 'audio decision');

$live = null;
foreach ($streams as $stream) {
    if ($stream['title'] === 'Live Channel') {
        $live = $stream;
        break;
    }
}
if ($live === null) {
    throw new RuntimeException('Missing Live TV stream.');
}
assertSameValue(null, $live['currentPositionHours'], 'live timing hours');
assertSameValue(null, $live['lengthDisplay'], 'live timing display');
assertSameValue('https://metadata-static.plex.tv/channel.jpg', $live['thumbUrl'], 'live channel thumbnail fallback');
assertSameValue('https://metadata-static.plex.tv/channel.jpg', $live['artUrl'], 'live channel background fallback');

echo "mergeStreams fixtures passed\n";