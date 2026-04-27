<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420074717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE signature_requests (id CHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, message CLOB DEFAULT NULL, comment CLOB DEFAULT NULL, requested_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, document_id CHAR(36) NOT NULL, requester_id CHAR(36) NOT NULL, signer_id CHAR(36) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_C8E950E0C33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C8E950E0ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C8E950E09588C067 FOREIGN KEY (signer_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C8E950E0C33F7837 ON signature_requests (document_id)');
        $this->addSql('CREATE INDEX IDX_C8E950E0ED442CF4 ON signature_requests (requester_id)');
        $this->addSql('CREATE INDEX IDX_C8E950E09588C067 ON signature_requests (signer_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE signature_requests');
    }
}
