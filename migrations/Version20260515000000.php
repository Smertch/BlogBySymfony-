<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * DBAL 3 lists sequences from information_schema.sequences only.
 * PostgreSQL IDENTITY columns use internal sequences that do not appear there, which breaks
 * doctrine:schema:validate. Replace IDENTITY with an OWNED BY sequence listed in
 * information_schema and keep DEFAULT nextval() so PK ids work with ORM IDENTITY mapping
 * (INSERT omits id; PostgreSQL applies the default).
 */
final class Version20260515000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace BIGINT IDENTITY PKs with OWNED BY sequences + DEFAULT nextval (DBAL introspection + ORM IDENTITY inserts).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed on PostgreSQL.'
        );

        $tables = [
            'site_translation',
            'comments',
            'post_likes',
            'posts',
            'users',
        ];

        foreach ($tables as $table) {
            $sequence = $table.'_id_seq';
            $this->addSql(\sprintf('ALTER TABLE %s ALTER COLUMN id DROP IDENTITY IF EXISTS', $table));
            $this->addSql(\sprintf('CREATE SEQUENCE %s OWNED BY %s.id', $sequence, $table));
            $this->addSql(\sprintf(
                "SELECT setval('%s', COALESCE((SELECT MAX(id) FROM %s), 1))",
                $sequence,
                $table
            ));
            $this->addSql(\sprintf(
                "ALTER TABLE %s ALTER COLUMN id SET DEFAULT nextval('%s')",
                $table,
                $sequence
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed on PostgreSQL.'
        );

        $this->throwIrreversibleMigrationException(
            'Restore GENERATED AS IDENTITY PKs manually if needed; down() is not implemented.'
        );
    }
}
