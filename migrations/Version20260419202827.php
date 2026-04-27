<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419202827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents ADD COLUMN tags CLOB DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__documents AS SELECT id, title, comment, status, created_at, updated_at, folder_id, owner_id FROM documents');
        $this->addSql('DROP TABLE documents');
        $this->addSql('CREATE TABLE documents (id CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, comment CLOB DEFAULT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, folder_id CHAR(36) NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_A2B07288162CB942 FOREIGN KEY (folder_id) REFERENCES folders (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A2B072887E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO documents (id, title, comment, status, created_at, updated_at, folder_id, owner_id) SELECT id, title, comment, status, created_at, updated_at, folder_id, owner_id FROM __temp__documents');
        $this->addSql('DROP TABLE __temp__documents');
        $this->addSql('CREATE INDEX IDX_A2B07288162CB942 ON documents (folder_id)');
        $this->addSql('CREATE INDEX IDX_A2B072887E3C61F9 ON documents (owner_id)');
    }
}
