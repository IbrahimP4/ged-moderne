<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427153712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__company_stamp AS SELECT id, data_url, updated_at, updated_by FROM company_stamp');
        $this->addSql('DROP TABLE company_stamp');
        $this->addSql('CREATE TABLE company_stamp (id INTEGER NOT NULL, data_url CLOB NOT NULL, updated_at DATETIME NOT NULL, updated_by CHAR(36) DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_12BF3D6D16FE72E1 FOREIGN KEY (updated_by) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO company_stamp (id, data_url, updated_at, updated_by) SELECT id, data_url, updated_at, updated_by FROM __temp__company_stamp');
        $this->addSql('DROP TABLE __temp__company_stamp');
        $this->addSql('CREATE INDEX IDX_12BF3D6D16FE72E1 ON company_stamp (updated_by)');
        $this->addSql('ALTER TABLE document_versions ADD COLUMN content_text CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__company_stamp AS SELECT id, data_url, updated_at, updated_by FROM company_stamp');
        $this->addSql('DROP TABLE company_stamp');
        $this->addSql('CREATE TABLE company_stamp (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, data_url CLOB NOT NULL, updated_at DATETIME NOT NULL, updated_by CHAR(36) DEFAULT NULL, CONSTRAINT FK_12BF3D6D16FE72E1 FOREIGN KEY (updated_by) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO company_stamp (id, data_url, updated_at, updated_by) SELECT id, data_url, updated_at, updated_by FROM __temp__company_stamp');
        $this->addSql('DROP TABLE __temp__company_stamp');
        $this->addSql('CREATE INDEX IDX_12BF3D6D16FE72E1 ON company_stamp (updated_by)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__document_versions AS SELECT id, version_number, mime_type, file_size_bytes, original_filename, storage_path, comment, created_at, document_id, uploaded_by FROM document_versions');
        $this->addSql('DROP TABLE document_versions');
        $this->addSql('CREATE TABLE document_versions (id CHAR(36) NOT NULL, version_number SMALLINT UNSIGNED NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size_bytes INTEGER UNSIGNED NOT NULL, original_filename VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, comment CLOB DEFAULT NULL, created_at DATETIME NOT NULL, document_id CHAR(36) NOT NULL, uploaded_by CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_961DB18BC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_961DB18BE3E73126 FOREIGN KEY (uploaded_by) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO document_versions (id, version_number, mime_type, file_size_bytes, original_filename, storage_path, comment, created_at, document_id, uploaded_by) SELECT id, version_number, mime_type, file_size_bytes, original_filename, storage_path, comment, created_at, document_id, uploaded_by FROM __temp__document_versions');
        $this->addSql('DROP TABLE __temp__document_versions');
        $this->addSql('CREATE INDEX IDX_961DB18BC33F7837 ON document_versions (document_id)');
        $this->addSql('CREATE INDEX IDX_961DB18BE3E73126 ON document_versions (uploaded_by)');
        $this->addSql('CREATE UNIQUE INDEX uq_document_version ON document_versions (document_id, version_number)');
    }
}
