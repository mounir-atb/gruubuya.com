<?php
require __DIR__ . '/includes/bootstrap.php';

$me = require_verified();

$st = db()->prepare(
    'SELECT l.*, (SELECT COUNT(*) FROM lobby_members m WHERE m.lobby_id = l.id) AS member_count,
            (SELECT COUNT(*) FROM lobby_members m2 WHERE m2.lobby_id = l.id AND m2.user_id = ?) AS is_member
     FROM lobbies l
     JOIN lobby_members mm ON mm.lobby_id = l.id AND mm.user_id = ?
     ORDER BY l.id DESC'
);
$st->execute([$me['id'], $me['id']]);
$mine = $st->fetchAll();

$st = db()->prepare(
    'SELECT l.*, (SELECT COUNT(*) FROM lobby_members m WHERE m.lobby_id = l.id) AS member_count,
            (SELECT COUNT(*) FROM lobby_members m2 WHERE m2.lobby_id = l.id AND m2.user_id = ?) AS is_member
     FROM lobbies l WHERE l.privacy = \'public\'
     ORDER BY member_count DESC, l.id DESC LIMIT 50'
);
$st->execute([$me['id']]);
$public = $st->fetchAll();

$pageTitle = 'Lobbies';
$active    = 'lobbies';
require __DIR__ . '/includes/layout/header.php';

$lobbyCard = function (array $l): string {
    $isMember = !empty($l['is_member']);
    $btn = $isMember
        ? '<a href="lobby.php?id=' . (int) $l['id'] . '" class="bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl px-4 py-2">Open <i class="fa-solid fa-arrow-right ml-1"></i></a>'
        : '<button data-join-lobby="' . (int) $l['id'] . '" class="border border-violet-200 text-violet-700 hover:bg-violet-50 text-sm font-semibold rounded-xl px-4 py-2">Join</button>';
    return '<div class="bg-white border border-gray-200 rounded-2xl p-5 flex flex-col">'
        . '<div class="flex items-center gap-2 mb-1">'
        . '<i class="fa-solid ' . ($l['privacy'] === 'private' ? 'fa-lock text-gray-400' : 'fa-earth-americas text-violet-500') . ' text-sm"></i>'
        . '<h3 class="font-semibold truncate">' . e($l['name']) . '</h3>'
        . '</div>'
        . ($l['description'] !== '' ? '<p class="text-sm text-gray-500 line-clamp-2 mb-3">' . e($l['description']) . '</p>' : '<p class="mb-3"></p>')
        . '<div class="mt-auto flex items-center justify-between">'
        . '<span class="text-xs text-gray-400"><i class="fa-solid fa-user-group mr-1"></i>'
        . (int) $l['member_count'] . ' / ' . (int) $l['max_members'] . '</span>'
        . $btn
        . '</div></div>';
};
?>
<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold">Lobbies</h1>
        <div class="flex gap-2">
            <form id="joinCodeForm" class="flex gap-2">
                <input name="code" placeholder="Invite code" maxlength="8" autocomplete="off"
                       class="w-32 border border-gray-200 rounded-xl px-3 py-2 text-sm uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-violet-500">
                <button class="border border-violet-200 text-violet-700 hover:bg-violet-50 text-sm font-semibold rounded-xl px-4 py-2">
                    Join
                </button>
            </form>
            <button data-modal-open="createLobbyModal" class="bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl px-4 py-2">
                <i class="fa-solid fa-plus mr-1"></i> Create lobby
            </button>
        </div>
    </div>

    <?php if ($mine): ?>
        <div>
            <h2 class="font-semibold text-gray-700 mb-3">My lobbies</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($mine as $l) echo $lobbyCard($l); ?>
            </div>
        </div>
    <?php endif; ?>

    <div>
        <h2 class="font-semibold text-gray-700 mb-3">Public lobbies</h2>
        <?php if (!$public): ?>
            <div class="text-center py-12 text-gray-400">
                <i class="fa-solid fa-people-group text-4xl mb-3"></i>
                <p>No public lobbies yet — create the first one!</p>
            </div>
        <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($public as $l) echo $lobbyCard($l); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="createLobbyModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" data-modal-box>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold">Create a lobby</h2>
            <button data-modal-close class="text-gray-400 hover:text-gray-600 w-8 h-8"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="createLobbyForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="lobbyName">Name</label>
                <input id="lobbyName" name="name" required minlength="3" maxlength="60" autocomplete="off"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="lobbyDesc">Description <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea id="lobbyDesc" name="description" rows="2" maxlength="300"
                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-violet-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1" for="lobbyPrivacy">Privacy</label>
                    <select id="lobbyPrivacy" name="privacy"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="public">Public</option>
                        <option value="private">Private (invite code)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="lobbyMax">Max members</label>
                    <input id="lobbyMax" name="max_members" type="number" value="20" min="2" max="100"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
            <button class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl py-2.5">
                <i class="fa-solid fa-plus mr-1"></i> Create
            </button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
