<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permissions par dossier : colonne restricted sur folders + table folder_permissions';
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SqlitePlatform;

        if ($isSqlite) {
            // SQLite ne supporte pas ALTER TABLE ADD COLUMN avec DEFAULT dans tous les cas
            // On utilise la syntaxe compatible SQLite
            $this->addSql('ALTER TABLE folders ADD COLUMN restricted BOOLEAN NOT NULL DEFAULT 0');
            $this->addSql('CREATE TABLE folder_permissions (
                id CHAR(36) NOT NULL,
                folder_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                granted_by CHAR(36) DEFAULT NULL,
                level VARCHAR(10) NOT NULL,
                granted_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_FP_FOLDER FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE,
                CONSTRAINT FK_FP_USER   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
                CONSTRAINT FK_FP_GRANTER FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_folder_user ON folder_permissions (folder_id, user_id)');
            $this->addSql('CREATE INDEX IDX_FP_FOLDER ON folder_permissions (folder_id)');
            $this->addSql('CREATE INDEX IDX_FP_USER   ON folder_permissions (user_id)');
        } else {
            $this->addSql('ALTER TABLE folders ADD restricted TINYINT(1) NOT NULL DEFAULT 0');
            $this->addSql('CREATE TABLE folder_permissions (
                id CHAR(36) NOT NULL,
                folder_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                granted_by CHAR(36) DEFAULT NULL,
                level VARCHAR(10) NOT NULL,
                granted_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_FP_FOLDER  FOREIGN KEY (folder_id)  REFERENCES folders (id) ON DELETE CASCADE,
                CONSTRAINT FK_FP_USER    FOREIGN KEY (user_id)    REFERENCES users (id)   ON DELETE CASCADE,
                CONSTRAINT FK_FP_GRANTER FOREIGN KEY (granted_by) REFERENCES users (id)   ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE UNIQUE INDEX uniq_folder_user ON folder_permissions (folder_id, user_id)');
            $this->addSql('CREATE INDEX IDX_FP_FOLDER ON folder_permissions (folder_id)');
            $this->addSql('CREATE INDEX IDX_FP_USER   ON folder_permissions (user_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE folder_permissions');
        // SQLite ne supporte pas DROP COLUMN — à gérer manuellement si besoin
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SqlitePlatform;
        if (!$isSqlite) {
            $this->addSql('ALTER TABLE folders DROP COLUMN restricted');
        }
    }
}
