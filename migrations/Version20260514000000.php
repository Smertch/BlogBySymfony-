<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.site_language (UI locale, EN/ES/DE/UA).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->isPostgreSql(),
            'Migration can only be executed on PostgreSQL.'
        );

        $this->addSql("ALTER TABLE users ADD COLUMN site_language VARCHAR(3) NOT NULL DEFAULT 'EN'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->isPostgreSql(),
            'Migration can only be executed on PostgreSQL.'
        );

        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS site_language');
    }

    private function isPostgreSql(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
    }
}
