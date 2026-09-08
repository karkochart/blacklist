<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908174527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry ADD source_reference VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_752724CBC63DDADD ON blacklist_entry (source_reference)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_752724CBC63DDADD ON blacklist_entry');
        $this->addSql('ALTER TABLE blacklist_entry DROP source_reference');
    }
}
