/* Realtime client for the events-table bus. Prefers the WebSocket server
 * (GB.wsUrl from config) and falls back to polling api/events.php. */
(function () {
    'use strict';

    var subs = {};            // channel -> [handler]
    var channels = new Set(); // channels we want
    var last = 0;             // last event id seen (polling cursor)
    var ws = null;
    var pollTimer = null;
    var started = false;
    var wsGivenUp = false;

    function dispatch(ev) {
        (subs[ev.channel] || []).forEach(function (fn) {
            try { fn(ev); } catch (e) { console.error('Realtime handler error', e); }
        });
    }

    async function poll() {
        if (!channels.size || !GB.uid) return;
        try {
            var qs = 'since=' + last + '&channels=' + encodeURIComponent(Array.from(channels).join(','));
            var res = await fetch('api/events.php?' + qs, {headers: {'X-CSRF': GB.csrf}});
            var j = await res.json();
            if (j.ok) {
                last = j.last;
                (j.events || []).forEach(dispatch);
            }
        } catch (e) { /* transient network error — next tick retries */ }
    }

    function startPolling() {
        if (pollTimer) return;
        poll();
        pollTimer = setInterval(poll, 3000);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    async function startWs() {
        if (!GB.wsUrl || wsGivenUp || !GB.uid) { startPolling(); return; }
        try {
            var t = await api('api/ws_token.php', {});
            ws = new WebSocket(GB.wsUrl);
            ws.onopen = function () {
                ws.send(JSON.stringify({type: 'auth', token: t.token}));
            };
            ws.onmessage = function (m) {
                var ev;
                try { ev = JSON.parse(m.data); } catch (e) { return; }
                if (ev.type === 'ready') {
                    channels.forEach(function (c) {
                        ws.send(JSON.stringify({type: 'subscribe', channel: c}));
                    });
                    stopPolling();
                } else if (ev.channel) {
                    if (ev.id) last = Math.max(last, ev.id);
                    dispatch(ev);
                }
            };
            ws.onclose = function () {
                ws = null;
                wsGivenUp = true; // this page-load sticks to polling from here on
                startPolling();
            };
        } catch (e) {
            startPolling();
        }
    }

    window.Realtime = {
        subscribe: function (channel, handler) {
            (subs[channel] = subs[channel] || []).push(handler);
            if (!channels.has(channel)) {
                channels.add(channel);
                if (ws && ws.readyState === 1) {
                    ws.send(JSON.stringify({type: 'subscribe', channel: channel}));
                }
            }
            if (!started) {
                started = true;
                if (GB.wsUrl) startWs(); else startPolling();
            }
        }
    };

    // Every logged-in page listens on its own user channel for notifications.
    document.addEventListener('DOMContentLoaded', function () {
        if (!GB.uid) return;
        Realtime.subscribe('user:' + GB.uid, function (ev) {
            if (ev.type === 'notification') {
                bumpBell(1);
                if (ev.payload && ev.payload.text) toast(ev.payload.text);
            }
        });
    });
})();
