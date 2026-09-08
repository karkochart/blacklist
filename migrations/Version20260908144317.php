<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908144317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blacklist_entry (id INT AUTO_INCREMENT NOT NULL, reported_by VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, occurred_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, is_active TINYINT NOT NULL, driver_id INT NOT NULL, INDEX IDX_752724CBC3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE driver_history_entry (id INT AUTO_INCREMENT NOT NULL, reported_by VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, occurred_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, driver_id INT NOT NULL, INDEX IDX_5FBE199AC3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blacklist_entry ADD CONSTRAINT FK_752724CBC3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE driver_history_entry ADD CONSTRAINT FK_5FBE199AC3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blacklist_entry DROP FOREIGN KEY FK_752724CBC3423909');
        $this->addSql('ALTER TABLE driver_history_entry DROP FOREIGN KEY FK_5FBE199AC3423909');
        $this->addSql('DROP TABLE blacklist_entry');
        $this->addSql('DROP TABLE driver_history_entry');
    }
}
