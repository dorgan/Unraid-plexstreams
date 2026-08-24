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
        overflow: clip;
    }

    .plexstreams-server-header {
        align-items: end;
        border-bottom: 1px solid rgba(255, 255, 255, 0.14);
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 0 2px 10px;
    }

    .plexstreams-server-actions {
        display: flex;
        flex: 0 0 auto;
        gap: 8px;
    }

    .plexstreams-server-identity {
        color: #a9b2b3 !important;
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

    .plexstreams-server-streams-toggle {
        appearance: none;
        background: transparent !important;
        border: 1px solid transparent !important;
        border-radius: 4px;
        color: #a9b2b3;
        cursor: pointer;
        display: grid;
        flex: 0 0 28px;
        font-size: 13px;
        height: 28px;
        line-height: 1;
        margin: 0;
        padding: 0 !important;
        place-items: center;
        transition: background-color 160ms ease, color 160ms ease;
        width: 28px;
    }

    .plexstreams-server-streams-toggle:hover {
        background: rgba(121, 184, 118, 0.12) !important;
        color: #8dbb7f !important;
    }

    .plexstreams-server-streams-toggle:focus-visible {
        border-color: #8dbb7f !important;
        outline: 0;
    }

    .plexstreams-server-details-toggle {
        appearance: none;
        background: rgba(121, 184, 118, 0.08) !important;
        border: 1px solid rgba(121, 184, 118, 0.38) !important;
        border-radius: 4px;
        color: #a9b2b3;
        cursor: pointer;
        font-size: 11px;
        font-weight: 700;
        height: 28px;
        margin: 0;
        padding: 0 8px !important;
        transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
        white-space: nowrap;
    }

    .plexstreams-server-details-toggle:hover {
        background: rgba(121, 184, 118, 0.16) !important;
        border-color: #79b876 !important;
        color: #d6e4d1 !important;
    }

    .plexstreams-server-details-toggle:focus-visible {
        border-color: #8dbb7f !important;
        outline: 0;
    }

    .plexstreams-server-streams-toggle i {
        transition: transform 220ms ease;
    }

    .plexstreams-server-group--streams-collapsed .plexstreams-server-streams-toggle i {
        transform: rotate(180deg);
    }

    .plexstreams-server-details {
        display: grid;
        gap: 8px 12px;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transform: translateY(-5px);
        transition: max-height 280ms ease, opacity 180ms ease, transform 280ms ease, visibility 0s linear 280ms;
        visibility: hidden;
    }

    .plexstreams-server-group--expanded .plexstreams-server-details {
        max-height: 360px;
        opacity: 1;
        transform: translateY(0);
        transition-delay: 0s;
        visibility: visible;
    }

    .plexstreams-server-details.is-loading {
        align-items: center;
        color: #a9b2b3;
        display: flex;
        font-size: 12px;
        grid-template-columns: none;
        min-height: 36px;
    }

    .plexstreams-server-details-spinner {
        animation: plexstreams-server-details-spin 700ms linear infinite;
        border: 2px solid rgba(169, 178, 179, 0.3);
        border-right-color: #8dbb7f;
        border-radius: 50%;
        display: inline-block;
        flex: 0 0 13px;
        height: 13px;
        width: 13px;
    }

    @keyframes plexstreams-server-details-spin {
        to { transform: rotate(360deg); }
    }

    .plexstreams-server-detail {
        border-left: 2px solid rgba(121, 184, 118, 0.7);
        color: #a9b2b3;
        display: grid;
        font-size: 11px;
        gap: 3px;
        padding-left: 8px;
    }

    .plexstreams-server-detail strong {
        color: #f1f4f0;
        font-size: 14px;
    }

    #streams-container ul {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .plexstreams-server-streams-viewport {
        overflow: clip;
    }

    .plexstreams-server-streams {
        min-height: 0;
    }

    .plexstreams-server-group--streams-collapsed .plexstreams-server-streams-viewport {
        pointer-events: none;
    }

    .plexstreams-empty-state {
        align-items: center;
        color: #a9b2b3;
        display: flex;
        flex: 1 1 100%;
        font-size: 13px;
        font-style: italic;
        justify-content: center;
        min-height: 96px;
        text-align: center;
    }

    .plexstreams-empty-state p {
        margin: 0;
    }

    .stream-container {
        background: rgba(0, 0, 0, 0.28);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-left: 4px solid transparent;
        box-sizing: border-box;
        flex: 1 1 640px;
        height: 440px;
        list-style: none;
        max-width: 820px;
        overflow: hidden;
        position: relative;
        transition: border-color 420ms ease, opacity 420ms ease, transform 420ms cubic-bezier(0.2, 0, 0, 1);
        width: min(100%, 820px);
    }

    .stream-container.plexstreams-stream-entering {
        border-color: transparent;
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }

    .stream-container.plexstreams-stream-exiting {
        border-color: transparent;
        opacity: 0;
        pointer-events: none;
        transform: translateY(-8px) scale(0.98);
    }

    .stream-container.plexstreams-is-paused {
        border-left-color: #88969a;
    }

    .stream-container.plexstreams-is-remote {
        border-left-color: #4ba7ae;
    }

    .stream-container.plexstreams-is-relayed {
        border-left-color: #d17d87;
    }

    .stream-container.plexstreams-is-transcoding {
        border-left-color: #d89a52;
    }

    .stream-container.plexstreams-is-buffering {
        border-left-color: #e2b257;
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

    .plexstreams-card-context {
        color: #a5a5a5;
        font-size: 10px;
        left: 70px;
        max-width: calc(100% - 160px);
        overflow: hidden;
        position: absolute;
        text-overflow: ellipsis;
        top: 9px;
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

    .plexstreams-unknown-user {
        font-style: italic;
    }

    .plexstreams-live-status {
        align-items: center;
        color: #8dbb7f;
        display: inline-flex;
        font-size: 11px;
        font-weight: 700;
        gap: 5px;
        text-transform: uppercase;
    }

    .plexstreams-live-status i {
        animation: plexstreams-live-pulse 1.6s ease-in-out infinite;
        background: #8dbb7f;
        border-radius: 50%;
        height: 6px;
        width: 6px;
    }

    .details .plexstreams-live-status {
        font-size: 10px;
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
        overflow: hidden;
        position: relative;
        top: 0;
    }

    .stream-container.plexstreams-is-playing.plexstreams-has-playback-progress .progressBar::after {
        animation: plexstreams-card-progress-sheen 2.4s linear infinite;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.55), transparent);
        content: '';
        inset: 0;
        position: absolute;
        transform: translateX(-100%);
        width: 45%;
    }

    .stream-container.plexstreams-is-live.plexstreams-is-playing .progressBar {
        animation: plexstreams-card-live-sheen 4.8s linear infinite;
        background: linear-gradient(90deg, rgba(121, 184, 118, 0.18), rgba(141, 187, 127, 0.72), rgba(121, 184, 118, 0.18));
        background-size: 200% 100%;
        width: 100% !important;
    }

    .plexstreams-card-footer .position {
        color: #e1e1e1;
        font-size: 13px;
        font-weight: 500;
        position: absolute;
        right: 60px;
        top: 31px;
    }

    .plexstreams-card-actions {
        opacity: 0;
        pointer-events: none;
        position: absolute;
        right: 14px;
        top: 14px;
        transform: translateY(-5px);
        transition: opacity 180ms ease, transform 180ms ease;
        z-index: 4;
    }

    .stream-container:hover .plexstreams-card-actions,
    .stream-container:focus-within .plexstreams-card-actions,
    .plexstreams-card-actions:focus-within,
    .stream-container.plexstreams-card-action-focused .plexstreams-card-actions,
    .stream-container.plexstreams-stream-stopping .plexstreams-card-actions,
    .stream-container.plexstreams-stop-failed .plexstreams-card-actions {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }

    .plexstreams-stop-stream {
        align-items: center;
        appearance: none;
        background: rgba(111, 35, 45, 0.94) !important;
        border: 1px solid rgba(239, 154, 165, 0.78) !important;
        border-radius: 4px;
        color: #fff3f4;
        cursor: pointer;
        display: inline-flex;
        font-size: 12px;
        font-weight: 700;
        gap: 6px;
        height: 32px;
        margin: 0;
        padding: 0 10px !important;
        transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
    }

    .plexstreams-stop-stream:hover {
        background: #8f3d49 !important;
        border-color: #ffd0d5 !important;
        transform: translateY(-1px);
    }

    .plexstreams-stop-stream:focus-visible {
        border-color: #ef9aa5 !important;
        outline: 0;
    }

    .plexstreams-stop-stream:disabled {
        cursor: wait;
        opacity: 0.7;
    }

    .plexstreams-stop-status {
        background: rgba(20, 9, 11, 0.9);
        border: 1px solid rgba(239, 154, 165, 0.46);
        border-radius: 3px;
        color: #f1c2c8;
        font-size: 10px;
        margin-top: 6px;
        max-width: 220px;
        overflow: hidden;
        padding: 4px 6px;
        text-align: right;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .plexstreams-stop-status:empty {
        display: none;
    }

    @media (hover: none) {
        .plexstreams-card-actions {
            opacity: 1;
            pointer-events: auto;
            transform: none;
        }
    }

    .stream-container.plexstreams-stream-stopping {
        opacity: 0.58;
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

    .userIcon.plexstreams-user-initial {
        align-items: center;
        background: linear-gradient(135deg, #556c9b, #31435f);
        color: #fff;
        display: flex;
        font-size: 20px;
        font-weight: 700;
        justify-content: center;
        line-height: 1;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.42);
    }

    @keyframes plexstreams-card-progress-sheen {
        to {
            transform: translateX(325%);
        }
    }

    @keyframes plexstreams-card-live-sheen {
        from {
            background-position: 200% 0;
        }
        to {
            background-position: -200% 0;
        }
    }

    @keyframes plexstreams-live-pulse {
        from {
            opacity: 0.45;
            transform: scale(0.75);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @media (max-width: 760px) {
        .plexstreams-server-header {
            align-items: center;
            display: grid;
            gap: 6px 12px;
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .plexstreams-server-identity {
            grid-column: 1;
            grid-row: 1;
        }

        .plexstreams-server-summary {
            grid-column: 1 / -1;
            grid-row: 2;
            text-align: left;
            white-space: normal;
        }

        .plexstreams-server-actions {
            grid-column: 2;
            grid-row: 1;
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
