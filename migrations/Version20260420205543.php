<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420205543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add google_id column to users for Google OAuth linking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD google_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN google_id');
    }
}
