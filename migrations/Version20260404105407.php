<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404105407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wallet_goals (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, target_amount NUMERIC(15, 2) NOT NULL, deadline DATE NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, wallet_id INT NOT NULL, INDEX IDX_B7237855712520F3 (wallet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wallet_goals ADD CONSTRAINT FK_B7237855712520F3 FOREIGN KEY (wallet_id) REFERENCES wallets (id)');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C712520F3');
        $this->addSql('DROP TABLE transactions');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, amount NUMERIC(15, 2) NOT NULL, date DATETIME NOT NULL, wallet_id INT NOT NULL, INDEX IDX_EAA81A4C712520F3 (wallet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C712520F3 FOREIGN KEY (wallet_id) REFERENCES wallets (id)');
        $this->addSql('ALTER TABLE wallet_goals DROP FOREIGN KEY FK_B7237855712520F3');
        $this->addSql('DROP TABLE wallet_goals');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
