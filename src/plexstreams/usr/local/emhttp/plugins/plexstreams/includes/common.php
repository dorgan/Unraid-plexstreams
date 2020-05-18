<?php
    function getStreams($host, $cfg) {
        $retArray = [];
        $url = $host . "/status/sessions?X-Plex-Token=" . $cfg['TOKEN'] .'&_m=' .mktime();
        if (isset($_REQUEST['dbg'])) {
            v_d($url);
        }
        return getUrl($url);
    }

    function v_d($obj) {
        echo('<pre>');
        var_dump($obj);
        echo('</pre>');
    }

    function getUrl($url) {
        $arrContextOptions=array(
            "http" => array(
                "method" => "GET",
                "header" => 
                    "Content-Type: application/xml; charset=utf-8;\r\n".
                    "Connection: close\r\n".
                    "Cache-Control: no-cache, no-store, must-revalidate, max-age=0\r\n".
                    "Pragma: no-cache\r\n",
                "ignore_errors" => true,
                "timeout" => (float)30.0
            ),
            "ssl"=>array(
                "allow_self_signed"=>true,
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            )
        );
        return json_decode(json_encode(simplexml_load_string(file_get_contents($url, false, stream_context_create($arrContextOptions)))), TRUE);
    }

?>