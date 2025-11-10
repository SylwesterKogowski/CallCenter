<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110170132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates ticket aggregates tables (tickets, ticket_messages, ticket_notes, ticket_registered_time).';
    }

    public function up(Schema $schema): void
    {
        $this->createTicketsTable($schema);
        $this->createTicketMessagesTable($schema);
        $this->createTicketNotesTable($schema);
        $this->createTicketRegisteredTimeTable($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('ticket_registered_time')) {
            $schema->dropTable('ticket_registered_time');
        }

        if ($schema->hasTable('ticket_notes')) {
            $schema->dropTable('ticket_notes');
        }

        if ($schema->hasTable('ticket_messages')) {
            $schema->dropTable('ticket_messages');
        }

        if ($schema->hasTable('tickets')) {
            $schema->dropTable('tickets');
        }
    }

    private function createTicketsTable(Schema $schema): void
    {
        if ($schema->hasTable('tickets')) {
            return;
        }

        $table = $schema->createTable('tickets');
        $table->addColumn('id', 'guid');
        $table->addColumn('client_id', 'guid');
        $table->addColumn('client_email', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('client_phone', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('client_first_name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('client_last_name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('category_id', 'string', ['length' => 255]);
        $table->addColumn('category_name', 'string', ['length' => 255]);
        $table->addColumn('category_description', 'text', ['notnull' => false]);
        $table->addColumn('category_default_resolution_minutes', 'integer');
        $table->addColumn('title', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 50, 'default' => 'awaiting_response']);
        $table->addColumn('created_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('closed_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('closed_by_id', 'guid', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['client_id'], 'idx_ticket_client_id');
        $table->addIndex(['category_id'], 'idx_ticket_category_id');
        $table->addIndex(['status'], 'idx_ticket_status');
        $table->addIndex(['created_at'], 'idx_ticket_created_at');
        $table->addIndex(['closed_at'], 'idx_ticket_closed_at');

        if ($schema->hasTable('clients')) {
            $table->addForeignKeyConstraint(
                'clients',
                ['client_id'],
                ['id'],
                ['onDelete' => 'RESTRICT'],
                'fk_ticket_client',
            );
        }

        if ($schema->hasTable('ticket_categories')) {
            $table->addForeignKeyConstraint(
                'ticket_categories',
                ['category_id'],
                ['id'],
                ['onDelete' => 'RESTRICT'],
                'fk_ticket_category',
            );
        }

        if ($schema->hasTable('workers')) {
            $table->addForeignKeyConstraint(
                'workers',
                ['closed_by_id'],
                ['id'],
                ['onDelete' => 'SET NULL'],
                'fk_ticket_closed_by',
            );
        }
    }

    private function createTicketMessagesTable(Schema $schema): void
    {
        if ($schema->hasTable('ticket_messages')) {
            return;
        }

        $table = $schema->createTable('ticket_messages');
        $table->addColumn('id', 'guid');
        $table->addColumn('ticket_id', 'guid');
        $table->addColumn('sender_type', 'string', ['length' => 50]);
        $table->addColumn('sender_id', 'guid', ['notnull' => false]);
        $table->addColumn('sender_name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('content', 'text');
        $table->addColumn('created_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('status', 'string', ['length' => 50, 'notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['ticket_id'], 'idx_ticket_message_ticket_id');
        $table->addIndex(['created_at'], 'idx_ticket_message_created_at');

        if ($schema->hasTable('tickets')) {
            $table->addForeignKeyConstraint(
                'tickets',
                ['ticket_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_ticket_message_ticket',
            );
        }
    }

    private function createTicketNotesTable(Schema $schema): void
    {
        if ($schema->hasTable('ticket_notes')) {
            return;
        }

        $table = $schema->createTable('ticket_notes');
        $table->addColumn('id', 'guid');
        $table->addColumn('ticket_id', 'guid');
        $table->addColumn('worker_id', 'guid');
        $table->addColumn('content', 'text');
        $table->addColumn('created_at', 'datetime_immutable', ['default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['ticket_id'], 'idx_ticket_note_ticket_id');
        $table->addIndex(['worker_id'], 'idx_ticket_note_worker_id');
        $table->addIndex(['created_at'], 'idx_ticket_note_created_at');

        if ($schema->hasTable('tickets')) {
            $table->addForeignKeyConstraint(
                'tickets',
                ['ticket_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_ticket_note_ticket',
            );
        }

        if ($schema->hasTable('workers')) {
            $table->addForeignKeyConstraint(
                'workers',
                ['worker_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_ticket_note_worker',
            );
        }
    }

    private function createTicketRegisteredTimeTable(Schema $schema): void
    {
        if ($schema->hasTable('ticket_registered_time')) {
            return;
        }

        $table = $schema->createTable('ticket_registered_time');
        $table->addColumn('id', 'guid');
        $table->addColumn('ticket_id', 'guid');
        $table->addColumn('worker_id', 'guid');
        $table->addColumn('started_at', 'datetime_immutable');
        $table->addColumn('ended_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('duration_minutes', 'integer', ['notnull' => false]);
        $table->addColumn('is_phone_call', 'boolean', ['default' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['ticket_id'], 'idx_ticket_registered_time_ticket_id');
        $table->addIndex(['worker_id'], 'idx_ticket_registered_time_worker_id');
        $table->addIndex(['started_at'], 'idx_ticket_registered_time_started_at');
        $table->addIndex(['ended_at'], 'idx_ticket_registered_time_ended_at');
        $table->addIndex(['ticket_id', 'worker_id', 'ended_at'], 'idx_ticket_registered_time_active');

        if ($schema->hasTable('tickets')) {
            $table->addForeignKeyConstraint(
                'tickets',
                ['ticket_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_ticket_registered_time_ticket',
            );
        }

        if ($schema->hasTable('workers')) {
            $table->addForeignKeyConstraint(
                'workers',
                ['worker_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_ticket_registered_time_worker',
            );
        }
    }
}
