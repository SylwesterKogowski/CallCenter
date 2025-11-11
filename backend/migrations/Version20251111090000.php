<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251111090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates table for storing manager auto-assignment settings.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('manager_auto_assignment_settings')) {
            return;
        }

        $table = $schema->createTable('manager_auto_assignment_settings');
        $table->addColumn('manager_id', 'guid');
        $table->addColumn('enabled', 'boolean', ['default' => false]);
        $table->addColumn('consider_efficiency', 'boolean', ['default' => true]);
        $table->addColumn('consider_availability', 'boolean', ['default' => true]);
        $table->addColumn('max_tickets_per_worker', 'integer', ['default' => 10]);
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->addColumn('last_run', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('tickets_assigned', 'integer', ['default' => 0]);

        $table->setPrimaryKey(['manager_id']);
        $table->addIndex(['updated_at'], 'idx_manager_auto_assignment_settings_updated_at');
        $table->addIndex(['last_run'], 'idx_manager_auto_assignment_settings_last_run');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('manager_auto_assignment_settings')) {
            $schema->dropTable('manager_auto_assignment_settings');
        }
    }
}
