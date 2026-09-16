<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916091849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, telegram_user_id INT NOT NULL, granted_by_id INT DEFAULT NULL, INDEX IDX_A3C664D3FC28B263 (telegram_user_id), INDEX IDX_A3C664D33151C11F (granted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE telegram_user (id INT AUTO_INCREMENT NOT NULL, telegram_id BIGINT NOT NULL, username VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F180F059CC0B3066 (telegram_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3FC28B263 FOREIGN KEY (telegram_user_id) REFERENCES telegram_user (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D33151C11F FOREIGN KEY (granted_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3FC28B263');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D33151C11F');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE telegram_user');
    }
}
