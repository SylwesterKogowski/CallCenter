<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251110130526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates workers table for the Authentication module.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('workers')) {
            return;
        }

        $table = $schema->createTable('workers');
        $table->addColumn('id', 'guid');
        $table->addColumn('login', 'string', ['length' => 255]);
        $table->addColumn('password_hash', 'string', ['length' => 255]);
        $table->addColumn('is_manager', 'boolean', ['default' => false]);
        $table->addColumn('created_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['login'], 'unique_login');
        $table->addIndex(['created_at'], 'idx_created_at');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('workers')) {
            $schema->dropTable('workers');
        }
    }
}
