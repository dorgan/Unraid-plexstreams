<link type="text/css" rel="stylesheet" href="/plugins/plexstreams/spinner.css">
<style>
    .caution {
        padding-left: 76px;
        margin: 16px -40px;
        padding: 16px 50px;
        background-color:  rgb(254, 239, 227);
        color: rgb(191, 54, 12);
        display: block;
        font-weight: bolder;
        font-size: 14px;
    }
    .caution i {
        font-size:15pt;
    }

    .caution .text {
        display: inline-block;
        vertical-align: 2px;
        padding-left: 7px;
    }

    #streams-container {
        
    }

    .stream-container {
        position: relative;
        width: 500px;
        background-color: #000;
    }

    .stream {
        background-position: center;
        background-repeat: no-repeat;
        background-size: contain;
    }

    .blur {
        backdrop-filter: blur(3px);
    }

    .stream .blur {
        width: 100%;
        height: 100%;
    }

    .stream .poster {
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        width: 150px;
        height: 225px;
        z-index: 997;
    }

    .stream-container .bottom-box {
        width: 100%;
        position:absolute;
        bottom: 0;
        background: rgb(70,67,67,0.55);
        color: #fff;
        font-weight: bolder;
        z-index: 998;
    }

    .stream-container .bottom-box .progressBar {
        height: 5px;
        background-color: #cc0000;
    }

    .stream-container .bottom-box .progressBar .position {
        position: absolute;
        right: 5px;
        top: 0;
        width: 100px;
        font-size:9px;
        color: #fff;
        text-align:right;
    }

    .stream-container .bottom-box .title {
        padding: 10px;
        z-index: 999;
    }

    .stream-container .bottom-box .title a {
        text-decoration: none;
        color: #fff;
    }

    .stream-container .bottom-box .title a:hover {
        text-decoration: none;
    }

    .stream-container .title .status {
        float:right;
        color: #fff;
    }

    .userIcon {
        border-radius: 50%;
        overflow: hidden;
        position: absolute;
        top: 5px;
        right: 5px;
        margin: 0;
        height: 75px;
        width: 75px;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
    }

    .details {
        opacity: 0;
        transition: visibility 0s, opacity 0.5s ease-out ;
        position: absolute;
        opacity: 0;
        left: 160px;
        top: 5px;
        background: rgb(34, 34, 34, 0.80);
        color: #fff;
        width: 244px;
        height: 175px;
        font-weight:bold;
    }

    .details:hover {
        opacity: 1;
    }

    .details ul {
        margin-top: 0;
        padding-left: 0;
        list-style: none;
        font-size:14px;
    }
    
    .details li {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: baseline;
        width:100%;
        margin-bottom:5px;
        box-sizing: border-box;
        color: #fff;
        font-size: 12px;
        line-height: 17px;
    }

    .details li div {
        color: #aaa;
        text-align: right;
        line-height: 14px;
    }
    
    .details li .label {
        color: #aaa;
        width:75px;
    }

    .details li .value {
        color: #fff;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
        flex-grow: 1;
        text-align: left;
        margin-left: 10px;
    }

    .sb-overlay {
        backdrop-filter: blur(7px);
    }

</style>
<script>
    function openBox(cmd,title,height,width,load,func,id) {
    // open shadowbox window (run in foreground)
    var run = cmd.split('?')[0].substr(-4)=='.php' ? cmd : '/logging.htm?cmd='+cmd+'&csrf_token=91E90CB5E22139F9';
    var options = {overlayOpacity: 0.90};
    Shadowbox.open({content:run, player:'iframe', title:title, height:Math.min(height,screen.availHeight), width:Math.min(width,screen.availWidth), options:options});
    }
</script>
<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');
    $mergedStreams = [];
    

    if (!empty($cfg['TOKEN'])) {
        $host =  (substr($cfg['HOST'], -1) !== '/' ? $cfg['HOST'] : substr($cfg['HOST'],0,-1));
        if (isset($_REQUEST['host'])) {
            $host = $_REQUEST['host'];
        }

        $streams = getStreams($host, $cfg);
        
        $mergedStreams = mergeStreams($streams);
        if (count($mergedStreams) > 0) {
            echo('<h4 style="margin-bottom:0px;">Hover the stream for details</h4>');
            echo('<table border="0" cellspacing="0" cellpadding="5" id="streams-container">');
            foreach($mergedStreams as $idx => $stream) {
                if ($idx%3 === 0 && $idx !== 0) {
                    echo( '</tr><tr>');
                } else if ($idx === 0) {
                    echo('<tr>');
                }
                $loc = strtoupper($stream['location']);
                $location = $loc . ' (' . $stream['address'] . ($loc !== 'LAN' ? ' - ' .getGeo($stream['address']) : '' ) . ')';
                echo('
                    <td>
                        <div class="stream-container">
                            <div class="stream" style="background-image:url(' . $stream['artUrl'] .');">
                                <div class="blur">
                                    <div class="details">
                                        <ul class="detail-list">
                                            <li><div class="label">Length</div><div class="value">' . $stream['lengthDisplay'] .'</div></li>
                                            <li><div class="label">Stream</div><div class="value">' . ucwords($stream['streamDecision']) .'</div></li>
                                            <li><div class="label">Location</div><div class="value" title="' . $location . '" style="pointer:default;">' .$location .'</div></li>
                                            <li><div class="label">Bandwidth</div><div class="value">' .$stream['bandwidth'] . ' Mbps</div></li>
                                            <li><div class="label">Audio</div><div class="value">' . ucwords($stream['streamInfo']['audio']['@attributes']['decision'] ?? $stream['streamInfo']['audio']['decision']) . '</div></li>
                ');
                if (isset($stream['streamInfo']['video'])) {
                    echo('                  <li><div class="label">Video</div><div class="value">' . ucwords($stream['streamInfo']['video']['@attributes']['decision'] ?? $stream['streamInfo']['video']['decision']) . '</div></li>');
                }

                echo('
                                        </ul>
                                    </div>
                                    <div class="poster" style="background-image:url(' .$stream['thumbUrl'] .');">
                                    </div>
                                    <div class="userIcon" title="' .$stream['user'] . '" style="background-image:url(' . $stream['userAvatar'] . ')">
                                    </div>
                                </div>
                            </div>
                            <div class="bottom-box">
                                <div class="progressBar" duration="' . $stream['duration'] .'" style="width:' . 
                                    (!is_null($stream['duration']) ? $stream['percentPlayed'] : '0') .
                                    '%"><div class="position">' . 
                                    (!is_null($stream['duration']) ?  $stream['currentPositionDisplay'] . ' / ' . $stream['lengthDisplay'] : '' ) .'</div></div>
                                <div class="title">' . ($stream['type'] === 'video' ? '<a href="#" onclick="openBox(\'/plugins/plexstreams/movieDetails.php?details=' . urlencode($stream['key']) . '\',\'Details\',600,900); return false;">' : '') . $stream['title'] . ($stream['type'] === 'video' ? '</a>' : '' ) . '<div class="status"><i class="fa fa-' .$stream['stateIcon']  . '" title="' .ucwords($stream['state']) .'"></i></div></div>
                            </div>
                        </div>
                    </td>
                ');

                if (isset($_REQUEST['dbg'])) {
                    
                    echo('
                        <table border="0" cellspacing="0" cellpadding="0">
                            <tr><td>Duration</td><td>' .$stream['duration'] .'</td></tr>
                            <tr><td>LengthInSeconds</td><td>' .$stream['lengthInSeconds'] .'</td></tr>    
                            <tr><td>LengthInMinutes</td><td>' .$stream['lengthInMinutes'] .'</td></tr>
                            <tr><td>lengthSec</td></td><td>' .$stream['lengthInSeconds'] .'</td></tr>
                            <tr><td>lengthHours</td></td><td>' .$stream['lengthHours'] .'</td></tr>
                            <tr><td>lengthMinutes</td></td><td>' .$stream['lengthMinutes'] .'</td></tr>
                            <tr><td>lengthSeconds</td></td><td>' .$stream['lengthSeconds'] .'</td></tr>
                            <tr><td>currentPosition</td></td><td>' .$stream['currentPosition'] .'</td></tr>
                            <tr><td>currentPositionInSeconds</td></td><td>' .$stream['currentPositionInSeconds'] .'</td></tr>
                            <tr><td>currentPositionInMinutes</td></td><td>' .$stream['currentPositionInMinutes'] .'</td></tr>
                            <tr><td>currentPositionHours</td></td><td>' .$stream['currentPositionHours'] .'</td></tr>
                            <tr><td>currentPositionMinutes</td></td><td>' .$stream['currentPositionMinutes'] .'</td></tr>
                            <tr><td>currentPositionSeconds</td></td><td>' .$stream['currentPositionSeconds'] .'</td></tr>                                    
                        </table>
                    </td>');
                }
            }
            if (isset($streams['Video'])) {
                echo('</tr>');
            }

            echo('</table>');
        } else {
            echo('<p align="center">There are currently no active streams</p>');
        }
    } else {
        echo('<div class="caution"><i class="fa fa-exclamation-triangle"></i><div class="text">Please provide server details under Settings -> Network Services -> Plex Streams or <a href="/Settings/PlexStreams">click here</a></div></div>');
    }
?>
<script src="/plugins/plexstreams/js/plex.js"></script>