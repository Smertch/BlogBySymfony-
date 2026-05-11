<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Repair databases that ran Version20260515100000 before it stopped dropping PK defaults.
 * ORM IDENTITY mapping omits id from INSERT; the column must keep DEFAULT nextval().
 */
final class Version20260515200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure BIGINT PK columns use DEFAULT nextval (required for ORM IDENTITY inserts).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed on PostgreSQL.'
        );

        foreach (
            [
                'site_translation' => 'site_translation_id_seq',
                'comments' => 'comments_id_seq',
                'post_likes' => 'post_likes_id_seq',
                'posts' => 'posts_id_seq',
                'users' => 'users_id_seq',
            ] as $table => $sequence
        ) {
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
            'Removing PK defaults would break ORM IDENTITY inserts; not implemented.'
        );
    }
}
