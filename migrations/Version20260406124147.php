<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406124147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, symbol VARCHAR(50) NOT NULL, value DOUBLE PRECISION NOT NULL, type VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `orders` (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, quantity INT NOT NULL, price DOUBLE PRECISION NOT NULL, type VARCHAR(20) NOT NULL, asset_id INT NOT NULL, INDEX IDX_E52FFDEE5DA1941 (asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE portfolio (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, total_value DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE portfolio_asset (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, avg_price DOUBLE PRECISION NOT NULL, portfolio_id INT NOT NULL, asset_id INT NOT NULL, INDEX IDX_5FF77019B96B5643 (portfolio_id), INDEX IDX_5FF770195DA1941 (asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `orders` ADD CONSTRAINT FK_E52FFDEE5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE portfolio_asset ADD CONSTRAINT FK_5FF77019B96B5643 FOREIGN KEY (portfolio_id) REFERENCES portfolio (id)');
        $this->addSql('ALTER TABLE portfolio_asset ADD CONSTRAINT FK_5FF770195DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `orders` DROP FOREIGN KEY FK_E52FFDEE5DA1941');
        $this->addSql('ALTER TABLE portfolio_asset DROP FOREIGN KEY FK_5FF77019B96B5643');
        $this->addSql('ALTER TABLE portfolio_asset DROP FOREIGN KEY FK_5FF770195DA1941');
        $this->addSql('DROP TABLE asset');
        $this->addSql('DROP TABLE `orders`');
        $this->addSql('DROP TABLE portfolio');
        $this->addSql('DROP TABLE portfolio_asset');
    }
}
