var serverList = [];

function uCWord(str) {
    return str.charAt(0).toUpperCase() + str.slice(1)
}

function updateFullStreamInfo() {
    $.ajax('/plugins/plexstreams/ajax.php').done(function(streams){
        if (streams.length > 0) {
            var currentDate = new Date();
            var lastUpdate = currentDate.getTime();
            streams.forEach(function(stream) {
                var node = $('#' + stream.id + '.stream-container')[0];
                console.log('Updating Stream ID:' + stream.id);
                $container = $(node);
                if ($container.length > 0) {
                    $status = $container.find('.status i');
                    $progressBar = $container.find('.progressBar');
                    $progressBar.css({
                        width: stream.percentPlayed + '%'
                    });
                    
                    $status.attr('class', 'fa fa-' + stream.stateIcon);
                    $status.attr('title', uCWord(stream.state));
                    var $details = $container.find('.details');
                    $details.find('.stream.value').html(stream.streamDecision);
                    $details.find('.bandwidth.value').html(stream.bandwidth);
                    $details.find('.audio.value').html(uCWord(stream.streamInfo.audio['@attributes'].decision));
                    $details.find('.video.value').html(uCWord(stream.streamInfo.video['@attributes'].decision));
                } else {
                    // var containerCount = $('.stream-container').length;
                    // var isNewRow = containerCount % 3 === 0;
                    // var $streamsContainer = $('#streams-container tbody');
                    // var $currentRow;
                    // if (isNewRow) {
                    //     $currentRow = $streamsContainer.append('<tr></tr>');
                    // } else {
                    //     $currentRow = $streamsContainer.find('tr').last();
                    // }
                    
                    // $container = $currentRow.append(newEntry);
                    // node = $currentRow[0];
                }
                if (stream.duration) {
                    $hours = $container.find('.currentPositionHours');
                    $minutes = $container.find('.currentPositionMinutes');
                    $seconds = $container.find('.currentPositionSeconds');
                }
                if (node.prevState && node.prevState !== stream.state) {
                    if (stream.duration) {
                        $hours.html(stream.currentPositionHours.toString().padStart(2, 0));
                        $minutes.html(stream.currentPositionMinutes.toString().padStart(2, 0));
                        $seconds.html(stream.currentPositionSeconds.toString().padStart(2, 0));
                        if (stream.state === 'playing') {
                            incrementTimer($hours, $minutes, $seconds);
                        }
                    }
                }
                if (stream.duration  && stream.state === 'playing' && !node.timer) {
                    node.timer = setInterval(incrementTimer, 1000, $hours, $minutes, $seconds);
                } else if(stream.state !== 'playing') {
                    if (node.timer) {
                        clearInterval(node.timer);
                        node.timer = undefined;
                    }
                    $hours.html(stream.currentPositionHours.toString().padStart(2, 0));
                    $minutes.html(stream.currentPositionMinutes.toString().padStart(2, 0));
                    $seconds.html(stream.currentPositionSeconds.toString().padStart(2, 0));
                }
                node.prevState = stream.state;
                $container.attr('updatedat', lastUpdate);
            });
            $('.stream-container[updatedat]').each(function() {
                if ($(this).is('[updatedat]')) {
                    if ($(this).attr('updatedat') !== lastUpdate.toString()) {
                        if (this.timer) {
                            clearInterval(this.timer)
                        };
                        $(this).remove();
                    }
                }
            })
        } else {
            $('#plexstreams_streams').html('<tr><td colspan="3" align="center"><i>There are currently no active streams</i></td></tr>');
        }
    }).fail(function(jqXHR) {
        if (jqXHR.status == '500') {
            $('#plexstreams_streams').html('<tr><td colspan="3" align="center"><i>Please make sure you have <a href="/Settings/PlexStreams">setup</a> the plugin first</i></td></tr>');
        }
    });
}

function incrementTimer($hours, $minutes, $seconds) {
    var seconds = parseInt($seconds.html(), 10);
    var minutes = parseInt($minutes.html(), 10);
    var hours = parseInt($hours.html());
    seconds += 1;
    if (seconds > 59) {
        seconds = 0;
        minutes += 1;
    }
    if (minutes > 59) {
        minutes = 0;
        hours += 1;
    }
    $seconds.html(seconds.toString().padStart(2, 0));
    $minutes.html(minutes.toString().padStart(2, 0));
    $hours.html(hours.toString().padStart(2, 0));
}

function updateServerList(dest) {
    var list = [];
    $.each($("input[name='hostbox']:checked"), function(){
        list.push($(this).val());
    });
    $('#' + dest).val(list.join(','));
}

function getServers(containerSelector, selected) {
    var url = '/plugins/plexstreams/getServers.php?useSsl=' + $('input[name="FORCE_PLEX_HTTPS"]:checked').val();
    var $host = $(containerSelector);
    $host.hide();
    $('.lds-dual-ring').show();
    selected = selected.split(',');
    $host.html('');
    $.get(url).done(function(data) {
        serverList = data.serverList;
        for (var id in serverList) {
            if (serverList.hasOwnProperty(id)) {
                var server = serverList[id];
                serverList[id].Connections.forEach(function(connection) {
                    if (connection !== null) {
                        $host.append('<input type="checkbox" onchange="updateServerList(\'HOST\')" name="hostbox" id="' + connection.uri + '" data-id="' + id + '"' + (selected.indexOf(connection.uri) > -1 ? ' checked="checked"' : '' ) + ' value="' + connection.uri + '"/> <label for="' + connection.uri + '"> ' + server.Name + ' (' +  connection.address + ':' + connection.port + ')' + (connection.local === '0' ? ' - Remote' : '') + '</label><br/>');
                    }
                });
            }
        }
        $host.show();
        $('.lds-dual-ring').hide();
    });
}

function setLocalStorage(key, value, path) {
    if (path !== false) {
        key = key + '_' + window.location.pathname;
    }
    localStorage.setItem(key, value);
}
function getLocalStorage(key, default_value, path) {
    if (path !== false) {
        key = key + '_' + window.location.pathname;
    }
    var value = localStorage.getItem(key);
    if (value !== null) {
        return value
    } else if (default_value !== undefined) {
        setLocalStorage(key, default_value, path);
        return default_value
    }
}

function PopupCenter(url, title, w, h) {
    // Fixes dual-screen position                         Most browsers      Firefox
    var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : window.screenX;
    var dualScreenTop = window.screenTop != undefined ? window.screenTop : window.screenY;

    var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
    var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

    var left = ((width / 2) - (w / 2)) + dualScreenLeft;
    var top = ((height / 2) - (h / 2)) + dualScreenTop;
    var newWindow = window.open(url, title, 'scrollbars=yes, width=' + w + ', height=' + h + ', top=' + top + ', left=' + left);

    // Puts focus on the newWindow
    if (window.focus) {
        newWindow.focus();
    }

    return newWindow;
}
var plex_oauth_loader = '<style>' +
        '.login-loader-container {' +
            'font-family: "Open Sans", Arial, sans-serif;' +
            'position: absolute;' +
            'top: 0;' +
            'right: 0;' +
            'bottom: 0;' +
            'left: 0;' +
        '}' +
        '.login-loader-message {' +
            'color: #282A2D;' +
            'text-align: center;' +
            'position: absolute;' +
            'left: 50%;' +
            'top: 25%;' +
            'transform: translate(-50%, -50%);' +
        '}' +
        '.login-loader {' +
            'border: 5px solid #ccc;' +
            '-webkit-animation: spin 1s linear infinite;' +
            'animation: spin 1s linear infinite;' +
            'border-top: 5px solid #282A2D;' +
            'border-radius: 50%;' +
            'width: 50px;' +
            'height: 50px;' +
            'position: relative;' +
            'left: calc(50% - 25px);' +
        '}' +
        '@keyframes spin {' +
            '0% { transform: rotate(0deg); }' +
            '100% { transform: rotate(360deg); }' +
        '}' +
    '</style>' +
    '<div class="login-loader-container">' +
        '<div class="login-loader-message">' +
            '<div class="login-loader"></div>' +
            '<br>' +
            'Redirecting to the Plex login page...' +
        '</div>' +
    '</div>';
var plex_oauth_window = null;
function closePlexOAuthWindow() {
    if (plex_oauth_window) {
        plex_oauth_window.close();
    }
}

function uuidv4() {
    return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, function(c) {
        var cryptoObj = window.crypto || window.msCrypto; // for IE 11
        return (c ^ cryptoObj.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
    });
}

function getPlexHeaders() {
    return {
        'Accept': 'application/json',
        'X-Plex-Product': 'Unraid Plex Streams Plugin',
        'X-Plex-Version': PLUGIN_VERSION,
        'X-Plex-Client-Identifier': getLocalStorage('UnraidPlexStreams_ClientID', uuidv4(), false),
        'X-Plex-Platform': 'unraid',
        'X-Plex-Platform-Version': OS_VERSION,
        'X-Plex-Model': 'Plex OAuth',
        'X-Plex-Device': OS_VERSION,
        'X-Plex-Device-Name': 'Unraid Plex Streams Plugin',
        'X-Plex-Device-Screen-Resolution': window.screen.width + 'x' + window.screen.height,
        'X-Plex-Language': 'en'
    };
}

getPlexOAuthPin = function () {
    var x_plex_headers = getPlexHeaders();
    var deferred = $.Deferred();

    $.ajax({
        url: 'https://plex.tv/api/v2/pins?strong=true',
        type: 'POST',
        headers: x_plex_headers,
        success: function(data) {
            deferred.resolve({pin: data.id, code: data.code});
        },
        error: function() {
            closePlexOAuthWindow();
            deferred.reject();
        }
    });
    return deferred;
};

var polling = null;

function encodeData(data) {
    return Object.keys(data).map(function(key) {
        return [key, data[key]].map(encodeURIComponent).join("=");
    }).join("&");
}

function PlexOAuth(success, error, pre) {
    if (typeof pre === "function") {
        pre()
    }
    closePlexOAuthWindow();
    plex_oauth_window = PopupCenter('', 'Plex-OAuth', 600, 700);
    $(plex_oauth_window.document.body).html(plex_oauth_loader);

    getPlexOAuthPin().then(function (data) {
        var x_plex_headers = getPlexHeaders();
        const pin = data.pin;
        const code = data.code;

        var oauth_params = {
            'clientID': x_plex_headers['X-Plex-Client-Identifier'],
            'context[device][product]': x_plex_headers['X-Plex-Product'],
            'context[device][version]': x_plex_headers['X-Plex-Version'],
            'context[device][platform]': x_plex_headers['X-Plex-Platform'],
            'context[device][platformVersion]': x_plex_headers['X-Plex-Platform-Version'],
            'context[device][device]': x_plex_headers['X-Plex-Device'],
            'context[device][deviceName]': x_plex_headers['X-Plex-Device-Name'],
            'context[device][model]': x_plex_headers['X-Plex-Model'],
            'context[device][screenResolution]': x_plex_headers['X-Plex-Device-Screen-Resolution'],
            'context[device][layout]': 'desktop',
            'code': code
        }

        plex_oauth_window.location = 'https://app.plex.tv/auth/#!?' + encodeData(oauth_params);
        polling = pin;

        (function poll() {
            $.ajax({
                url: 'https://plex.tv/api/v2/pins/' + pin,
                type: 'GET',
                headers: x_plex_headers,
                success: function (data) {
                    if (data.authToken){
                        closePlexOAuthWindow();
                        getServers('#hostcontainer', $('#HOST').val());
                        if (typeof success === "function") {
                            success(data.authToken)
                        }
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (textStatus !== "timeout") {
                        closePlexOAuthWindow();
                        if (typeof error === "function") {
                            error()
                        }
                    }
                },
                complete: function () {
                    if (!plex_oauth_window.closed && polling === pin){
                        setTimeout(function() {poll()}, 1000);
                    }
                },
                timeout: 10000
            });
        })();
    }, function () {
        closePlexOAuthWindow();
        if (typeof error === "function") {
            error()
        }
    });
}