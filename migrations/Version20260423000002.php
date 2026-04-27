<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corbeille, favoris et commentaires : deleted_at sur documents, tables document_favorites et document_comments';
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SqlitePlatform;

        if ($isSqlite) {
            $this->addSql('ALTER TABLE documents ADD COLUMN deleted_at DATETIME DEFAULT NULL');

            $this->addSql('CREATE TABLE document_favorites (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                document_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_FAV_DOC  FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
                CONSTRAINT FK_FAV_USER FOREIGN KEY (user_id)     REFERENCES users (id)     ON DELETE CASCADE
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_doc_user_fav ON document_favorites (document_id, user_id)');
            $this->addSql('CREATE INDEX IDX_FAV_DOC  ON document_favorites (document_id)');
            $this->addSql('CREATE INDEX IDX_FAV_USER ON document_favorites (user_id)');

            $this->addSql('CREATE TABLE document_comments (
                id CHAR(36) NOT NULL,
                document_id CHAR(36) NOT NULL,
                author_id CHAR(36) NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_CMT_DOC    FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
                CONSTRAINT FK_CMT_AUTHOR FOREIGN KEY (author_id)   REFERENCES users (id)     ON DELETE CASCADE
            )');
            $this->addSql('CREATE INDEX IDX_CMT_DOC ON document_comments (document_id)');
        } else {
            $this->addSql('ALTER TABLE documents ADD deleted_at DATETIME DEFAULT NULL');

            $this->addSql('CREATE TABLE document_favorites (
                id INT AUTO_INCREMENT NOT NULL,
                document_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_FAV_DOC  FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
                CONSTRAINT FK_FAV_USER FOREIGN KEY (user_id)     REFERENCES users (id)     ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE UNIQUE INDEX uniq_doc_user_fav ON document_favorites (document_id, user_id)');
            $this->addSql('CREATE INDEX IDX_FAV_DOC  ON document_favorites (document_id)');
            $this->addSql('CREATE INDEX IDX_FAV_USER ON document_favorites (user_id)');

            $this->addSql('CREATE TABLE document_comments (
                id CHAR(36) NOT NULL,
                document_id CHAR(36) NOT NULL,
                author_id CHAR(36) NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_CMT_DOC    FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
                CONSTRAINT FK_CMT_AUTHOR FOREIGN KEY (author_id)   REFERENCES users (id)     ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE INDEX IDX_CMT_DOC ON document_comments (document_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_comments');
        $this->addSql('DROP TABLE document_favorites');

        $isSqlite = $this->connection->getDatabasePlatform() instanceof SqlitePlatform;
        if (!$isSqlite) {
            $this->addSql('ALTER TABLE documents DROP COLUMN deleted_at');
        }
    }
}
