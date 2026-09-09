<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909145447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry ADD reporter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE blacklist_entry ADD CONSTRAINT FK_752724CBE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_752724CBE1CFE6F5 ON blacklist_entry (reporter_id)');
        $this->addSql('ALTER TABLE driver_history_entry ADD reporter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE driver_history_entry ADD CONSTRAINT FK_5FBE199AE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_5FBE199AE1CFE6F5 ON driver_history_entry (reporter_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry DROP FOREIGN KEY FK_752724CBE1CFE6F5');
        $this->addSql('DROP INDEX IDX_752724CBE1CFE6F5 ON blacklist_entry');
        $this->addSql('ALTER TABLE blacklist_entry DROP reporter_id');
        $this->addSql('ALTER TABLE driver_history_entry DROP FOREIGN KEY FK_5FBE199AE1CFE6F5');
        $this->addSql('DROP INDEX IDX_5FBE199AE1CFE6F5 ON driver_history_entry');
        $this->addSql('ALTER TABLE driver_history_entry DROP reporter_id');
    }
}
