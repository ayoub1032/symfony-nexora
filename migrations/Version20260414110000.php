<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password reset PIN fields to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD reset_pin_code VARCHAR(255) DEFAULT NULL, ADD reset_pin_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD reset_pin_requested_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP reset_pin_code, DROP reset_pin_expires_at, DROP reset_pin_requested_at');
    }
}
