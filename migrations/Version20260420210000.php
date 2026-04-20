<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add face_image column to users table for Face++ recognition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD face_image LONGTEXT DEFAULT NULL COMMENT "Base64-encoded face photo for Face++ recognition"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN face_image');
    }
}
