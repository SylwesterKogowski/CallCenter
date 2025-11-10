<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110185803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates clients table for storing identified and anonymous callers.';
    }

    public function up(Schema $schema): void
    {
        $this->createClientsTable($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('clients')) {
            $schema->dropTable('clients');
        }
    }

    private function createClientsTable(Schema $schema): void
    {
        if ($schema->hasTable('clients')) {
            return;
        }

        $table = $schema->createTable('clients');
        $table->addColumn('id', 'guid');
        $table->addColumn('email', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('phone', 'string', ['length' => 50, 'notnull' => false]);
        $table->addColumn('first_name', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('last_name', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('is_anonymous', 'boolean', ['default' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('identified_at', 'datetime_immutable', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'unique_client_email');
        $table->addIndex(['email'], 'idx_client_email');
        $table->addIndex(['phone'], 'idx_client_phone');
        $table->addIndex(['is_anonymous'], 'idx_client_is_anonymous');
        $table->addIndex(['created_at'], 'idx_client_created_at');
    }
}
