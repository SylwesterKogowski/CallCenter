<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110202230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates worker availability table to track workers availability slots.';
    }

    public function up(Schema $schema): void
    {
        $this->createWorkerAvailabilityTable($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('worker_availability')) {
            $schema->dropTable('worker_availability');
        }
    }

    private function createWorkerAvailabilityTable(Schema $schema): void
    {
        if ($schema->hasTable('worker_availability')) {
            return;
        }

        $table = $schema->createTable('worker_availability');
        $table->addColumn('id', 'guid');
        $table->addColumn('worker_id', 'guid');
        $table->addColumn('start_datetime', 'datetime_immutable');
        $table->addColumn('end_datetime', 'datetime_immutable');
        $table->addColumn('created_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['worker_id'], 'idx_worker_id');
        $table->addIndex(['start_datetime'], 'idx_start_datetime');
        $table->addIndex(['end_datetime'], 'idx_end_datetime');
        $table->addIndex(['worker_id', 'start_datetime', 'end_datetime'], 'idx_worker_datetime_range');
        $table->addIndex(['start_datetime', 'end_datetime'], 'idx_date_range');

        $table->addForeignKeyConstraint(
            'workers',
            ['worker_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_worker_availability_worker',
        );
    }
}
