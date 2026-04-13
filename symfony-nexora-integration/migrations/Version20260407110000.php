<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users table, link wallets to users, and seed login accounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(100) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_users_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql('ALTER TABLE wallets ADD user_id INT DEFAULT NULL, ADD UNIQUE INDEX UNIQ_95D17D3BA76ED395 (user_id)');
        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_95D17D3BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql("INSERT INTO users (id, email, full_name, roles, password, created_at) VALUES (1, 'admin@nexora.tn', 'Professional Admin', '[\"ROLE_ADMIN\"]', '$2y$10$TDRQLhQCiFfyNvPEoy.x1uxikiA/lCcZhOX1OopHLUsbmiaFrWNoe', NOW())");
        $this->addSql("INSERT INTO users (id, email, full_name, roles, password, created_at) VALUES (2, 'ayoub1@nexora.tn', 'Ayoub Investor', '[\"ROLE_USER\"]', '$2y$10$sgOX0m6kgZ8a6f/N6jl4husPfwNJ57yuPd9LfaEUVErREPT54E41y', NOW())");
        $this->addSql("INSERT INTO users (id, email, full_name, roles, password, created_at) VALUES (3, 'test1@nexora.tn', 'Test Investor', '[\"ROLE_USER\"]', '$2y$10$fPTODd/c4OmOwOOYCFWjae81kHloVhhUO0qBZRhXc3JqcbUPSHVju', NOW())");

        $this->addSql('UPDATE wallets SET user_id = 2 WHERE id = 8');
        $this->addSql('UPDATE wallets SET user_id = 3 WHERE id = 10');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets DROP FOREIGN KEY FK_95D17D3BA76ED395');
        $this->addSql('DROP INDEX UNIQ_95D17D3BA76ED395 ON wallets');
        $this->addSql('ALTER TABLE wallets DROP user_id');
        $this->addSql('DROP TABLE users');
    }
}
