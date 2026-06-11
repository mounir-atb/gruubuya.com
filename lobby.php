<?php
require __DIR__ . '/includes/bootstrap.php';

$me    = require_verified();
$lobby = fetch_lobby((int) ($_GET['id'] ?? 0));

if (!$lobby) {
    flash('error', 'That lobby does not exist.');
    redirect('lobbies.php');
}

$role     = lobby_member_role((int) $lobby['id'], (int) $me['id']);
$isMember = $role !== null;
$isOwner  = $role === 'owner';

if (!$isMember && $lobby['privacy'] === 'private') {
    flash('error', 'That lobby is private — join with an invite code.');
    redirect('lobbies.php');
}

$members = lobby_members((int) $lobby['id']);

$pageTitle = $lobby['name'];
$active    = 'lobbies';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="grid lg:grid-cols-[1fr_280px] gap-4" id="lobbyRoot"
     data-lobby="<?= (int) $lobby['id'] ?>" data-owner="<?= $isOwner ? 1 : 0 ?>" data-member="<?= $isMember ? 1 : 0 ?>">

    <div class="bg-white border border-gray-200 rounded-2xl flex flex-col" style="height:calc(100vh - 8.5rem);min-height:420px;">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <a href="lobbies.php" class="text-gray-400 hover:text-violet-600"><i class="fa-solid fa-arrow-left"></i></a>
            <i class="fa-solid <?= $lobby['privacy'] === 'private' ? 'fa-lock text-gray-400' : 'fa-earth-americas text-violet-500' ?>"></i>
            <div class="min-w-0 flex-1">
                <h1 class="font-bold truncate"><?= e($lobby['name']) ?></h1>
                <?php if ($lobby['description'] !== ''): ?>
                    <p class="text-xs text-gray-400 truncate"><?= e($lobby['description']) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($isMember): ?>
                <button type="button" data-copy-code="<?= e($lobby['code']) ?>"
                        class="text-xs border border-gray-200 hover:border-violet-300 hover:text-violet-700 rounded-lg px-3 py-1.5 font-mono tracking-wider"
                        title="Copy invite code">
                    <i class="fa-regular fa-copy mr-1"></i><?= e($lobby['code']) ?>
                </button>
            <?php endif; ?>
            <?php if ($isOwner): ?>
                <button type="button" id="deleteLobbyBtn" class="text-xs border border-gray-200 hover:border-red-300 hover:text-red-600 rounded-lg px-3 py-1.5">
                    <i class="fa-regular fa-trash-can mr-1"></i>Delete
                </button>
            <?php elseif ($isMember): ?>
                <button type="button" id="leaveLobbyBtn" class="text-xs border border-gray-200 hover:border-red-300 hover:text-red-600 rounded-lg px-3 py-1.5">
                    <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i>Leave
                </button>
            <?php endif; ?>
        </div>

        <div id="chatMessages" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <p class="text-center text-gray-300 text-sm py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading messages&hellip;</p>
        </div>

        <?php if ($isMember): ?>
            <form id="chatForm" class="px-5 py-4 border-t border-gray-100 flex items-center gap-2">
                <input id="chatInput" name="body" maxlength="1000" placeholder="Message #<?= e($lobby['name']) ?>" autocomplete="off"
                       class="flex-1 min-w-0 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                <button class="bg-violet-600 hover:bg-violet-700 text-white rounded-xl w-11 h-11 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        <?php else: ?>
            <div class="px-5 py-4 border-t border-gray-100 text-center">
                <button data-join-lobby="<?= (int) $lobby['id'] ?>" class="bg-violet-600 hover:bg-violet-700 text-white font-semibold text-sm rounded-xl px-6 py-2.5">
                    <i class="fa-solid fa-right-to-bracket mr-1"></i> Join lobby to chat
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl p-5 h-fit">
        <h2 class="font-semibold text-sm text-gray-700 mb-3">
            Members <span id="memberCount" class="text-gray-400">(<?= count($members) ?>/<?= (int) $lobby['max_members'] ?>)</span>
        </h2>
        <div id="membersList" class="space-y-2.5"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var root    = document.getElementById('lobbyRoot');
    var lobbyId = +root.dataset.lobby;
    var isOwner = root.dataset.owner === '1';
    var isMember = root.dataset.member === '1';
    var maxMembers = <?= (int) $lobby['max_members'] ?>;
    var box     = document.getElementById('chatMessages');
    var rendered = {};

    function msgHtml(m) {
        var name = m.user.display_name || m.user.username;
        var mine = m.user.id === GB.uid;
        return '<div class="flex items-start gap-2.5" data-mid="' + m.id + '">'
            + '<a href="profile.php?u=' + encodeURIComponent(m.user.username) + '">' + avatarHtml(m.user, 'w-8 h-8 text-xs') + '</a>'
            + '<div class="min-w-0">'
            + '<p class="text-xs text-gray-400 mb-0.5"><a class="font-semibold text-gray-700 hover:text-violet-700" href="profile.php?u='
            + encodeURIComponent(m.user.username) + '">' + esc(name) + '</a> ' + esc(fmtTime(m.ts)) + '</p>'
            + '<p class="text-sm text-gray-800 break-words rounded-2xl px-3.5 py-2 inline-block '
            + (mine ? 'bg-violet-50' : 'bg-gray-50') + '">' + esc(m.body).replace(/\n/g, '<br>') + '</p>'
            + '</div></div>';
    }

    function fmtTime(ts) {
        var d = new Date(ts * 1000);
        return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
    }

    function appendMsg(m, scroll) {
        if (rendered[m.id]) return;
        rendered[m.id] = true;
        box.insertAdjacentHTML('beforeend', msgHtml(m));
        if (scroll) box.scrollTop = box.scrollHeight;
    }

    function loadHistory() {
        fetch('api/lobby_chat.php?action=history&lobby_id=' + lobbyId)
            .then(function (r) { return r.json(); })
            .then(function (j) {
                box.innerHTML = '';
                if (!j.ok) {
                    box.innerHTML = '<p class="text-center text-gray-300 text-sm py-8">' + esc(j.error || 'Could not load messages.') + '</p>';
                    return;
                }
                if (!j.messages.length) {
                    box.innerHTML = '<p class="text-center text-gray-300 text-sm py-8" id="noMsgs">No messages yet — say hi!</p>';
                }
                j.messages.forEach(function (m) { appendMsg(m, false); });
                box.scrollTop = box.scrollHeight;
            });
    }

    function renderMembers(list) {
        var el = document.getElementById('membersList');
        el.innerHTML = '';
        list.forEach(function (u) {
            var name = u.display_name || u.username;
            var row = '<div class="flex items-center gap-2.5">'
                + '<a href="profile.php?u=' + encodeURIComponent(u.username) + '" class="relative">'
                + avatarHtml(u, 'w-8 h-8 text-xs')
                + (u.online ? '<span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-white"></span>' : '')
                + '</a>'
                + '<a href="profile.php?u=' + encodeURIComponent(u.username) + '" class="text-sm font-medium truncate flex-1 hover:text-violet-700">'
                + esc(name)
                + (u.role === 'owner' ? ' <i class="fa-solid fa-crown text-amber-400 text-xs" title="Owner"></i>' : '')
                + '</a>';
            if (isOwner && u.role !== 'owner') {
                row += '<button data-kick="' + u.id + '" class="text-gray-300 hover:text-red-500 text-xs" title="Kick"><i class="fa-solid fa-xmark"></i></button>';
            }
            row += '</div>';
            el.insertAdjacentHTML('beforeend', row);
        });
        document.getElementById('memberCount').textContent = '(' + list.length + '/' + maxMembers + ')';
    }

    function loadMembers() {
        fetch('api/lobbies.php?action=members&lobby_id=' + lobbyId)
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.ok) renderMembers(j.members); });
    }

    loadHistory();
    loadMembers();

    if (isMember) {
        Realtime.subscribe('lobby:' + lobbyId, function (ev) {
            if (ev.type === 'chat.message') {
                var noMsgs = document.getElementById('noMsgs');
                if (noMsgs) noMsgs.remove();
                appendMsg(ev.payload, true);
            } else if (ev.type === 'member.join') {
                loadMembers();
            } else if (ev.type === 'member.leave') {
                if (ev.payload.user_id === GB.uid && ev.payload.kicked) {
                    toast('You were removed from this lobby.', 'error');
                    setTimeout(function () { location.href = 'lobbies.php'; }, 1200);
                    return;
                }
                loadMembers();
            } else if (ev.type === 'lobby.deleted') {
                toast('This lobby was deleted by its owner.', 'error');
                setTimeout(function () { location.href = 'lobbies.php'; }, 1200);
            }
        });
    }

    var form = document.getElementById('chatForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = document.getElementById('chatInput');
            var body  = input.value.trim();
            if (!body) return;
            input.value = '';
            api('api/lobby_chat.php', {action: 'send', lobby_id: lobbyId, body: body})
                .then(function (j) {
                    var noMsgs = document.getElementById('noMsgs');
                    if (noMsgs) noMsgs.remove();
                    appendMsg(j.msg, true);
                })
                .catch(function (err) { toast(err.message, 'error'); input.value = body; });
        });
    }

    var leaveBtn = document.getElementById('leaveLobbyBtn');
    if (leaveBtn) {
        leaveBtn.addEventListener('click', function () {
            if (!confirm('Leave this lobby?')) return;
            api('api/lobbies.php', {action: 'leave', lobby_id: lobbyId})
                .then(function () { location.href = 'lobbies.php'; })
                .catch(function (err) { toast(err.message, 'error'); });
        });
    }

    var deleteBtn = document.getElementById('deleteLobbyBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            if (!confirm('Delete this lobby for everyone? This cannot be undone.')) return;
            api('api/lobbies.php', {action: 'delete', lobby_id: lobbyId})
                .then(function () { location.href = 'lobbies.php'; })
                .catch(function (err) { toast(err.message, 'error'); });
        });
    }

    document.getElementById('membersList').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-kick]');
        if (!btn) return;
        if (!confirm('Kick this member?')) return;
        api('api/lobbies.php', {action: 'kick', lobby_id: lobbyId, user_id: +btn.dataset.kick})
            .then(loadMembers)
            .catch(function (err) { toast(err.message, 'error'); });
    });
});
</script>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
