(function() {
window.zkTrackView = function zkTrackView(route) {
        if (typeof route === 'string' && route.length > 1) {
            route = route.replace(/\/+$/, '');
        }
        if (!window.fetch || !window.ZK) return;
        if (window.zkIsAdmin) return;
        try { 
            if (localStorage.getItem('zk_ignore_tracking') === 'true' && window.location.search.indexOf('force_track') === -1) return; 
        } catch(e) {}

        var apiRoute = ZK.home.replace(/\/$/, '') + '/wp-json/zk/v1/sync';
        
        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        var visitorId = '';
        try {
            visitorId = localStorage.getItem('zk_visitor_id');
            if (!visitorId) {
                visitorId = generateUUID();
                localStorage.setItem('zk_visitor_id', visitorId);
            }
        } catch (e) {}

        var sessionId = '';
        try {
            sessionId = sessionStorage.getItem('zk_session_id');
            if (!sessionId) {
                sessionId = generateUUID();
                sessionStorage.setItem('zk_session_id', sessionId);
            }
        } catch (e) {}

        var activeDurationSecs = 0;
        var lastActiveTime = Date.now();
        var isHidden = document.visibilityState === 'hidden';
        var currentViewId = 0;

        function isMediaPlaying() {
            var mediaNodes = document.querySelectorAll('audio, video');
            for (var i = 0; i < mediaNodes.length; i++) {
                if (!mediaNodes[i].paused && !mediaNodes[i].ended && mediaNodes[i].readyState > 2) {
                    return true;
                }
            }
            if (window.speechSynthesis && window.speechSynthesis.speaking) {
                return true;
            }
            return false;
        }

        function updateActiveDuration() {
            var playing = isMediaPlaying();
            if (!isHidden || playing) {
                var now = Date.now();
                activeDurationSecs += Math.floor((now - lastActiveTime) / 1000);
            }
            lastActiveTime = Date.now();
        }

        function sendTrack(country, city) {
            var payload = JSON.stringify({ 
                url: route, 
                referrer: document.referrer || '',
                country: country || '', 
                city: city || '',
                screen_data: (window.screen ? window.screen.width + 'x' + window.screen.height : ''),
                visitor_id: visitorId,
                session_id: sessionId
            });
            fetch(apiRoute, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload
            }).then(r => r.json()).then(data => {
                if (data && data.view_id) currentViewId = data.view_id;
            }).catch(e => {});

            // Reset music stats for the new page view so they don't accumulate across views
            window.zkMusicPlayed = false;
            window.zkMusicDurationTotal = 0;
        }

        // Setup duration ping on page leave
        function sendDurationPing() {
            if (!currentViewId) return;
            updateActiveDuration();
            if (activeDurationSecs <= 0) return;
            var pingPayload = JSON.stringify({
                action: 'duration_ping',
                view_id: currentViewId,
                duration: activeDurationSecs,
                music_played: window.zkMusicPlayed ? 1 : 0,
                music_duration: window.zkMusicDurationTotal ? Math.floor(window.zkMusicDurationTotal) : 0,
                visitor_id: visitorId,
                session_id: sessionId
            });
            
            var blob = new Blob([pingPayload], { type: 'application/json' });
            if (navigator.sendBeacon) {
                navigator.sendBeacon(apiRoute, blob);
            } else {
                fetch(apiRoute, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: pingPayload, keepalive: true }).catch(e => {});
            }
        }

        // Cleanup old listener if SPA route changes
        if (window.zkLeaveListener) {
            window.zkLeaveListener({ type: 'spa_navigation' }); // Ping the previous page
            window.removeEventListener('visibilitychange', window.zkLeaveListener);
            window.removeEventListener('pagehide', window.zkLeaveListener);
        }
        if (window.zkDurationInterval) {
            clearInterval(window.zkDurationInterval);
        }

        window.zkLeaveListener = function(e) {
            if (e.type === 'visibilitychange') {
                updateActiveDuration();
                isHidden = document.visibilityState === 'hidden';
                if (!isHidden) {
                    lastActiveTime = Date.now();
                }
            }
            sendDurationPing();
        };
        window.addEventListener('visibilitychange', window.zkLeaveListener);
        window.addEventListener('pagehide', window.zkLeaveListener);

        window.zkDurationInterval = setInterval(function() {
            if (!isHidden || isMediaPlaying()) {
                sendDurationPing();
            }
        }, 10000);

        var geoResolved = false;
        var geoTimeout = setTimeout(function() {
            if (!geoResolved) {
                geoResolved = true;
                sendTrack('', '');
            }
        }, 2000);

        try {
            var cachedGeo = sessionStorage.getItem('zk_geo');
            if (cachedGeo) {
                var geo = JSON.parse(cachedGeo);
                geoResolved = true;
                clearTimeout(geoTimeout);
                sendTrack(geo.country || '', geo.city || '');
            } else {
                fetch('https://get.geojs.io/v1/ip/geo.json').then(r => r.json()).then(data => {
                    if (!geoResolved) {
                        geoResolved = true;
                        clearTimeout(geoTimeout);
                        if (data && data.country) {
                            var geoObj = { country: data.country, city: data.city || '' };
                            sessionStorage.setItem('zk_geo', JSON.stringify(geoObj));
                            sendTrack(geoObj.country, geoObj.city);
                        } else {
                            sendTrack('', '');
                        }
                    }
                }).catch(e => {
                    fetch('https://ipapi.co/json/').then(r => r.json()).then(data => {
                        if (!geoResolved) {
                            geoResolved = true;
                            clearTimeout(geoTimeout);
                            if (data && data.country_name) {
                                var geoObj = { country: data.country_name, city: data.city || '' };
                                sessionStorage.setItem('zk_geo', JSON.stringify(geoObj));
                                sendTrack(geoObj.country, geoObj.city);
                            } else {
                                sendTrack('', '');
                            }
                        }
                    }).catch(err => {
                        if (!geoResolved) {
                            geoResolved = true;
                            clearTimeout(geoTimeout);
                            sendTrack('', '');
                        }
                    });
                });
            }
        } catch (e) {
            if (!geoResolved) {
                geoResolved = true;
                clearTimeout(geoTimeout);
                sendTrack('', '');
            }
        }
    }

// Run initially
window.zkTrackView(window.location.pathname);
})();
