var serverList = [];
var streamPollingInterval = 5000;

function startStreamPolling(update) {
    function poll() {
        if (document.hidden) {
            setTimeout(poll, streamPollingInterval);
            return;
        }

        var request = update();
        if (request && typeof request.always === 'function') {
            request.always(function() {
                setTimeout(poll, streamPollingInterval);
            });
        } else {
            setTimeout(poll, streamPollingInterval);
        }
    }

    poll();
}

function renderHostStreamCounts(hostStreams) {
    var $container = $('#stream_count_container');
    $container.empty();

    Object.keys(hostStreams).forEach(function(host) {
        $container.append('<div><strong>' + host + ':</strong> ' + hostStreams[host] + ' ' + _('Active Stream(s)') + '</div>');
    });
}

function formatPlaybackTime(stream, includeEndTime) {
    if (!Number.isFinite(stream.currentPositionHours) || !Number.isFinite(stream.currentPositionMinutes) || !Number.isFinite(stream.currentPositionSeconds)) {
        return 'N/A';
    }

    var time = '<span class="currentPositionHours">' + stream.currentPositionHours.toString().padStart(2, 0) + '</span>:<span class="currentPositionMinutes">' + stream.currentPositionMinutes.toString().padStart(2, 0) + '</span>:<span class="currentPositionSeconds">' + stream.currentPositionSeconds.toString().padStart(2, 0) + '</span> / ' + (stream.lengthDisplay || 'N/A');
    return includeEndTime && stream.endTime ? time + ' <span class="plexstreams-modern-end-time">(<span class="endTime">' + stream.endTime + '</span>)</span>' : time;
}

function updateDashboardStreamsNew() {
    return $.ajax('/plugins/plexstreams/ajax.php').done(function(streams){
        $('#plexstreams_count').html(streams.length);
        $('#retrieving_streams').remove();
        if (streams.length > 0) {
            $('.no_streams').remove();
            var currentDate = new Date();
            var lastUpdate = currentDate.getTime();
            streams.forEach(function(stream) {
                var $container = $('#' + stream.id);
                if ($container.length === 0) {
                    var videoDetails = stream.streamInfo.video && stream.streamInfo.video['@attributes'];
                    var playbackQuality = stream.playbackQuality || {};
                    var quality = playbackQuality.videoOutput || (videoDetails ? videoDetails.height || videoDetails.displayTitle : '');
                    var qualityTitle = getQualityTransform(playbackQuality.videoSource, playbackQuality.videoOutput, playbackQuality.videoTranscoded);
                    var connection = getConnectionLabel(stream) + ' · ' + getClientLabel(stream);
                    var identity = stream.mediaIdentity || {};
                    var dashboardTitle = identity.seriesTitle || stream.title;
                    var episodeLabel = getEpisodeLabel(stream);
                    var playbackTime = formatPlaybackTime(stream, true);
                    var isLive = stream.type === 'video' && !stream.duration;
                    var playbackStatus = isLive ? '<span class="plexstreams-live-status" title="' + _('Live') + '"><i></i>' + _('Live') + '</span>' : '<span><i class="fa fa-clock-o"></i> ' + playbackTime + '</span>';
                    var user = stream.userIsUnknown ? '<em class="plexstreams-unknown-user">' + _('Unknown') + '</em>' : stream.user;
                    var progressClass = stream.type === 'video' && stream.duration ? ' plexstreams-has-playback-progress' : '';
                    var liveClass = isLive ? ' plexstreams-is-live' : '';
                    var playbackStateClass = stream.state === 'playing' ? ' plexstreams-is-playing' : '';
                    $container = $('<div class="plexstreams-modern-stream' + progressClass + liveClass + playbackStateClass + '" id="' + stream.id + '">' +
                        '<div class="plexstreams-modern-poster" style="background-image:url(' + stream.thumbUrl + ');"></div>' +
                        '<div class="plexstreams-modern-content">' +
                            '<div class="plexstreams-modern-title" title="' + stream.titleString + '">' + dashboardTitle + '</div>' +
                            '<div class="plexstreams-modern-episode">' + episodeLabel + '</div>' +
                            '<div class="plexstreams-modern-meta"><span>' + user + ' · ' + (stream.alias || stream.address) + '</span><span class="plexstreams-modern-badge decision">' + stream.streamDecision + '</span>' + (quality ? '<span class="plexstreams-modern-badge quality" title="' + qualityTitle + '">' + quality + '</span>' : '') + '<span class="plexstreams-modern-badge bandwidth">' + stream.bandwidth + ' Mbps</span></div>' +
                            '<div class="plexstreams-modern-progress"><span class="plexstreams-modern-location" title="' + connection + '">' + connection + '</span>' + playbackStatus + '</div>' +
                            '<div class="plexstreams-modern-progress-track"><div class="plexstreams-modern-progress-value"></div></div>' +
                        '</div>' +
                        '<div class="plexstreams-modern-state"><i class="fa fa-' + stream.stateIcon + '" title="' + stream.state + '"></i></div>' +
                    '</div').appendTo('#plexstreams_streams');
                    var node = $container[0];
                } else {
                    var node = $container[0];
                    $container.toggleClass('plexstreams-has-playback-progress', stream.type === 'video' && Boolean(stream.duration));
                    $container.toggleClass('plexstreams-is-live', stream.type === 'video' && !stream.duration);
                    $container.toggleClass('plexstreams-is-playing', stream.state === 'playing');
                    $container.find('.plexstreams-modern-state i').attr('class', 'fa fa-' + stream.stateIcon).attr('title', uCWord(stream.state));
                    $container.find('.plexstreams-modern-title').text((stream.mediaIdentity || {}).seriesTitle || stream.title).attr('title', stream.titleString);
                    $container.find('.plexstreams-modern-episode').text(getEpisodeLabel(stream));
                    $container.find('.plexstreams-modern-meta > span:first-child').html((stream.userIsUnknown ? '<em class="plexstreams-unknown-user">' + _('Unknown') + '</em>' : stream.user) + ' · ' + (stream.alias || stream.address));
                    $container.find('.quality').text((stream.playbackQuality || {}).videoOutput || '').attr('title', getQualityTransform((stream.playbackQuality || {}).videoSource, (stream.playbackQuality || {}).videoOutput, (stream.playbackQuality || {}).videoTranscoded));
                    $container.find('.bandwidth').text(stream.bandwidth + ' Mbps');
                    $container.find('.plexstreams-modern-location').text(getConnectionLabel(stream) + ' · ' + getClientLabel(stream)).attr('title', getConnectionLabel(stream) + ' · ' + getClientLabel(stream));
                }
                node = $container.get(0);
                if (!node) {
                    return;
                }
                $container.find('.plexstreams-modern-progress-value').css('width', stream.percentPlayed + '%');
                updateDuration(node, stream);
                $container.attr('updatedat', lastUpdate);
                node.prevState = stream.state;
            });
            var totalBandwidth = streams.reduce(function(total, stream) {
                return total + Number(stream.bandwidth || 0);
            }, 0);
            $('#plexstreams_summary').html('<span id="plexstreams_count">' + streams.length + '</span> ' + _('Active Stream(s)') + ' · ' + totalBandwidth.toFixed(1) + ' Mbps');
            $('#plexstreams_streams .plexstreams-modern-stream[updatedat]').each(function() {
                if ($(this).is('[updatedat]')) {
                    if ($(this).attr('updatedat') !== lastUpdate.toString()) {
                        if (this.timer) {
                            clearInterval(this.timer)
                        };
                        $(this).remove();
                    }
                }
            });
        } else {
            $('#plexstreams_summary').html('<span id="plexstreams_count">0</span> ' + _('Active Stream(s)'));
            $('#plexstreams_streams').html('<div class="no_streams"><span class="w100"><p style="text-align:center;font-style:italic;font-size:13px;">' + _('There are currently no active streams') + '</p></span></div>');
        }
    }).fail(function(jqXHR) {
        var message = jqXHR.status === 500 ? _('Please make sure you have') + ' <a href="/Settings/PlexStreams">' + _('setup') + '</a> ' + _('the plugin first') : _('Unable to retrieve stream information.');
        $('#plexstreams_streams').html('<span class="w100"><p style="text-align:center;font-style:italic;font-size:13px;">' + message + '</p></span>');
    });
}


function updateDashboardStreams() {
    return $.ajax('/plugins/plexstreams/ajax.php').done(function(streams){
        //$('#plexstreams_count').html(streams.length);
        $('#retrieving_streams').remove();
        if (streams.length > 0) {
            $('.no_streams').remove();
            var currentDate = new Date();
            var lastUpdate = currentDate.getTime();
            var hostStreams = {};
            streams.forEach(function(stream) {
                var $container = $('#' + stream.id);
                if (hostStreams[stream['alias']] === undefined) {
                    hostStreams[stream['alias']] = 1;
                } else {
                    hostStreams[stream['alias']] = hostStreams[stream['alias']] + 1;
                }
                if ($container.length === 0) {
                    $container = $('<tr style="display:table-row;" id="' + stream.id + '">' +
                        '<td width="40%" style="padding: 0px;"><p class="plexstream-title" title="' + stream.titleString + '">' + stream.title +  '</p></td>' +
                        '<td align="center" style="padding: 0px;text-align:center;"><i class="fa fa-' + stream.stateIcon + '" title="' + stream.state + '"></i></td>' +
                        '<td align="center" style="padding: 0px;"><p class="plexstream-user" title="' + stream.user + '">' + stream.user + '</td>' +
                        '<td align="center" style="padding: 0px;text-align:right;"><p class="plexstream-time">' + formatPlaybackTime(stream, false) + '</p></td>' +
                    '</tr>').appendTo('#plexstreams_streams');
                    var node = $container[0];
                } else {
                    var node = $container[0];
                    var $cells = $container.find('td');
                    $($cells[1]).find('i').attr('class', 'fa fa-' + stream.stateIcon).attr('title', uCWord(stream.state));
                }
                $container.attr('updatedat', lastUpdate);
                node.prevState = stream.state;
                updateDuration(node, stream);
            });
            renderHostStreamCounts(hostStreams);
            $('#plexstreams_streams tr[updatedat]').each(function() {
                if ($(this).is('[updatedat]')) {
                    if ($(this).attr('updatedat') !== lastUpdate.toString()) {
                        if (this.timer) {
                            clearInterval(this.timer)
                        };
                        $(this).remove();
                    }
                }
            });
        } else {
            $('#stream_count_container').html('<span id="plexstreams_count">0</span> ' + _('Active Stream(s)') + '</span>');
            $('#plexstreams_streams').html('<tr class="no_streams"><td colspan="4" align="center" style="padding: 0 0 0 0;"><p style="text-align:center;font-style:italic;">' + _('There are currently no active streams') + '</p></td></tr>');
        }
    }).fail(function(jqXHR) {
        if (jqXHR.status == '500') {
            $('#plexstreams_streams').html('<tr><td colspan="4" align="center"><p style="text-align:center;font-style:italic;">' + _('Please make sure you have') + ' <a href="/Settings/PlexStreams">' + _('setup') + '</a> ' + _('the plugin first') + '</p></td></tr>');
        }
    });
}

function uCWord(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function getConnectionLabel(stream) {
    var connection = stream.connection || {};
    if (String(connection.location || stream.location || '').toLowerCase() === 'lan') {
        return _('LAN');
    }
    return _('Remote') + ' · ' + (connection.relayed ? _('Relay') : _('Direct'));
}

function getClientLabel(stream) {
    var client = stream.client || {};
    var labels = [client.product || stream.player];
    var clientName = client.name || client.platform || client.device;
    if (clientName && clientName !== labels[0]) {
        labels.push(clientName);
    }
    return labels.filter(Boolean).join(' · ');
}

function getQualityTransform(source, output, transcoded) {
    if (!source) {
        return output || '';
    }
    var quality = output && output !== source ? source + ' -> ' + output : source;
    return transcoded ? quality + ' (' + _('Transcoded') + ')' : quality;
}

function getEpisodeLabel(stream) {
    var identity = stream.mediaIdentity || {};
    if (!identity.seriesTitle || identity.season === null || identity.season === undefined || identity.episode === null || identity.episode === undefined) {
        return '';
    }
    var season = String(identity.season).padStart(2, '0');
    var episode = String(identity.episode).padStart(2, '0');
    return 'S' + season + 'E' + episode + (identity.episodeTitle ? ' · ' + identity.episodeTitle : '');
}

function updateCardDetail($container, detailClass, label, value) {
    var $row = $container.find('.plexstreams-detail-' + detailClass);
    if ($row.length === 0) {
        $row = $('<li class="plexstreams-detail-' + detailClass + '"><div class="label"></div><div class="value"></div></li>').appendTo($container.find('.details ul'));
    }
    $row.find('.label').text(label);
    $row.find('.value').text(value || _('Unavailable'));
}

function updateStreamPresentation($container, stream) {
    if (stream.type !== 'video') {
        return;
    }
    var quality = stream.playbackQuality || {};
    var subtitle = stream.subtitle || { label: _('Off') };
    var liveContext = stream.liveContext || {};
    var $footer = $container.find('.plexstreams-card-footer');
    var $title = $footer.find('.plexstreams-card-title a');
    var $context = $footer.find('.plexstreams-card-context');
    var episodeLabel = getEpisodeLabel(stream);
    var liveLabel = [liveContext.channel, liveContext.network].filter(Boolean).join(' · ');

    updateCardDetail($container, 'video-quality', _('Video quality'), getQualityTransform(quality.videoSource, quality.videoOutput, quality.videoTranscoded));
    updateCardDetail($container, 'audio-quality', _('Audio quality'), getQualityTransform(quality.audioSource, quality.audioOutput, quality.audioTranscoded));
    updateCardDetail($container, 'subtitles', _('Subtitles'), subtitle.label || _('Off'));
    updateCardDetail($container, 'client', _('Client'), getClientLabel(stream));
    updateCardDetail($container, 'connection', _('Connection'), getConnectionLabel(stream));
    $container.find('.details .audio.value, .details .video.value').closest('li').remove();
    $container.find('.details .label').filter(function() {
        return $(this).text() === _('Location');
    }).closest('li').remove();

    if (!episodeLabel && !liveLabel) {
        $context.remove();
        return;
    }
    if ($context.length === 0) {
        $context = $('<div class="plexstreams-card-context"></div>').prependTo($footer);
    }
    if (episodeLabel) {
        $title.text(stream.mediaIdentity.seriesTitle);
        $context.text(episodeLabel);
    } else {
        $context.text(liveLabel);
    }
}

function getServerGroupId(host) {
    return 'plexstreams-server-' + String(host || 'unknown').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '');
}

var serverDetailsState = {};

function getServerActivitySummary(server, streams) {
    var counts = { directPlay: 0, directStream: 0, transcode: 0, hardwareTranscode: 0, liveTv: 0, remote: 0 };
    var bandwidth = 0;

    streams.forEach(function(stream) {
        var decision = String(stream.streamDecision || '').toLowerCase().replace(/\s/g, '');
        bandwidth += Number(stream.bandwidth || 0);
        if (decision === 'directplay') {
            counts.directPlay += 1;
        } else if (decision === 'directstream') {
            counts.directStream += 1;
        } else if (decision === 'transcode') {
            counts.transcode += 1;
            if (stream.streamInfo.video && String(stream.streamInfo.video['@attributes'].decision || '').indexOf('(HW)') !== -1) {
                counts.hardwareTranscode += 1;
            }
        }
        if (stream.type === 'video' && !Number.isFinite(stream.currentPositionHours)) {
            counts.liveTv += 1;
        }
        if (String(stream.location || '').toLowerCase() !== 'lan') {
            counts.remote += 1;
        }
    });

    var summary = [streams.length + ' ' + _('Active')];
    if (counts.directPlay) {
        summary.push(_('Direct Play') + ' ' + counts.directPlay);
    }
    if (counts.directStream) {
        summary.push(_('Direct Stream') + ' ' + counts.directStream);
    }
    if (counts.transcode) {
        summary.push(_('Transcode') + (counts.hardwareTranscode ? ' (HW)' : '') + ' ' + counts.transcode);
    }
    if (counts.liveTv) {
        summary.push(_('Live TV') + ' ' + counts.liveTv);
    }
    if (counts.remote) {
        summary.push(_('Remote') + ' ' + counts.remote);
    }
    summary.push(bandwidth.toFixed(1) + ' Mbps');
    return summary.join(' · ');
}

function renderServerDetails($group, details) {
    var libraries = details.libraries || {};
    var libraryFacts = [
        ['Movies', libraries.movie],
        ['Shows', libraries.show],
        ['Episodes', libraries.episode],
        ['Artists', libraries.artist],
        ['Albums', libraries.album]
    ].filter(function(fact) {
        return Number.isFinite(fact[1]) && fact[1] > 0;
    });
    var $details = $group.find('.plexstreams-server-details');
    var facts = [
        ['Remote access', details.remoteAccess === 'direct' ? 'Direct' : 'Unknown'],
        ['Active Live TV', details.activeLiveTv],
        ['Tuner capacity', details.tuners]
    ];
    libraryFacts.forEach(function(fact) {
        facts.splice(1, 0, fact);
    });
    $details.html(facts.map(function(fact) {
        return '<div class="plexstreams-server-detail"><span>' + _(fact[0]) + '</span><strong>' + fact[1] + '</strong></div>';
    }).join(''));
    $details.removeClass('is-loading').addClass('is-ready');
}

function refreshLiveTvServerDetails(streams) {
    Object.keys(serverDetailsState).forEach(function(host) {
        var state = serverDetailsState[host];
        if (!state.details) {
            return;
        }
        var activeLiveTv = streams.filter(function(stream) {
            return stream.serverHost === host && stream.type === 'video' && !stream.duration;
        }).length;
        if (state.details.activeLiveTv === activeLiveTv) {
            return;
        }
        state.details.activeLiveTv = activeLiveTv;
        if (state.expanded) {
            renderServerDetails($('#' + getServerGroupId(host)), state.details);
        }
    });
}

function renderServerDetailsLoading($details) {
    $details.addClass('is-loading').removeClass('is-ready').html('<span class="plexstreams-server-details-spinner" aria-hidden="true"></span><span>' + _('Loading server details...') + '</span>');
}

function loadServerDetails($group, host) {
    var $details = $group.find('.plexstreams-server-details');
    var state = serverDetailsState[host] || (serverDetailsState[host] = {});
    if (state.details) {
        renderServerDetails($group, state.details);
        return;
    }
    if (state.loading) {
        renderServerDetailsLoading($details);
        return;
    }
    state.loading = true;
    renderServerDetailsLoading($details);
    $.ajax('/plugins/plexstreams/server_details.php').done(function(servers) {
        var details = (servers || []).filter(function(server) {
            return server.host === host;
        })[0];
        state.loading = false;
        if (!details) {
            state.error = _('Server details are unavailable.');
            $('#' + getServerGroupId(host)).find('.plexstreams-server-details').removeClass('is-loading').addClass('is-ready').text(state.error);
            return;
        }
        state.details = details;
        renderServerDetails($('#' + getServerGroupId(host)), details);
    }).fail(function() {
        state.loading = false;
        state.error = _('Server details are unavailable.');
        $('#' + getServerGroupId(host)).find('.plexstreams-server-details').removeClass('is-loading').addClass('is-ready').text(state.error);
    });
}

function renderFullStreamServerGroups(serverStatuses, streams) {
    var $container = $('#streams-container');
    if ($container.length === 0) {
        $('#streams-root').html('<div id="streams-container"></div>');
        $container = $('#streams-container');
    }

    var servers = {};
    (serverStatuses || []).forEach(function(server) {
        servers[server.host] = server;
    });
    streams.forEach(function(stream) {
        if (!servers[stream.serverHost]) {
            servers[stream.serverHost] = {
                host: stream.serverHost,
                alias: stream.alias || stream.serverHost,
                name: stream.alias || stream.serverHost,
                online: true,
                version: '',
                claimed: false,
                liveTv: false,
                tuners: false
            };
        }
    });

    Object.keys(servers).forEach(function(host) {
        var server = servers[host];
        var groupId = getServerGroupId(host);
        var $group = $('#' + groupId);
        var detailState = serverDetailsState[host];
        if ($group.length === 0) {
            $group = $('<section class="plexstreams-server-group" id="' + groupId + '"><header class="plexstreams-server-header"><div class="plexstreams-server-identity"></div><div class="plexstreams-server-summary"></div><button class="plexstreams-server-details-toggle" type="button" title="' + _('Show server details') + '" aria-expanded="false"><i class="fa fa-chevron-down"></i></button></header><div class="plexstreams-server-details" aria-live="polite"></div><ul></ul></section>').appendTo($container);
            $group.find('.plexstreams-server-details-toggle').on('click', function() {
                var $toggle = $(this);
                var isExpanded = $toggle.attr('aria-expanded') === 'true';
                var willExpand = !isExpanded;
                var detailState = serverDetailsState[host] || (serverDetailsState[host] = {});
                detailState.expanded = willExpand;
                $toggle.attr('aria-expanded', String(willExpand)).attr('title', willExpand ? _('Hide server details') : _('Show server details'));
                $group.toggleClass('plexstreams-server-group--expanded', willExpand);
                if (willExpand) {
                    loadServerDetails($group, host);
                }
            });
        }
        if (detailState && detailState.expanded) {
            $group.addClass('plexstreams-server-group--expanded');
            $group.find('.plexstreams-server-details-toggle').attr('aria-expanded', 'true').attr('title', _('Hide server details'));
            if (detailState.details) {
                renderServerDetails($group, detailState.details);
            } else if (detailState.error) {
                $group.find('.plexstreams-server-details').removeClass('is-loading').addClass('is-ready').text(detailState.error);
            } else {
                loadServerDetails($group, host);
            }
        }

        var serverStreams = streams.filter(function(stream) {
            return stream.serverHost === host;
        });
        var state = server.online ? _('Online') : _('Unavailable');
        var facts = [state];
        if (server.version) {
            facts.push('PMS ' + server.version);
        }
        if (server.online && server.claimed !== null) {
            facts.push(server.claimed ? _('Claimed') : _('Unclaimed'));
        }
        if (server.liveTv) {
            facts.push(_('Live TV'));
        }
        $group.find('.plexstreams-server-identity').html('<strong>' + $('<div>').text(server.name).html() + '</strong><span>' + facts.join(' · ') + '</span>');
        $group.find('.plexstreams-server-summary').text(getServerActivitySummary(server, serverStreams));
    });
}

function getFullStreamHolder(host) {
    return $('#' + getServerGroupId(host) + ' > ul');
}

function renderServerEmptyStates() {
    $('.plexstreams-server-group > ul').each(function() {
        var $holder = $(this);
        $holder.find('.plexstreams-empty-state').remove();
        $holder.append('<li class="plexstreams-empty-state"><p>' + _('There are currently no active streams') + '</p></li>');
    });
}

function updateFullStreamInfo() {
    return $.when(
        $.ajax('/plugins/plexstreams/ajax.php'),
        $.ajax('/plugins/plexstreams/server_status.php')
    ).done(function(streamResponse, serverResponse){
        var streams = streamResponse[0];
        var serverStatuses = serverResponse[0] || [];
        if (streams.length > 0) {
            $('.plexstreams-empty-state').remove();
            var currentDate = new Date();
            var lastUpdate = currentDate.getTime();
            var streamPositions = getStreamLayoutSnapshot();
            renderFullStreamServerGroups(serverStatuses, streams);
            refreshLiveTvServerDetails(streams);
            $('#hover-message').hide();
            streams.forEach(function(stream) {
                var $streamHolder = getFullStreamHolder(stream.serverHost);
                var hasPlaybackTimeline = Number.isFinite(stream.currentPositionHours) && Number.isFinite(stream.currentPositionMinutes) && Number.isFinite(stream.currentPositionSeconds);
                if (!hasPlaybackTimeline) {
                    stream = $.extend({}, stream, {
                        hasPlaybackTimeline: false,
                        currentPositionHours: 0,
                        currentPositionMinutes: 0,
                        currentPositionSeconds: 0,
                        lengthDisplay: _('Live')
                    });
                } else {
                    stream.hasPlaybackTimeline = true;
                }
                var node = $('#' + stream.id + '.stream-container')[0];
                var $container = $(node);
                var isNewStream = $container.length === 0;
                if ($container.length > 0) {
                    if ($container.parent()[0] !== $streamHolder[0]) {
                        $container.appendTo($streamHolder);
                    }
                    var $status = $container.find('.plexstreams-card-status i, .status i');
                    var $progressBar = $container.find('.progressBar');
                    $progressBar.css({
                        width: stream.percentPlayed + '%'
                    });
                    
                    $status.attr('class', 'fa fa-' + stream.stateIcon);
                    $status.attr('title', uCWord(stream.state || stream.stateIcon));
                    var $details = $container.find('.details');
                    $details.find('.stream.value').html(uCWord(stream.streamDecision));
                    $details.find('.bandwidth.value').text(stream.bandwidth + ' Mbps');
                    $details.find('.audio.value').html(uCWord(stream.streamInfo.audio['@attributes'].decision));
                    if (stream.streamInfo.video) {
                        $details.find('.video.value').html(uCWord(stream.streamInfo.video['@attributes'].decision));
                    }
                } else {
                    $container = $('<li class="stream-container" id="' + stream.id + '"><div class="stream-subcontainer"><div class="stream" style="background-image:url(' + stream.artUrl  + ');"><div class="blur"><div class="details"><ul class="detail-list"><li><div class="label">' + _('Server') + '</div><div class="value">' + stream.alias + '</div></li><li><div class="label">' + _('Length') + '</div><div class="value">' + stream.duration + '</div></li><li><div class="label">' + _('Stream') + '</div><div class="stream value">' + stream.streamDecision + '</div></li><li><div class="label">' + _('Location') + '</div><div class="value" title="' + stream.locationDisplay + '" style="pointer:default;">' + stream.locationDisplay + '</div></li><li><div class="label">' + _('Bandwidth') + '</div><div class="bandwidth value">' + stream.bandwidth + '</div></li><li><div class="label">' + _('Audio') + '</div><div class="audio value">' + stream.streamInfo.audio['@attributes'].decision + '</div></li><li>' +  (stream.streamInfo.video ? '<div class="label">' + _('Video') + '</div><div class="video value">' + stream.streamInfo.video['@attributes'].decision + '</div></li>' : '') + '</ul></div><div class="poster" style="background-image:url(' + stream.thumbUrl + ');"></div><div class="userIcon" title="' + stream.user + '" style="background-image:url(' + stream.userAvatar + ')"></div></div></div><div class="bottom-box"><div class="progressBar" duration="' + stream.duration + '" style="' + stream.percentPlayed + '%;"><div class="position"><span class="currentPositionHours">' + stream.currentPositionHours.toString().padStart(2, 0) + '</span>:<span class="currentPositionMinutes">' + stream.currentPositionMinutes.toString().padStart(2, 0) + '</span>:<span class="currentPositionSeconds">' + stream.currentPositionSeconds.toString().padStart(2, 0) + '</span>  / ' + stream.lengthDisplay + '</div></div><div class="title"><a href="#" onclick="openBox(\'/plugins/plexstreams/movieDetails.php?details=' + encodeURIComponent(stream.key) + '&host=' + encodeURIComponent(stream['@host'])  + '\',\'Details\',600,900); return false;">' + stream.title +'</a><div class="status"><i class="fa fa-' + stream.stateIcon + '" title="' + stream.status + '"></i></div></div></div></div></li>').appendTo($streamHolder);
                    node = $container[0];
                }
                if (isNewStream) {
                    $container.addClass('plexstreams-stream-entering');
                    requestAnimationFrame(function() {
                        $container.removeClass('plexstreams-stream-entering');
                    });
                }
                $container.toggleClass('plexstreams-has-playback-progress', hasPlaybackTimeline);
                $container.toggleClass('plexstreams-is-live', !hasPlaybackTimeline);
                $container.toggleClass('plexstreams-is-playing', stream.state === 'playing');
                var $lengthValue = $container.find('.details .label').filter(function() {
                    return $(this).text() === _('Length');
                }).siblings('.value');
                if (hasPlaybackTimeline) {
                    $lengthValue.text(stream.lengthDisplay || 'N/A');
                } else {
                    $lengthValue.html('<span class="plexstreams-live-status"><i></i>' + _('Live') + '</span>');
                }
                $container.find('.stream.value').text(uCWord(stream.streamDecision));
                $container.find('.bandwidth.value').text(stream.bandwidth + ' Mbps');
                $container.find('.audio.value').text(uCWord(stream.streamInfo.audio['@attributes'].decision));
                $container.find('.video.value').text(stream.streamInfo.video ? uCWord(stream.streamInfo.video['@attributes'].decision) : '');
                var $artwork = $container.find('.stream-subcontainer > .stream');
                var $footer = $container.find('.bottom-box').first();
                if ($footer.length === 0) {
                    $footer = $artwork.children('.plexstreams-card-footer').first();
                }
                $footer.removeClass('bottom-box').addClass('plexstreams-card-footer').appendTo($artwork);
                $footer.find('.title').removeClass('title').addClass('plexstreams-card-title');
                $footer.find('.status').removeClass('status').addClass('plexstreams-card-status');
                updateStreamPresentation($container, stream);
                if ($container.find('.playback-status').length === 0) {
                    var playbackTime = stream.currentPositionHours !== null ? '<span class="playback-current"><span class="currentPositionHours">' + stream.currentPositionHours.toString().padStart(2, 0) + '</span>:<span class="currentPositionMinutes">' + stream.currentPositionMinutes.toString().padStart(2, 0) + '</span>:<span class="currentPositionSeconds">' + stream.currentPositionSeconds.toString().padStart(2, 0) + '</span></span><span class="playback-total"> / ' + stream.lengthDisplay + '</span>' : 'N/A';
                    $container.find('.position').remove();
                    $('<div class="playback-status"><div class="position">' + playbackTime + '</div><div class="ends-at">' + _('Ends') + ' <span class="endTime">' + (stream.endTime || '') + '</span></div></div>').appendTo($footer);
                }
                $container.find('.ends-at').toggle(hasPlaybackTimeline);
                if (!hasPlaybackTimeline) {
                    $container.find('.playback-status .position').html('<span class="plexstreams-live-status"><i></i>' + _('Live') + '</span>');
                }
                var $streamUser = $container.find('.stream-user');
                if ($streamUser.length === 0) {
                    $streamUser = $('<span class="stream-user"></span>').appendTo($footer);
                }
                $streamUser.text(stream.user);
                $streamUser.toggleClass('plexstreams-unknown-user', Boolean(stream.userIsUnknown));
                $container.find('.progressBar').css('width', stream.percentPlayed + '%');
                updateDuration(node, stream);
                $container.attr('updatedat', lastUpdate);
                node.prevState = stream.state;
            });

            requestAnimationFrame(function() {
                animateStreamLayoutChanges(streamPositions);
            });
            removeStaleStreamCards(lastUpdate);
        } else {
            $('#hover-message').hide();
            renderFullStreamServerGroups(serverStatuses, streams);
            refreshLiveTvServerDetails(streams);
            removeStaleStreamCards(null, renderServerEmptyStates);
        }
    }).fail(function(jqXHR) {
        var message = jqXHR.status === 500 ? _('Please make sure you have') + ' <a href="/Settings/PlexStreams">' + _('setup') + '</a> ' + _('the plugin first') : _('Unable to retrieve stream information.');
        $('#hover-message').hide();
        $('#streams-root').html('<div class="no_streams"><span class="w100"><p style="text-align:center;font-style:italic;font-size:13px;">' + message + '</p></div>');
    });
}

function getStreamLayoutSnapshot() {
    var positions = {};
    $('.stream-container').not('.plexstreams-stream-exiting').each(function() {
        positions[this.id] = this.getBoundingClientRect();
    });
    return positions;
}

function animateStreamLayoutChanges(previousPositions, duration) {
    $('.stream-container').not('.plexstreams-stream-exiting').each(function() {
        var previous = previousPositions[this.id];
        var current = this.getBoundingClientRect();
        if (!previous) {
            return;
        }
        var horizontalDistance = previous.left - current.left;
        var verticalDistance = previous.top - current.top;
        if (horizontalDistance || verticalDistance) {
            this.animate([
                { transform: 'translate(' + horizontalDistance + 'px, ' + verticalDistance + 'px)' },
                { transform: 'translate(0, 0)' }
            ], { duration: duration || 240, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' });
        }
    });
}

function removeStaleStreamCards(lastUpdate, onComplete) {
    var $staleCards = $('.stream-container[updatedat]').filter(function() {
        return (lastUpdate === null || $(this).attr('updatedat') !== lastUpdate.toString()) && !$(this).hasClass('plexstreams-stream-exiting');
    });
    if ($staleCards.length === 0) {
        if (onComplete) {
            onComplete();
        }
        return;
    }
    $staleCards.each(function() {
        if (this.timer) {
            clearInterval(this.timer);
        }
        $(this).addClass('plexstreams-stream-exiting');
    });
    Promise.all($staleCards.map(function(_, card) {
        return new Promise(function(resolve) {
            setTimeout(resolve, 440);
        });
    }).get()).then(function() {
        var positions = getStreamLayoutSnapshot();
        $staleCards.remove();
        requestAnimationFrame(function() {
            animateStreamLayoutChanges(positions, 600);
        });
        if (onComplete) {
            onComplete();
        }
    });
}

function animateNewStreamCard($container) {
    $container.addClass('plexstreams-stream-entering');
    requestAnimationFrame(function() {
        $container.removeClass('plexstreams-stream-entering');
    });
}

function updateDuration(node, stream) {
    if (!node) {
        return;
    }

    var $container = $(node);
    var hasPlaybackTimeline = stream.hasPlaybackTimeline !== undefined
        ? stream.hasPlaybackTimeline
        : Boolean(stream.duration && Number.isFinite(stream.currentPositionHours) && Number.isFinite(stream.currentPositionMinutes) && Number.isFinite(stream.currentPositionSeconds));
    if (!hasPlaybackTimeline) {
        if (node.timer) {
            clearInterval(node.timer);
            node.timer = undefined;
        }
        return;
    }

    var $hours = $container.find('.currentPositionHours');
    var $minutes = $container.find('.currentPositionMinutes');
    var $seconds = $container.find('.currentPositionSeconds');
    if ($hours.length === 0 || $minutes.length === 0 || $seconds.length === 0) {
        if (node.timer) {
            clearInterval(node.timer);
            node.timer = undefined;
        }
        return;
    }
    if (node.prevState && node.prevState !== stream.state) {
        $hours.html(stream.currentPositionHours.toString().padStart(2, 0));
        $minutes.html(stream.currentPositionMinutes.toString().padStart(2, 0));
        $seconds.html(stream.currentPositionSeconds.toString().padStart(2, 0));
        if (stream.state === 'playing') {
            incrementTimer($hours, $minutes, $seconds);
        }
    }
    if (stream.state === 'playing' && !node.timer) {
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
    if (stream.endTime) {
        $container.find('.endTime').text(stream.endTime);
    }
}

function incrementTimer($hours, $minutes, $seconds) {
    if (!$hours || !$minutes || !$seconds || $hours.length === 0 || $minutes.length === 0 || $seconds.length === 0) {
        return;
    }
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
                    animateNewStreamCard($container);
    $host.hide();
    $('.lds-dual-ring').show();
    selected = (selected || '').split(',');
    $host.html('');
    $.ajax({
        url: '/plugins/plexstreams/getServers.php',
        method: 'POST',
        dataType: 'json',
        data: {
            useSsl: $('input[name="FORCE_PLEX_HTTPS"]:checked').val(),
            token: token || $('#plex-token').val()
        }
    }).done(function(data) {
        serverList = data.serverList;
        if (Object.keys(serverList).length > 0) {
            for (var id in serverList) {
                if (serverList.hasOwnProperty(id)) {
                    var server = serverList[id];
                    serverList[id].Connections.forEach(function(connection) {
                        if (connection !== null) {
                            var shortHost = connection.uri;
                            shortHost = shortHost.replace(connection.protocol  + '://', '');
                            if (connection.port) {
                                shortHost = shortHost.replace(':' + connection.port, '');
                            }
                            $host.append('<input type="hidden" name="ALIAS-' + shortHost + '" value="' + server.Name + '"/>');
                            $host.append('<input type="checkbox" onchange="updateServerList(\'HOST\')" name="hostbox" id="' + connection.uri + '" data-id="' + id + '"' + (selected.indexOf(connection.uri) > -1 ? ' checked="checked"' : '' ) + ' value="' + connection.uri + '" data-address="' + connection.address + '" data-name="' + server.Name + '"/> <label for="' + connection.uri + '"> ' + server.Name + ' (' +  connection.address + ':' + connection.port + ')' + (connection.local === '0' ? ' - Remote' : '') + '</label><br/>');
                        }
                    });
                }
            }
        } else {
            $host.html('<p>No Servers found, please enter server in Custom Servers Field');
        }
        $host.show();
        $('.lds-dual-ring').hide();
    }).fail(function(jqXHR) {
        var message = jqXHR.responseJSON && jqXHR.responseJSON.error ? jqXHR.responseJSON.error : 'Unable to retrieve Plex servers.';
        $host.html('<p>' + message + '</p>');
        $host.show();
        $('.lds-dual-ring').hide();
    });
}

function loadDebugLog() {
    var $log = $('#plexstreams-debug-log');
    if ($log.length === 0) {
        return;
    }

    $.getJSON('/plugins/plexstreams/getDebugLog.php').done(function(data) {
        $log.val(data.log || '');
        $log.scrollTop($log[0].scrollHeight);
    }).fail(function(jqXHR) {
        var message = jqXHR.responseJSON && jqXHR.responseJSON.error ? jqXHR.responseJSON.error : 'Unable to load the debug log.';
        $log.val(message);
    });
}

function copyDebugLog() {
    var log = document.getElementById('plexstreams-debug-log');
    if (!log) {
        return;
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(log.value);
        return;
    }

    log.focus();
    log.select();
    document.execCommand('copy');
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

function logPlexDebugEvent(event) {
    $.post('/plugins/plexstreams/debugLog.php', {event: event});
}

getPlexOAuthPin = function () {
    var x_plex_headers = getPlexHeaders();
    var deferred = $.Deferred();

    $.ajax({
        url: 'https://plex.tv/api/v2/pins?strong=true',
        type: 'POST',
        headers: x_plex_headers,
        success: function(data) {
            logPlexDebugEvent('oauth_pin_received');
            deferred.resolve({pin: data.id, code: data.code});
        },
        error: function() {
            logPlexDebugEvent('oauth_pin_failed');
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
    logPlexDebugEvent('oauth_started');

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
                        logPlexDebugEvent('oauth_token_received');
                        closePlexOAuthWindow();
                        if (typeof success === "function") {
                            success(data.authToken)
                        }
                        getServers('#hostcontainer', $('#HOST').val(), data.authToken);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (textStatus !== "timeout") {
                        logPlexDebugEvent('oauth_token_failed');
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