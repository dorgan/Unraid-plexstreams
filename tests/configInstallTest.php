<?php
// Exercise install/boot behavior in a temporary tree, never the server's config.
$repo = dirname(__DIR__);
$pluginDir = $repo . '/src/plexstreams/usr/local/emhttp/plugins/plexstreams';
$temp = sys_get_temp_dir() . '/plexstreams-config-' . bin2hex(random_bytes(8));
mkdir($temp);

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

try {
    $manifest = simplexml_load_file($repo . '/plexstreams.plg', 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);
    check($manifest !== false, 'Plugin manifest must be valid XML');
    $initialConfig = null;
    $postInstall = null;
    foreach ($manifest->FILE as $file) {
        if ((string) $file['Name'] === '/boot/config/plugins/plexstreams/plexstreams.cfg') {
            $initialConfig = trim((string) $file->INLINE) . PHP_EOL;
        }
        if ((string) $file['Run'] === '/bin/bash' && !isset($file['Method'])) {
            $postInstall = (string) $file->INLINE;
        }
    }
    check($initialConfig !== null, 'Manifest must create the initial configuration');
    check($postInstall !== null, 'Manifest must have a post-install script');
    $initialValues = parse_ini_string($initialConfig);
    check($initialValues === ['HOST' => '', 'TOKEN' => '', 'DISPLAY_NAV' => '1', 'DISPLAY_WIDGET' => '1'], 'Fresh install must produce valid INI with visibility defaults');

    // Substitute only filesystem locations so the real config reader and page
    // conditions run unchanged against missing, old, and user-saved configs.
    $configReader = str_replace('"/boot/config/plugins/"', var_export($temp . '/', true), file_get_contents($pluginDir . '/includes/config.php'));
    mkdir($temp . '/plexstreams');
    file_put_contents($temp . '/config.php', $configReader);
    $cfgPath = $temp . '/plexstreams/plexstreams.cfg';
    $versionPath = $temp . '/unraid-version';
    $fixtures = [
        'missing' => [null, '1', '1'],
        'fresh install' => [$initialConfig, '1', '1'],
        'empty' => ['', '1', '1'],
        'old installer' => ['HOST="" TOKEN=""', '1', '1'],
        'malformed' => ['TOKEN="unterminated', '1', '1'],
        'partial config' => ["TOKEN=\"fixture-token\"\n", '1', '1'],
        'reported settings' => ["DISPLAY_NAV=\"0\"\nDISPLAY_WIDGET=\"1\"\nTOKEN=\"fixture-token\"\n", '0', '1'],
        'widget disabled' => ["DISPLAY_NAV=\"1\"\nDISPLAY_WIDGET=\"0\"\n", '1', '0'],
        'both disabled' => ["DISPLAY_NAV=\"0\"\nDISPLAY_WIDGET=\"0\"\n", '0', '0'],
    ];
    foreach ($fixtures as $name => [$contents, $nav, $widget]) {
        if ($contents === null) {
            if (file_exists($cfgPath)) unlink($cfgPath);
        } else {
            file_put_contents($cfgPath, $contents);
        }
        include $temp . '/config.php';
        check($cfg['DISPLAY_NAV'] === $nav && $cfg['DISPLAY_WIDGET'] === $widget, "$name: config visibility defaults");
        check(isset($cfg['HOST'], $cfg['TOKEN'], $cfg['MEDIA_SERVERS'], $cfg['NOTIFICATION_TIMEOUT']), "$name: settings defaults must be available");
        if (strpos($contents ?? '', 'fixture-token') !== false) {
            check($cfg['TOKEN'] === 'fixture-token', "$name: saved token must be preserved");
        }
        check($contents === null ? !file_exists($cfgPath) : file_get_contents($cfgPath) === $contents, "$name: config reads must not alter saved data");

        foreach (['6.11.5', '6.12.0-beta5', '6.12.0-rc2', '7.3.3', '7.4.0-rc1'] as $version) {
            file_put_contents($versionPath, 'version="' . $version . '"');
            foreach (['Plex_Streams', 'PlexStreams_dashboard', 'NewDashboard'] as $pageName) {
                $header = explode("\n---\n", file_get_contents($pluginDir . '/' . $pageName . '.page'), 2)[0];
                $page = parse_ini_string($header);
                check(isset($page['Cond']), "$pageName: header must have a valid Cond expression");
                $condition = str_replace(['/boot/config/plugins/plexstreams/plexstreams.cfg', '/etc/unraid-version'], [$cfgPath, $versionPath], $page['Cond']);
                $enabled = eval('return ' . $condition . ';');
                $expected = $pageName === 'Plex_Streams' ? $nav === '1' : $widget === '1' && version_compare($version, '6.12.0-beta5', $pageName === 'NewDashboard' ? '>' : '<=');
                check($enabled === $expected, "$name / $version / $pageName: incorrect visibility");
            }
        }
    }

    // Simulate boot after older releases renamed pages, including a package
    // upgrade that has installed a new .page beside a stale .page.off.
    $pageDir = $temp . '/pages';
    mkdir($pageDir);
    file_put_contents($pageDir . '/PlexStreams_dashboard.page.off', 'legacy widget');
    file_put_contents($pageDir . '/NewDashboard.page', 'new widget');
    file_put_contents($pageDir . '/NewDashboard.page.off', 'stale widget');
    file_put_contents($pageDir . '/Plex_Streams.page.off', 'navigation');
    $script = str_replace('/usr/local/emhttp/plugins/plexstreams', $pageDir, $postInstall);
    file_put_contents($temp . '/post-install.sh', $script);
    for ($run = 0; $run < 2; $run++) {
        $output = [];
        exec('/bin/bash ' . escapeshellarg($temp . '/post-install.sh') . ' 2>&1', $output, $status);
        check($status === 0, 'Post-install script must succeed');
        check(strpos(implode("\n", $output), 'has been installed.') !== false, 'Post-install confirmation must render');
        check(count(array_filter($output, function ($line) {
            return preg_match('/error|warning|No such file|not found/i', $line);
        })) === 0, 'Post-install script must not print startup errors');
        foreach (['PlexStreams_dashboard' => 'legacy widget', 'NewDashboard' => 'new widget', 'Plex_Streams' => 'navigation'] as $pageName => $expected) {
            check(file_get_contents($pageDir . '/' . $pageName . '.page') === $expected, "$pageName: restore pages without overwriting newly installed content");
            check(!file_exists($pageDir . '/' . $pageName . '.page.off'), "$pageName: stale disabled page must be removed");
        }
    }
    echo "Configuration, page visibility, and install/boot regression checks passed.\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temp);
    restore_error_handler();
}
