<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename posts(user_id) index to the hashed name Doctrine expects with underscore_number_aware naming.
 */
final class Version20260515100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename posts(user_id) index to match Doctrine naming strategy.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed on PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM pg_class c
                    INNER JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = 'public'
                      AND c.relkind = 'i'
                      AND c.relname = 'idx_posts_user_id'
                ) THEN
                    EXECUTE 'ALTER INDEX idx_posts_user_id RENAME TO idx_885dbafaa76ed395';
                END IF;
            END $$;
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed on PostgreSQL.'
        );

        $this->throwIrreversibleMigrationException('Restoring the previous index name is not implemented.');
    }
}
