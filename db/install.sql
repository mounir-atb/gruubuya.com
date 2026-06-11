-- =====================================================================
-- Gruubuya — full database install
-- Run this whole file in phpMyAdmin against database `hadrqrak_main`.
-- WARNING: drops and recreates ALL platform tables — existing data is lost.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS ws_tokens;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS lobby_messages;
DROP TABLE IF EXISTS lobby_members;
DROP TABLE IF EXISTS lobbies;
DROP TABLE IF EXISTS post_comments;
DROP TABLE IF EXISTS post_likes;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS friendships;
DROP TABLE IF EXISTS user_tokens;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------ users & auth

CREATE TABLE users (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username          VARCHAR(20)  NOT NULL,
    email             VARCHAR(190) NOT NULL,
    pass_hash         VARCHAR(255) NOT NULL,
    display_name      VARCHAR(50)  NOT NULL,
    bio               VARCHAR(500) NOT NULL DEFAULT '',
    avatar            VARCHAR(150) NOT NULL DEFAULT '',
    email_verified_at DATETIME     NULL DEFAULT NULL,
    last_seen_at      DATETIME     NULL DEFAULT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_tokens (
    id         INT UNSIGNED           NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED           NOT NULL,
    type       ENUM('verify','reset') NOT NULL,
    token_hash CHAR(64)               NOT NULL,
    expires_at DATETIME               NOT NULL,
    used_at    DATETIME               NULL DEFAULT NULL,
    created_at DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_tokens_hash (token_hash),
    KEY idx_user_tokens_user (user_id, type),
    CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ social

CREATE TABLE friendships (
    id           INT UNSIGNED               NOT NULL AUTO_INCREMENT,
    requester_id INT UNSIGNED               NOT NULL,
    addressee_id INT UNSIGNED               NOT NULL,
    status       ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
    created_at   DATETIME                   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME                   NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_friendships_pair (requester_id, addressee_id),
    KEY idx_friendships_addressee (addressee_id, status),
    CONSTRAINT fk_friendships_requester FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_friendships_addressee FOREIGN KEY (addressee_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_user (user_id, id),
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_likes (
    post_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, user_id),
    CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_comments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       VARCHAR(500) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_comments_post (post_id, id),
    CONSTRAINT fk_post_comments_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_post_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ lobbies

CREATE TABLE lobbies (
    id          INT UNSIGNED             NOT NULL AUTO_INCREMENT,
    owner_id    INT UNSIGNED             NOT NULL,
    name        VARCHAR(60)              NOT NULL,
    description VARCHAR(300)             NOT NULL DEFAULT '',
    privacy     ENUM('public','private') NOT NULL DEFAULT 'public',
    code        CHAR(8)                  NOT NULL,
    max_members SMALLINT UNSIGNED        NOT NULL DEFAULT 20,
    created_at  DATETIME                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lobbies_code (code),
    KEY idx_lobbies_privacy (privacy, id),
    CONSTRAINT fk_lobbies_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lobby_members (
    lobby_id  INT UNSIGNED           NOT NULL,
    user_id   INT UNSIGNED           NOT NULL,
    role      ENUM('owner','member') NOT NULL DEFAULT 'member',
    joined_at DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (lobby_id, user_id),
    KEY idx_lobby_members_user (user_id),
    CONSTRAINT fk_lobby_members_lobby FOREIGN KEY (lobby_id) REFERENCES lobbies (id) ON DELETE CASCADE,
    CONSTRAINT fk_lobby_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lobby_messages (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    lobby_id   INT UNSIGNED  NOT NULL,
    user_id    INT UNSIGNED  NOT NULL,
    body       VARCHAR(1000) NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lobby_messages_lobby (lobby_id, id),
    CONSTRAINT fk_lobby_messages_lobby FOREIGN KEY (lobby_id) REFERENCES lobbies (id) ON DELETE CASCADE,
    CONSTRAINT fk_lobby_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ notifications & realtime bus

CREATE TABLE notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    actor_id   INT UNSIGNED NULL DEFAULT NULL,
    type       VARCHAR(30)  NOT NULL,
    data       TEXT         NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read, id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel    VARCHAR(40)     NOT NULL,
    type       VARCHAR(30)     NOT NULL,
    payload    TEXT            NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_channel (channel, id),
    KEY idx_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ws_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ws_tokens_hash (token_hash),
    CONSTRAINT fk_ws_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
