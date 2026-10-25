<?php

declare(strict_types=1);

return static function (string $driver): array {
    $mysql = $driver === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY';
    // Une clé étrangère doit avoir exactement le type de l'id référencé, sinon MySQL refuse la table
    $fk = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    // MariaDB crée les tables en latin1 par défaut : les emojis et l'arabe y seraient perdus
    $options = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    return [
        "CREATE TABLE settings (
            name VARCHAR(255) NOT NULL PRIMARY KEY,
            value TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            modified_at DATETIME NOT NULL
        )$options",

        // Email unique insensible à la casse en MySQL, sensible en SQLite : à normaliser en minuscules avant écriture
        "CREATE TABLE users (
            id $id,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            firstname VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            public_name VARCHAR(100) NOT NULL,
            roles TEXT NOT NULL,
            is_activated BOOLEAN NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            modified_at DATETIME NOT NULL,
            verified_at DATETIME NOT NULL
        )$options",

        "CREATE TABLE announcements (
            id $id,
            user_id $fk NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            image VARCHAR(255) NULL,
            published_at DATETIME NULL,
            is_notified BOOLEAN NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            modified_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )$options",

        'CREATE INDEX announcements_published_at ON announcements (published_at)',
    ];
};
