/* Gruubuya core client helpers + global UI behaviors. Loaded deferred on
 * every page; page-specific code runs in DOMContentLoaded handlers. */
(function () {
    'use strict';

    window.GB = window.GB || {csrf: '', uid: 0, wsUrl: null, unread: 0};

    // ------------------------------------------------------------ helpers

    window.esc = function (s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };

    window.api = async function (url, data) {
        var opts = {headers: {'X-CSRF': GB.csrf}};
        if (data !== undefined) {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
        var res = await fetch(url, opts);
        var j;
        try { j = await res.json(); } catch (e) { j = {ok: false, error: 'Bad server response.'}; }
        if (!res.ok || j.ok === false) throw new Error(j.error || 'Request failed (' + res.status + ').');
        return j;
    };

    window.toast = function (msg, type) {
        var wrap = document.getElementById('toasts');
        if (!wrap) return;
        var colors = type === 'error'
            ? 'bg-red-50 border-red-200 text-red-700'
            : (type === 'success' ? 'bg-green-50 border-green-200 text-green-700'
                                  : 'bg-violet-50 border-violet-200 text-violet-700');
        var el = document.createElement('div');
        el.className = 'border rounded-xl px-4 py-3 text-sm shadow-sm ' + colors;
        el.textContent = msg;
        wrap.appendChild(el);
        setTimeout(function () { el.remove(); }, 4500);
    };

    window.setBell = function (n) {
        var badge = document.getElementById('notifBadge');
        if (!badge) return;
        GB.unread = Math.max(0, n);
        badge.textContent = GB.unread > 99 ? '99+' : GB.unread;
        badge.classList.toggle('hidden', GB.unread === 0);
    };
    window.bumpBell = function (d) { setBell(GB.unread + d); };

    // CRC32 (IEEE) matching PHP's crc32() so JS picks the same avatar color.
    function crc32(str) {
        var crc = 0xFFFFFFFF, c, k;
        for (var i = 0; i < str.length; i++) {
            c = (crc ^ str.charCodeAt(i)) & 0xFF;
            for (k = 0; k < 8; k++) c = (c & 1) ? ((c >>> 1) ^ 0xEDB88320) : (c >>> 1);
            crc = (crc >>> 8) ^ c;
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    window.avatarHtml = function (user, cls) {
        if (user.avatar) {
            return '<img src="' + esc(user.avatar) + '" alt="" class="' + esc(cls) + ' rounded-full object-cover shrink-0">';
        }
        var palette = ['bg-violet-600', 'bg-purple-600', 'bg-fuchsia-600', 'bg-indigo-600', 'bg-pink-600'];
        var color = palette[crc32(String(user.username || '').toLowerCase()) % palette.length];
        var name = user.display_name || user.username || '?';
        return '<span class="' + color + ' ' + esc(cls)
            + ' rounded-full inline-flex items-center justify-center text-white font-semibold select-none shrink-0">'
            + esc(name.charAt(0).toUpperCase()) + '</span>';
    };

    // ------------------------------------------------------------ dropdowns & modals

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-dropdown]');
        document.querySelectorAll('[data-dropdown]').forEach(function (btn) {
            var menu = document.getElementById(btn.dataset.dropdown);
            if (!menu) return;
            if (trigger === btn) menu.classList.toggle('hidden');
            else if (!menu.contains(e.target)) menu.classList.add('hidden');
        });

        var opener = e.target.closest('[data-modal-open]');
        if (opener) {
            var modal = document.getElementById(opener.dataset.modalOpen);
            if (modal) modal.classList.remove('hidden');
        }
        var closer = e.target.closest('[data-modal-close]');
        if (closer) {
            var open = closer.closest('.fixed');
            if (open) open.classList.add('hidden');
        }
        // Click on the dark overlay (outside the dialog box) closes the modal.
        if (e.target.matches('.fixed.inset-0') && e.target.querySelector('[data-modal-box]')) {
            e.target.classList.add('hidden');
        }

        var copyBtn = e.target.closest('[data-copy-code]');
        if (copyBtn && navigator.clipboard) {
            navigator.clipboard.writeText(copyBtn.dataset.copyCode)
                .then(function () { toast('Invite code copied!', 'success'); });
        }
    });

    // ------------------------------------------------------------ posts (feed / profile / post pages)

    document.addEventListener('click', async function (e) {
        var likeBtn = e.target.closest('[data-like]');
        if (likeBtn) {
            try {
                var j = await api('api/posts.php', {action: 'like', post_id: +likeBtn.dataset.like});
                likeBtn.querySelector('[data-like-count]').textContent = j.count;
                likeBtn.querySelector('i').className = (j.liked ? 'fa-solid' : 'fa-regular') + ' fa-heart';
                likeBtn.className = 'flex items-center gap-1.5 '
                    + (j.liked ? 'text-violet-600' : 'text-gray-500 hover:text-violet-600');
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        var cBtn = e.target.closest('[data-comments]');
        if (cBtn) {
            var article = cBtn.closest('article');
            var box = article.querySelector('[data-comments-box]');
            if (box.classList.contains('hidden')) {
                box.classList.remove('hidden');
                if (!box.dataset.loaded) {
                    box.dataset.loaded = '1';
                    try {
                        var jc = await api('api/posts.php?action=comments&post_id=' + cBtn.dataset.comments);
                        box.querySelector('[data-comments-list]').innerHTML = jc.html;
                    } catch (err) { toast(err.message, 'error'); }
                }
                var input = box.querySelector('input[name=body]');
                if (input) input.focus();
            } else {
                box.classList.add('hidden');
            }
            return;
        }

        var delBtn = e.target.closest('[data-del-post]');
        if (delBtn) {
            if (!confirm('Delete this post?')) return;
            try {
                await api('api/posts.php', {action: 'delete', post_id: +delBtn.dataset.delPost});
                delBtn.closest('article').remove();
                toast('Post deleted.', 'success');
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        var moreBtn = e.target.closest('[data-load-more]');
        if (moreBtn) {
            var url = 'api/posts.php?action=' + moreBtn.dataset.mode + '&before_id=' + moreBtn.dataset.before;
            if (moreBtn.dataset.user) url += '&user_id=' + moreBtn.dataset.user;
            try {
                var jm = await api(url);
                document.getElementById('postsList').insertAdjacentHTML('beforeend', jm.html);
                moreBtn.dataset.before = jm.last;
                if (!jm.more) moreBtn.parentElement.remove();
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        var joinBtn = e.target.closest('[data-join-lobby]');
        if (joinBtn) {
            try {
                var jl = await api('api/lobbies.php', {action: 'join', lobby_id: +joinBtn.dataset.joinLobby});
                location.href = 'lobby.php?id=' + jl.lobby_id;
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        var friendBtn = e.target.closest('[data-friend-act]');
        if (friendBtn) {
            var act = friendBtn.dataset.friendAct;
            if (act === 'unfriend' && !confirm('Remove this friend?')) return;
            var holder = friendBtn.closest('[data-uid]');
            if (!holder) return;
            try {
                await api('api/friends.php', {action: act, user_id: +holder.dataset.uid});
                if (document.getElementById('searchResults') && document.getElementById('searchResults').contains(friendBtn)) {
                    runFriendSearch(); // refresh result states in place
                } else {
                    location.reload();
                }
            } catch (err) { toast(err.message, 'error'); }
        }
    });

    document.addEventListener('submit', async function (e) {
        var f = e.target;

        if (f.id === 'composerForm') {
            e.preventDefault();
            var ta = f.querySelector('textarea[name=body]');
            var body = ta.value.trim();
            if (!body) return;
            try {
                var j = await api('api/posts.php', {action: 'create', body: body});
                var empty = document.getElementById('emptyFeed');
                if (empty) empty.remove();
                document.getElementById('postsList').insertAdjacentHTML('afterbegin', j.html);
                ta.value = '';
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        if (f.matches('[data-comment-form]')) {
            e.preventDefault();
            var input = f.querySelector('input[name=body]');
            var text = input.value.trim();
            if (!text) return;
            try {
                var jc = await api('api/posts.php', {action: 'comment', post_id: +f.dataset.pid, body: text});
                f.closest('[data-comments-box]').querySelector('[data-comments-list]')
                    .insertAdjacentHTML('beforeend', jc.html);
                f.closest('article').querySelector('[data-comment-count]').textContent = jc.count;
                input.value = '';
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        if (f.id === 'createLobbyForm') {
            e.preventDefault();
            try {
                var jn = await api('api/lobbies.php', {
                    action:      'create',
                    name:        f.querySelector('[name=name]').value.trim(),
                    description: f.querySelector('[name=description]').value.trim(),
                    privacy:     f.querySelector('[name=privacy]').value,
                    max_members: +f.querySelector('[name=max_members]').value
                });
                location.href = 'lobby.php?id=' + jn.lobby_id;
            } catch (err) { toast(err.message, 'error'); }
            return;
        }

        if (f.id === 'joinCodeForm') {
            e.preventDefault();
            var code = f.querySelector('[name=code]').value.trim();
            if (!code) return;
            try {
                var jj = await api('api/lobbies.php', {action: 'join_code', code: code});
                location.href = 'lobby.php?id=' + jj.lobby_id;
            } catch (err) { toast(err.message, 'error'); }
        }
    });

    // ------------------------------------------------------------ friend search (friends.php?tab=find)

    var searchTimer = null;

    window.runFriendSearch = async function () {
        var input = document.getElementById('friendSearch');
        var out = document.getElementById('searchResults');
        if (!input || !out) return;
        var q = input.value.trim();
        if (q.length < 2) {
            out.innerHTML = '<p class="text-center py-10 text-gray-400 text-sm">Type at least 2 characters to search.</p>';
            return;
        }
        try {
            var j = await api('api/friends.php?action=search&q=' + encodeURIComponent(q));
            if (!j.users.length) {
                out.innerHTML = '<p class="text-center py-10 text-gray-400 text-sm">No one found for "' + esc(q) + '".</p>';
                return;
            }
            out.innerHTML = j.users.map(function (u) {
                var name = u.display_name || u.username;
                var actions = {
                    none:        '<button data-friend-act="request" class="bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold rounded-lg px-3 py-1.5">Add</button>',
                    pending_out: '<button data-friend-act="cancel" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-xs font-medium rounded-lg px-3 py-1.5">Cancel request</button>',
                    pending_in:  '<button data-friend-act="accept" class="bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold rounded-lg px-3 py-1.5">Accept</button>'
                               + '<button data-friend-act="decline" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-xs font-medium rounded-lg px-3 py-1.5">Decline</button>',
                    friends:     '<button data-friend-act="unfriend" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-xs font-medium rounded-lg px-3 py-1.5">Remove</button>'
                }[u.state] || '';
                return '<div class="flex items-center gap-3 bg-white border border-gray-200 rounded-2xl p-4">'
                    + '<a href="profile.php?u=' + encodeURIComponent(u.username) + '" class="relative">'
                    + avatarHtml(u, 'w-11 h-11 text-base')
                    + (u.online ? '<span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-green-500 border-2 border-white"></span>' : '')
                    + '</a>'
                    + '<div class="min-w-0 flex-1">'
                    + '<a href="profile.php?u=' + encodeURIComponent(u.username) + '" class="font-semibold hover:text-violet-700 truncate block">' + esc(name) + '</a>'
                    + '<p class="text-xs text-gray-400 truncate">@' + esc(u.username) + '</p>'
                    + '</div>'
                    + '<div class="flex gap-2 shrink-0" data-uid="' + u.id + '">' + actions + '</div>'
                    + '</div>';
            }).join('');
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('friendSearch');
        if (input) {
            input.focus();
            input.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(runFriendSearch, 300);
            });
        }
    });
})();
