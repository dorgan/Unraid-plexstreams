<?php
    $plugin = "plexstreams";
    $plg_path = "/boot/config/plugins/" . $plugin;
    $cfg_file    = "$plg_path/" . $plugin . ".cfg";

    global $serverTypes;

    $serverTypes = ['Plex'];

    if (file_exists($cfg_file)) {
        $cfg  = parse_ini_file($cfg_file);
    } else {
        $cfg = array();
    }
?>