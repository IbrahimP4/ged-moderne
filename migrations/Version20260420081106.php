<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420081106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE company_stamp (id INTEGER NOT NULL, data_url CLOB NOT NULL, updated_at DATETIME NOT NULL, updated_by CHAR(36) DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_12BF3D6D16FE72E1 FOREIGN KEY (updated_by) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_12BF3D6D16FE72E1 ON company_stamp (updated_by)');
        $this->addSql('CREATE TABLE profile_signatures (data_url CLOB NOT NULL, type VARCHAR(20) NOT NULL, updated_at DATETIME NOT NULL, user_id CHAR(36) NOT NULL, PRIMARY KEY (user_id), CONSTRAINT FK_DD100151A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE company_stamp');
        $this->addSql('DROP TABLE profile_signatures');
    }
}
