<?php

declare(strict_types=1);

use Yawasla\Database\AbstractMigration;

return new class extends AbstractMigration {
    public function up(): void
    {
        $this->sql('CREATE TABLE config (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            value TEXT NOT NULL,
            created_at DATETIME NOT NULL
        )');
        $this->sql('CREATE TABLE migration (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            module VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE (module, name)
        )');
        $this->sql('CREATE TABLE module (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            namespace VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            deactivated_at DATETIME NULL,
            is_activated BOOLEAN NOT NULL DEFAULT 1
        )');
        $this->sql('CREATE TABLE organization (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
        )');
        $this->sql('CREATE TABLE user (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id INT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            roles LONGTEXT NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(30) NULL,
            created_at DATETIME NOT NULL,
            connected_at DATETIME NULL,
            deactivated_at DATETIME NULL,
            is_activated BOOLEAN NOT NULL DEFAULT 1,
            CONSTRAINT user_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id)
        )');
        $this->sql('CREATE TABLE email_subscription (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            news_subscribed BOOLEAN NOT NULL DEFAULT 0,
            janaza_subscribed BOOLEAN NOT NULL DEFAULT 0,
            digest_subscribed BOOLEAN NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            deactivated_at DATETIME NULL,
            is_activated BOOLEAN NOT NULL DEFAULT 1
        )');
        $this->sql('CREATE TABLE push_subscription (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint_hash VARCHAR(64) NOT NULL UNIQUE,
            endpoint TEXT NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            failed_at DATETIME NULL,
            fail_count SMALLINT NOT NULL DEFAULT 0,
            is_activated BOOLEAN NOT NULL DEFAULT 1
        )');
        $this->sql('CREATE TABLE token (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            email_subscription_id INT UNSIGNED NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            type VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL,
            expired_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            CONSTRAINT token_user_id_fk FOREIGN KEY (user_id) REFERENCES user (id),
            CONSTRAINT token_email_subscription_id_fk FOREIGN KEY (email_subscription_id) REFERENCES email_subscription (id)
        )');
        $this->sql('CREATE TABLE publication (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL,
            published_at DATETIME NULL,
            is_published BOOLEAN NOT NULL DEFAULT 0,
            CONSTRAINT publication_user_id_fk FOREIGN KEY (user_id) REFERENCES user (id)
        )');
        $this->sql('CREATE TABLE media (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id INT UNSIGNED NOT NULL,
            file VARCHAR(255) NOT NULL,
            mime VARCHAR(100) NOT NULL,
            size INT NOT NULL,
            CONSTRAINT media_publication_id_fk FOREIGN KEY (publication_id) REFERENCES publication (id)
        )');
        $this->sql('CREATE TABLE news (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id INT UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            digest_sent_at DATETIME NULL,
            digest_included BOOLEAN NOT NULL DEFAULT 0,
            CONSTRAINT news_publication_id_fk FOREIGN KEY (publication_id) REFERENCES publication (id)
        )');
        $this->sql('CREATE TABLE category (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        )');
        $this->sql('CREATE TABLE article (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            content MEDIUMTEXT NOT NULL,
            validated_at DATETIME NULL,
            digest_sent_at DATETIME NULL,
            digest_included BOOLEAN NOT NULL DEFAULT 0,
            CONSTRAINT article_publication_id_fk FOREIGN KEY (publication_id) REFERENCES publication (id),
            CONSTRAINT article_category_id_fk FOREIGN KEY (category_id) REFERENCES category (id)
        )');
        $this->sql('CREATE TABLE janaza (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id INT UNSIGNED NOT NULL,
            content VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            salat_date DATETIME NOT NULL,
            cancelled_at DATETIME NULL,
            is_archived BOOLEAN NOT NULL DEFAULT 0,
            CONSTRAINT janaza_publication_id_fk FOREIGN KEY (publication_id) REFERENCES publication (id)
        )');
        $this->sql('CREATE INDEX user_organization_id ON user (organization_id)');
        $this->sql('CREATE INDEX token_user_id ON token (user_id)');
        $this->sql('CREATE INDEX token_email_subscription_id ON token (email_subscription_id)');
        $this->sql('CREATE INDEX publication_user_id ON publication (user_id)');
        $this->sql('CREATE INDEX publication_published_at ON publication (published_at)');
        $this->sql('CREATE INDEX media_publication_id ON media (publication_id)');
        $this->sql('CREATE INDEX news_publication_id ON news (publication_id)');
        $this->sql('CREATE INDEX article_publication_id ON article (publication_id)');
        $this->sql('CREATE INDEX article_category_id ON article (category_id)');
        $this->sql('CREATE INDEX janaza_publication_id ON janaza (publication_id)');
    }

    public function down(): void
    {
        foreach (['janaza', 'article', 'category', 'news', 'media', 'publication', 'token', 'push_subscription', 'email_subscription', 'user', 'organization', 'module', 'migration', 'config'] as $table) {
            $this->sql('DROP TABLE ' . $table);
        }
    }
};
