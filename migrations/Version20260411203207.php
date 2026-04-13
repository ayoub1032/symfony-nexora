<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411203207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, icon VARCHAR(50) DEFAULT NULL, color VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, wallet_id INT NOT NULL, category_id INT DEFAULT NULL, INDEX IDX_EAA81A4C712520F3 (wallet_id), INDEX IDX_EAA81A4C12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C712520F3 FOREIGN KEY (wallet_id) REFERENCES wallets (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX uniq_users_email ON users');
        $this->addSql('ALTER TABLE users CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE wallets DROP FOREIGN KEY FK_95D17D3BA76ED395');
        $this->addSql('DROP INDEX uniq_95d17d3ba76ed395 ON wallets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_967AAA6CA76ED395 ON wallets (user_id)');
        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_95D17D3BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C712520F3');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C12469DE2');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('ALTER TABLE users CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
        $this->addSql('ALTER TABLE wallets DROP FOREIGN KEY FK_967AAA6CA76ED395');
        $this->addSql('DROP INDEX uniq_967aaa6ca76ed395 ON wallets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_95D17D3BA76ED395 ON wallets (user_id)');
        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_967AAA6CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }
}
