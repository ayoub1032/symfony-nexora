<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404113041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, message VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, wallet_id INT NOT NULL, INDEX IDX_FD06F647712520F3 (wallet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647712520F3 FOREIGN KEY (wallet_id) REFERENCES wallets (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647712520F3');
        $this->addSql('DROP TABLE activity_log');
    }
}
