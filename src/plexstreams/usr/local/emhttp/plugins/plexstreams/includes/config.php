<?php
    $plugin = "plexstreams";
    $plg_path = "/boot/config/plugins/" . $plugin;
    $cfg_file    = "$plg_path/" . $plugin . ".cfg";

    if (file_exists($cfg_file)) {
        $cfg  = parse_ini_file($cfg_file);
    } else {
        $cfg = array();
    }

    $defaults = array(
        'HOST' => '',
        'TOKEN' => '',
        'CUSTOM_SERVERS' => '',
        'DISPLAY_NAV' => '1',
        'DISPLAY_WIDGET' => '1',
        'DASHBOARD_LAYOUT' => 'default',
        'FORCE_PLEX_HTTPS' => '0',
        'DEBUG_LOGGING' => '0',
        // These initial convenience entries feed the provider-neutral registry below.
        // MEDIA_SERVERS may additionally contain any number of configured instances.
        'JELLYFIN_NAME' => 'Jellyfin',
        'JELLYFIN_HOST' => '',
        'JELLYFIN_API_KEY' => '',
        'EMBY_NAME' => 'Emby',
        'EMBY_HOST' => '',
        'EMBY_API_KEY' => '',
        'MEDIA_SERVERS' => ''
    );

    $cfg = array_merge($defaults, $cfg);
?>
