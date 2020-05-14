<?php
    $plugin = "plexstreams";
    $plg_path = "/boot/config/plugins/" . $plugin;
    $cfg_file    = "$plg_path/" . $plugin . ".cfg";
    if (file_exists($cfg_file)) {
        $cfg    = parse_ini_file($cfg_file);
    } else {
        $cfg = array();
    }
    if (isset($cfg['HOST'])) {
        $host =  (substr($cfg['HOST'], -1) !== '/' ? $cfg['HOST'] : substr($cfg['HOST'],0,-1));
        $url = $host . $_GET['img'];
        
        # Check if the client already has the requested item
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) or isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }

        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 12800);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $out = curl_exec ($ch);
        curl_close ($ch);

        $file_array = explode("\r\n\r\n", $out, 2);
        $header_array = explode("\r\n", $file_array[0]);
        $headers = array();
        foreach($header_array as $header_value) {
            $header_pieces = explode(': ', $header_value);
            if(count($header_pieces) == 2) {
                $headers[$header_pieces[0]] = trim($header_pieces[1]);
            }
        }
        if (array_key_exists('Location', $headers)) {
        $newurl = urlencode($headers['Location']);
        header("HTTP/1.1 301 Moved Permanently"); 
        header('Location: ' . $PROXY . $newurl);
        } else {
        if (array_key_exists('Content-Type', $headers)) {
            $ct = $headers['Content-Type'];
            if (preg_match('#image/png|image/.*icon|image/jpe?g|image/gif#', $ct) !== 1) {
                header('HTTP/1.1 404 Not Found');
                exit;
            }
            header('Content-Type: ' . $ct);
        }
        if (array_key_exists('Content-Length', $headers))
            header('Content-Length: ' . $headers['Content-Length']);
        if (array_key_exists('Expires', $headers))
            header('Expires: ' . $headers['Expires']);
        if (array_key_exists('Cache-Control', $headers))
            header('Cache-Control: ' . $headers['Cache-Control']);
        if (array_key_exists('Last-Modified', $headers))
            header('Last-Modified: ' . $headers['Last-Modified']);
        echo $file_array[1];
        }

    }
?>