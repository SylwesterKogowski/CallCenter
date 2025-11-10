<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110205748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates worker_schedule table that stores planned ticket assignments for workers.';
    }

    public function up(Schema $schema): void
    {
        $this->createWorkerScheduleTable($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('worker_schedule')) {
            $schema->dropTable('worker_schedule');
        }
    }

    private function createWorkerScheduleTable(Schema $schema): void
    {
        if ($schema->hasTable('worker_schedule')) {
            return;
        }

        $table = $schema->createTable('worker_schedule');
        $table->addColumn('id', 'guid');
        $table->addColumn('worker_id', 'guid');
        $table->addColumn('ticket_id', 'guid');
        $table->addColumn('scheduled_date', 'date_immutable');
        $table->addColumn('assigned_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('assigned_by_id', 'guid', ['notnull' => false]);
        $table->addColumn('is_auto_assigned', 'boolean', ['default' => false]);
        $table->addColumn('priority', 'integer', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['worker_id', 'ticket_id', 'scheduled_date'], 'unique_worker_ticket_date');
        $table->addIndex(['worker_id'], 'idx_worker_id');
        $table->addIndex(['ticket_id'], 'idx_ticket_id');
        $table->addIndex(['scheduled_date'], 'idx_scheduled_date');
        $table->addIndex(['worker_id', 'scheduled_date'], 'idx_worker_date');
        $table->addIndex(['ticket_id', 'scheduled_date'], 'idx_ticket_date');
        $table->addIndex(['is_auto_assigned'], 'idx_auto_assigned');
        $table->addIndex(['priority'], 'idx_priority');

        $table->addForeignKeyConstraint(
            'workers',
            ['worker_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_worker_schedule_worker',
        );

        $table->addForeignKeyConstraint(
            'tickets',
            ['ticket_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_worker_schedule_ticket',
        );

        $table->addForeignKeyConstraint(
            'workers',
            ['assigned_by_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'fk_worker_schedule_assigned_by',
        );
    }
}
