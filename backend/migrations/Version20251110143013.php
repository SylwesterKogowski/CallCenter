<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110143013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates worker authorization tables (worker_category_assignments, worker_roles).';
    }

    public function up(Schema $schema): void
    {
        $this->createWorkerCategoryAssignmentsTable($schema);
        $this->createWorkerRolesTable($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('worker_category_assignments')) {
            $schema->dropTable('worker_category_assignments');
        }

        if ($schema->hasTable('worker_roles')) {
            $schema->dropTable('worker_roles');
        }
    }

    private function createWorkerCategoryAssignmentsTable(Schema $schema): void
    {
        if ($schema->hasTable('worker_category_assignments')) {
            return;
        }

        $table = $schema->createTable('worker_category_assignments');
        $table->addColumn('worker_id', 'guid');
        $table->addColumn('category_id', 'guid');
        $table->addColumn('assigned_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('assigned_by_id', 'guid', ['notnull' => false]);

        $table->setPrimaryKey(['worker_id', 'category_id']);
        $table->addIndex(['worker_id'], 'idx_worker_id');
        $table->addIndex(['category_id'], 'idx_category_id');

        if ($schema->hasTable('workers')) {
            $table->addForeignKeyConstraint(
                'workers',
                ['worker_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_worker_category_worker',
            );
            $table->addForeignKeyConstraint(
                'workers',
                ['assigned_by_id'],
                ['id'],
                ['onDelete' => 'SET NULL'],
                'fk_worker_category_assigned_by',
            );
        }

        if ($schema->hasTable('ticket_categories')) {
            $table->addForeignKeyConstraint(
                'ticket_categories',
                ['category_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_worker_category_category',
            );
        }
    }

    private function createWorkerRolesTable(Schema $schema): void
    {
        if ($schema->hasTable('worker_roles')) {
            return;
        }

        $table = $schema->createTable('worker_roles');
        $table->addColumn('id', 'guid');
        $table->addColumn('worker_id', 'guid');
        $table->addColumn('is_manager', 'boolean', ['default' => false]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['worker_id'], 'unique_worker_role');

        if ($schema->hasTable('workers')) {
            $table->addForeignKeyConstraint(
                'workers',
                ['worker_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_worker_role_worker',
            );
        }
    }
}
