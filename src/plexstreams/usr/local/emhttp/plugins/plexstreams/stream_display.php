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

    #streams-loading {
        align-items: center;
        display: flex;
        height: 160px;
        justify-content: center;
    }

    #streams-container {
        display: grid;
        gap: 28px;
    }

    .plexstreams-server-group {
        display: grid;
        gap: 12px;
    }

    .plexstreams-server-header {
        align-items: end;
        border-bottom: 1px solid rgba(255, 255, 255, 0.14);
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 0 2px 10px;
    }

    .plexstreams-server-identity {
        color: #a9b2b3;
        display: grid;
        font-size: 12px;
        gap: 4px;
        min-width: 0;
    }

    .plexstreams-server-identity strong {
        color: #f1f4f0;
        font-size: 18px;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .plexstreams-server-summary {
        color: #8dbb7f;
        font-size: 12px;
        font-weight: 700;
        text-align: right;
        white-space: nowrap;
    }

    #streams-container ul {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .stream-container {
        background: rgba(0, 0, 0, 0.28);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-sizing: border-box;
        flex: 1 1 640px;
        height: 440px;
        list-style: none;
        max-width: 820px;
        overflow: hidden;
        position: relative;
        width: min(100%, 820px);
    }

    .stream-subcontainer {
        height: 100%;
        width: 100%;
    }

    .stream {
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        height: 100%;
        position: relative;
    }

    .blur {
        background: rgba(0, 0, 0, 0.42);
        height: 100%;
    }

    .stream .poster {
        background: #1c1c1c center / cover no-repeat;
        border-radius: 3px;
        height: calc(100% - 110px);
        left: 20px;
        position: absolute;
        top: 18px;
        width: 180px;
    }

    .details {
        background: rgba(10, 14, 16, 0.38);
        border-left: 2px solid rgba(121, 184, 118, 0.72);
        box-sizing: border-box;
        color: #c4c4c4;
        left: 224px;
        padding: 14px 16px;
        position: absolute;
        right: 20px;
        top: 20px;
    }

    .details ul {
        display: grid;
        gap: 8px 14px;
        grid-template-columns: repeat(2, minmax(190px, 1fr));
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .details li {
        align-items: center;
        display: flex;
        font-size: 12px;
        gap: 8px;
        line-height: 18px;
        min-width: 0;
    }

    .details li:nth-child(odd) {
        grid-column: 1;
    }

    .details li:nth-child(even) {
        grid-column: 2;
    }

    .details li .label {
        color: #9d9d9d;
        min-width: 64px;
        text-align: left;
    }

    .details li .value {
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 3px;
        color: #e2e2e2;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 6px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }

    .details .stream.value,
    .details li:nth-child(4) .value,
    .details .video.value,
    .details .audio.value {
        border-color: #5b9458;
        color: #79b876;
    }

    .plexstreams-card-footer {
        background: linear-gradient(to top, rgba(8, 12, 14, 0.96), rgba(8, 12, 14, 0.86) 44%, rgba(8, 12, 14, 0.58) 76%, rgba(8, 12, 14, 0.16));
        bottom: 0;
        box-sizing: border-box;
        color: #fff;
        height: 82px;
        left: 0;
        position: absolute;
        right: 0;
        z-index: 1;
    }

    .plexstreams-card-footer .plexstreams-card-title {
        font-size: 19px;
        font-weight: 700;
        overflow: hidden;
        padding: 25px 115px 0 70px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .plexstreams-card-footer .stream-user {
        bottom: 12px;
        color: #a5a5a5;
        font-size: 12px;
        left: 70px;
        max-width: calc(100% - 200px);
        position: absolute;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .plexstreams-card-footer .plexstreams-card-title a {
        color: #fff;
        text-decoration: none;
    }

    .plexstreams-card-footer .plexstreams-card-title a:hover {
        text-decoration: underline;
    }

    .plexstreams-card-footer .progressBar {
        background: #8dbb7f;
        height: 4px;
        left: 0;
        top: 0;
    }

    .plexstreams-card-footer .position {
        color: #e1e1e1;
        font-size: 13px;
        font-weight: 500;
        position: absolute;
        right: 60px;
        top: 31px;
    }

    .plexstreams-card-footer .playback-total {
        color: #9d9d9d;
        font-size: 13px;
        font-weight: 400;
    }

    .plexstreams-card-footer .ends-at {
        color: #9d9d9d;
        font-size: 11px;
        position: absolute;
        right: 60px;
        top: 51px;
    }

    .stream-container .plexstreams-card-title .plexstreams-card-status {
        color: #79b876;
        font-size: 24px;
        position: absolute;
        right: 20px;
        top: 30px;
    }

    .userIcon {
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        border: 1px solid rgba(255, 255, 255, 0.26);
        border-radius: 3px;
        bottom: 16px;
        height: 44px;
        left: 14px;
        position: absolute;
        top: auto;
        width: 44px;
        z-index: 2;
    }

    @media (max-width: 760px) {
        .plexstreams-server-header {
            align-items: start;
            flex-direction: column;
            gap: 6px;
        }

        .plexstreams-server-summary {
            text-align: left;
            white-space: normal;
        }

        .details ul {
            grid-template-columns: 1fr;
        }

        .details li:nth-child(odd),
        .details li:nth-child(even) {
            grid-column: auto;
        }

        .details li {
            font-size: 11px;
        }
    }

    @media (max-width: 560px) {
        .stream-container {
            height: 430px;
        }

        .stream .poster {
            height: 180px;
            width: 120px;
        }

        .details {
            left: 156px;
        }

        .plexstreams-card-footer .position {
            display: none;
        }

        .plexstreams-card-footer .ends-at {
            display: none;
        }
    }

    #streams-loading .lds-dual-ring {
        left: auto;
        position: relative;
        top: auto;
    }

</style>
<script>
    $(function() {
        $('.content > div.title').remove();
    });

    function openBox(cmd,title,height,width,load,func,id) {
    // open shadowbox window (run in foreground)
    var run = cmd.split('?')[0].substr(-4)=='.php' ? cmd : '/logging.htm?cmd='+cmd+'&csrf_token=91E90CB5E22139F9';
    var options = {overlayOpacity: 0.90};
    Shadowbox.open({content:run, player:'iframe', title:title, height:Math.min(height,screen.availHeight), width:Math.min(width,screen.availWidth), options:options});
    }
</script>
<?php
    $plugin = 'plexstreams';
    $docroot = $docroot ?: $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
    $translations = file_exists("$docroot/webGui/include/Translations.php");
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    if ($translations) {
        // add translations
        $_SERVER['REQUEST_URI'] = 'plexstreams';
        require_once "$docroot/webGui/include/Translations.php";
    } else {
        // legacy support (without javascript)
        $noscript = true;
        require_once "$docroot/plugins/$plugin/includes/Legacy.php";
    }

    if (!empty($cfg['TOKEN'])) {
        echo('<div id="streams-root"><div id="streams-loading"><div class="lds-dual-ring"></div></div></div>');
    } else {
        echo('<div class="caution"><i class="fa fa-exclamation-triangle"></i><div class="text">' . _('Please provide server details under Settings -> Network Services -> Plex Streams or') . ' <a href="/Settings/PlexStreams">' . _('click here') .'</a></div></div>');
    }
?>
<script src="<?autov('/plugins/plexstreams/js/plex.js')?>"></script>
<script>
    var title = $('title').html();
    $('title').html(title.split('/')[0] + '/Plex Streams');
    startStreamPolling(updateFullStreamInfo);
</script>