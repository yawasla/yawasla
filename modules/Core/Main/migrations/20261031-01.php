<?php

declare(strict_types=1);

return new class {
    /** @return list<string> */
    public function up(string $driver): array
    {
        $mysql = $driver === 'mysql';
        $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY';
        $fk = $mysql ? 'INT UNSIGNED' : 'INTEGER';
        $longtext = $mysql ? 'LONGTEXT' : 'TEXT';
        $mediumtext = $mysql ? 'MEDIUMTEXT' : 'TEXT';
        $options = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        return [
            "CREATE TABLE config (
                id $id,
                name VARCHAR(255) NOT NULL UNIQUE,
                value TEXT NOT NULL,
                created_at DATETIME NOT NULL
            )$options",

            "CREATE TABLE migration (
                id $id,
                name VARCHAR(255) NOT NULL,
                module VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE (module, name)
            )$options",

            "CREATE TABLE module (
                id $id,
                name VARCHAR(100) NOT NULL UNIQUE,
                namespace VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                deactivated_at DATETIME NULL,
                is_activated BOOLEAN NOT NULL DEFAULT 1
            )$options",

            "CREATE TABLE organization (
                id $id,
                name VARCHAR(255) NOT NULL UNIQUE,
                rna VARCHAR(20) NULL,
                siren VARCHAR(20) NULL,
                email VARCHAR(255) NULL,
                phone VARCHAR(30) NULL,
                fax VARCHAR(30) NULL,
                address VARCHAR(255) NULL,
                city VARCHAR(100) NULL,
                zip_code VARCHAR(20) NULL,
                country VARCHAR(100) NULL,
                website VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                in_maintenance BOOLEAN NOT NULL DEFAULT 0
            )$options",

            "CREATE TABLE user (
                id $id,
                organization_id $fk NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                roles $longtext NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                phone VARCHAR(30) NULL,
                created_at DATETIME NOT NULL,
                connected_at DATETIME NULL,
                deactivated_at DATETIME NULL,
                is_activated BOOLEAN NOT NULL DEFAULT 1,
                FOREIGN KEY (organization_id) REFERENCES organization (id)
            )$options",

            "CREATE TABLE email_subscription (
                id $id,
                email VARCHAR(255) NOT NULL UNIQUE,
                news_subscribed BOOLEAN NOT NULL DEFAULT 0,
                janaza_subscribed BOOLEAN NOT NULL DEFAULT 0,
                digest_subscribed BOOLEAN NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                deactivated_at DATETIME NULL,
                is_activated BOOLEAN NOT NULL DEFAULT 1
            )$options",

            "CREATE TABLE push_subscription (
                id $id,
                endpoint_hash VARCHAR(64) NOT NULL UNIQUE,
                endpoint TEXT NOT NULL,
                p256dh VARCHAR(255) NOT NULL,
                auth VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                failed_at DATETIME NULL,
                fail_count SMALLINT NOT NULL DEFAULT 0,
                is_activated BOOLEAN NOT NULL DEFAULT 1
            )$options",

            "CREATE TABLE token (
                id $id,
                user_id $fk NULL,
                email_subscription_id $fk NULL,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                type VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL,
                expired_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES user (id),
                FOREIGN KEY (email_subscription_id) REFERENCES email_subscription (id)
            )$options",

            "CREATE TABLE publication (
                id $id,
                user_id $fk NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL,
                published_at DATETIME NULL,
                is_published BOOLEAN NOT NULL DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES user (id)
            )$options",

            "CREATE TABLE media (
                id $id,
                publication_id $fk NOT NULL,
                file VARCHAR(255) NOT NULL,
                mime VARCHAR(100) NOT NULL,
                size INT NOT NULL,
                FOREIGN KEY (publication_id) REFERENCES publication (id)
            )$options",

            "CREATE TABLE news (
                id $id,
                publication_id $fk NOT NULL,
                content TEXT NOT NULL,
                digest_sent_at DATETIME NULL,
                digest_included BOOLEAN NOT NULL DEFAULT 0,
                FOREIGN KEY (publication_id) REFERENCES publication (id)
            )$options",

            "CREATE TABLE category (
                id $id,
                slug VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL
            )$options",

            "CREATE TABLE article (
                id $id,
                publication_id $fk NOT NULL,
                category_id $fk NOT NULL,
                content $mediumtext NOT NULL,
                validated_at DATETIME NULL,
                digest_sent_at DATETIME NULL,
                digest_included BOOLEAN NOT NULL DEFAULT 0,
                FOREIGN KEY (publication_id) REFERENCES publication (id),
                FOREIGN KEY (category_id) REFERENCES category (id)
            )$options",

            "CREATE TABLE janaza (
                id $id,
                publication_id $fk NOT NULL,
                content VARCHAR(255) NOT NULL,
                location VARCHAR(255) NOT NULL,
                salat_date DATETIME NOT NULL,
                cancelled_at DATETIME NULL,
                is_archived BOOLEAN NOT NULL DEFAULT 0,
                FOREIGN KEY (publication_id) REFERENCES publication (id)
            )$options",

            'CREATE INDEX user_organization_id ON user (organization_id)',
            'CREATE INDEX token_user_id ON token (user_id)',
            'CREATE INDEX token_email_subscription_id ON token (email_subscription_id)',
            'CREATE INDEX publication_user_id ON publication (user_id)',
            'CREATE INDEX publication_published_at ON publication (published_at)',
            'CREATE INDEX media_publication_id ON media (publication_id)',
            'CREATE INDEX news_publication_id ON news (publication_id)',
            'CREATE INDEX article_publication_id ON article (publication_id)',
            'CREATE INDEX article_category_id ON article (category_id)',
            'CREATE INDEX janaza_publication_id ON janaza (publication_id)',
        ];
    }

    /** @return list<string> */
    public function down(string $driver): array
    {
        return array_map(
            fn (string $table): string => 'DROP TABLE ' . $table,
            ['janaza', 'article', 'category', 'news', 'media', 'publication', 'token', 'push_subscription', 'email_subscription', 'user', 'organization', 'module', 'migration', 'config'],
        );
    }
};
