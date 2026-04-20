<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420171855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEEA76ED395 ON orders (user_id)');
        $this->addSql('ALTER TABLE p2p_contract CHANGE accepted_by acceptor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p2p_contract ADD CONSTRAINT FK_F896494961220EA6 FOREIGN KEY (creator_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE p2p_contract ADD CONSTRAINT FK_F89649492675F8C FOREIGN KEY (acceptor_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_F896494961220EA6 ON p2p_contract (creator_id)');
        $this->addSql('CREATE INDEX IDX_F89649492675F8C ON p2p_contract (acceptor_id)');
        $this->addSql('ALTER TABLE portfolio ADD CONSTRAINT FK_A9ED1062A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A9ED1062A76ED395 ON portfolio (user_id)');
        $this->addSql('ALTER TABLE user_reputation ADD CONSTRAINT FK_6D70BCB1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D70BCB1A76ED395 ON user_reputation (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `orders` DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('DROP INDEX IDX_E52FFDEEA76ED395 ON `orders`');
        $this->addSql('ALTER TABLE p2p_contract DROP FOREIGN KEY FK_F896494961220EA6');
        $this->addSql('ALTER TABLE p2p_contract DROP FOREIGN KEY FK_F89649492675F8C');
        $this->addSql('DROP INDEX IDX_F896494961220EA6 ON p2p_contract');
        $this->addSql('DROP INDEX IDX_F89649492675F8C ON p2p_contract');
        $this->addSql('ALTER TABLE p2p_contract CHANGE acceptor_id accepted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE portfolio DROP FOREIGN KEY FK_A9ED1062A76ED395');
        $this->addSql('DROP INDEX UNIQ_A9ED1062A76ED395 ON portfolio');
        $this->addSql('ALTER TABLE user_reputation DROP FOREIGN KEY FK_6D70BCB1A76ED395');
        $this->addSql('DROP INDEX UNIQ_6D70BCB1A76ED395 ON user_reputation');
    }
}
