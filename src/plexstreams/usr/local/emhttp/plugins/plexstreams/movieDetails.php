
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
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    if (isset($_GET['serverIdx'])) {
        $server = new MediaServer($cfg['servers'][$_GET['serverIdx']]);
        $details = $server->getMediaDetails($_GET['details']);

        if ($details !== false) {
            $videoAttr = $details['videoAttr'];
            $directors = $details['directors'];
            $genres = $details['genres'];

            echo('
                <h1>' . $details['title'] .'</h1>
                <p>' . $videoAttr['summary'] . '</p><p>
                <strong>Year:</strong> ' .$videoAttr['year'] . '<br/>
            ');
    
            if (isset($videoAttr['studio'])) {
                echo('<strong>Studio:</strong> ' . $videoAttr['studio'] . '<br/>');
            }
            if (count($directors) > 0) {
                echo('<strong>Director:</strong> ' .implode(' / ', $directors) .'<br/>');
            }
            if (count($genres) > 0) {
                echo('<strong>Genre:</strong> ' . implode(' / ', $genres) . '<br/>');
            }
            echo('<strong>Rating:</strong> ' .$videoAttr['contentRating'] . '</p>');
    
            echo('<p>');
            if (isset($video['Role'])) {
                echo('<h2>Cast</h2>');
                foreach($video['Role'] as $role) {
                    echo($role['@attributes']['tag'] . ' as ' . $role['@attributes']['role'] . '<br/>');
                }
                echo('</p>');
            }
        }
    }

    function v_d($obj) {
        echo('<pre>');
        var_dump($obj);
        echo('</pre>');
    }
