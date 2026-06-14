# Gruubuya

Social + lobby platform. PHP 8 / MySQL / Tailwind (CDN) / Font Awesome (CDN) /
vanilla JS. Realtime via an events-table bus: WebSocket server with automatic
AJAX-polling fallback.

## Setup (cPanel)

1. **Database** — open phpMyAdmin, select the database, run **`db/install.sql`**
   (whole file). It drops and recreates all tables — also the update path:
   every schema change ships as a new full `install.sql`.
2. **Config** — copy `includes/config.sample.php` to `includes/config.php`,
   fill in real credentials, upload manually via File Manager.
   `config.php` is gitignored and never deployed by CI.
3. **Deploy** — push to `main`; GitHub Actions FTP-syncs the repo to the
   server (`.github/workflows/main.yml`).

## Realtime

All live updates (lobby chat, member joins/leaves, notifications) are rows in
the `events` table with a channel (`user:{id}` / `lobby:{id}`).

- **Default: polling.** Browsers poll `api/events.php` every 3 s. Zero setup.
- **Optional: WebSockets.** Add the `ws` block to `includes/config.php`, then
  run `php ws/server.php` from the cPanel Terminal (keep it alive with a
  cron `@reboot` entry or `nohup`). Open the chosen port in the firewall.
  Browsers authenticate with single-use tokens from `api/ws_token.php` and
  fall back to polling automatically if the socket is unreachable.

Old events are pruned opportunistically (15-minute retention).

## Layout

| Path | Purpose |
| --- | --- |
| `includes/` | bootstrap (config/PDO/session/CSRF), auth, mailer (raw SMTP), bus, social helpers, layout |
| `api/` | JSON endpoints: friends, lobbies, lobby_chat, events, ws_token |
| `assets/js/` | `app.js` (UI + post/friend/lobby actions), `realtime.js` (WS + polling client) |
| `ws/server.php` | dependency-free WebSocket push server (CLI) |
| `db/install.sql` | full schema, DROP + CREATE |
| `uploads/avatars/` | user avatars (PHP execution disabled via .htaccess) |

## Features

- **Auth**: register, login, email verification (required before use),
  password reset — all email via raw-SMTP mailer (ssl:465), no Composer.
- **Profiles**: display name, bio, avatar upload, online indicator.
- **Social**: friend requests (send/accept/decline/cancel/remove) and user
  search.
- **Lobbies**: public/private (8-char invite codes), capacity limits,
  realtime chat, member list with online dots, owner kick/delete.
- **Notifications**: bell badge + toasts in realtime; friend requests,
  accepts, lobby kicks.
