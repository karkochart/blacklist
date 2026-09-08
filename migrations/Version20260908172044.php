<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908172044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry CHANGE reported_by reported_by VARCHAR(255) DEFAULT NULL, CHANGE text text LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE driver_history_entry CHANGE reported_by reported_by VARCHAR(255) DEFAULT NULL, CHANGE text text LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry CHANGE reported_by reported_by VARCHAR(255) NOT NULL, CHANGE text text LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE driver_history_entry CHANGE reported_by reported_by VARCHAR(255) NOT NULL, CHANGE text text LONGTEXT NOT NULL');
    }
}
