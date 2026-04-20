<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420185718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Only acceptor_id is missing; accepted_at and completed_at already exist in DB
        $this->addSql('ALTER TABLE p2p_contract ADD acceptor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p2p_contract ADD CONSTRAINT FK_F89649492675F8C FOREIGN KEY (acceptor_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE p2p_contract DROP FOREIGN KEY FK_F896494961220EA6');
        $this->addSql('ALTER TABLE p2p_contract DROP FOREIGN KEY FK_F89649492675F8C');
        $this->addSql('DROP INDEX IDX_F896494961220EA6 ON p2p_contract');
        $this->addSql('DROP INDEX IDX_F89649492675F8C ON p2p_contract');
        $this->addSql('ALTER TABLE p2p_contract DROP acceptor_id, DROP accepted_at, DROP completed_at');
        $this->addSql('ALTER TABLE `orders` DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('DROP INDEX IDX_E52FFDEEA76ED395 ON `orders`');
        $this->addSql('ALTER TABLE portfolio DROP FOREIGN KEY FK_A9ED1062A76ED395');
        $this->addSql('DROP INDEX UNIQ_A9ED1062A76ED395 ON portfolio');
        $this->addSql('ALTER TABLE user_reputation DROP FOREIGN KEY FK_6D70BCB1A76ED395');
        $this->addSql('DROP INDEX UNIQ_6D70BCB1A76ED395 ON user_reputation');
    }
}
