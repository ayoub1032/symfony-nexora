<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406125701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE p2p_contract (id INT AUTO_INCREMENT NOT NULL, creator_id INT NOT NULL, quantity INT NOT NULL, price_per_unit DOUBLE PRECISION NOT NULL, contract_type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, accepted_by INT DEFAULT NULL, created_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, asset_id INT NOT NULL, INDEX IDX_F89649495DA1941 (asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_reputation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, completed_contracts INT NOT NULL, canceled_contracts INT NOT NULL, total_score INT NOT NULL, rating_count INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE p2p_contract ADD CONSTRAINT FK_F89649495DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE p2p_contract DROP FOREIGN KEY FK_F89649495DA1941');
        $this->addSql('DROP TABLE p2p_contract');
        $this->addSql('DROP TABLE user_reputation');
    }
}
