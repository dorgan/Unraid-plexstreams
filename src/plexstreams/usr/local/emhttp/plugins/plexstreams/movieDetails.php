
<style>
body {
    padding: 25px;
}

.roles {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
}

.role {
    width: 200px;
    height: 200px;
}

.role .avatar {
    backgorund-position: center;
    border-radius: 50%;
    overflow: hidden;
    height: 75px;
    width: 75px;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

</style>
<?php
    $plugin = "plexstreams";
    $plg_path = "/boot/config/plugins/" . $plugin;
    $cfg_file    = "$plg_path/" . $plugin . ".cfg";
    if (file_exists($cfg_file)) {
        $cfg    = parse_ini_file($cfg_file);
    } else {
        $cfg = array();
    }

    if (!empty($cfg['TOKEN']) && isset($_GET['details'])) {
        $host =  (substr($cfg['HOST'], -1) !== '/' ? $cfg['HOST'] : substr($cfg['HOST'],0,-1));
        $url = $host . urldecode($_GET['details']);
        $details = getUrl($url);
        $video = $details['Video'];
        $videoAttr = $video['@attributes'];
        $title = $videoAttr['title'];
        $directors = [];
        $genres = [];

        if (isset($video['Genre']['@attributes'])) {
            $video['Genre'] = [$video['Genre']];
        }
        if (isset($video['Director']['@attributes'])) {
            $video['Director'] = [$video['Director']];
        }
        foreach ($video['Genre'] as $genre) {
            array_push($genres, $genre['@attributes']['tag']);
        }
        $director = [];
        
        foreach($video['Director'] as $director) {
            array_push($directors, $director['@attributes']['tag']);
        }
        echo('
            <h1>' . $title .'</h1>
            <p>' . $videoAttr['summary'] . '</p><p>
            <strong>Year:</strong> ' .$videoAttr['year'] . '<br/>
            <strong>Studio:</strong> ' . $videoAttr['studio'] . '<br/>
            <strong>Director:</strong> ' .implode(' / ', $directors) .'<br/>
            <strong>Genre:</strong> ' . implode(' / ', $genres) . '<br/>
            <strong>Rating:</strong> ' .$videoAttr['contentRating'] . '</p>
        ');

        echo('<h2>Cast</h2>');
        //echo('<div class="roles">');
        echo('<p>');
        foreach($video['Role'] as $role) {
        echo($role['@attributes']['tag'] . ' as ' . $role['@attributes']['role'] . '<br/>');
        //     $imageUrl = str_replace('http:', 'https:', $role['@attributes']['thumb']);
        //     echo('
        //         <div class="role">
        //             <div class="avatar" style="background-image:url(' .$imageUrl .');"></div>
        //             <div>' .$role['@attributes']['Tag']  . '</div>
        //         </div>');
        }
        echo('</p>');
        //echo('</div>');
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

    function v_d($obj) {
        echo('<pre>');
        var_dump($obj);
        echo('</pre>');
    }