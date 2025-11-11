<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110234912 extends AbstractMigration
{
    private const MANAGER_WORKER_ID = 'f8a52a6d-893b-4fcb-9c81-0f7f2e03aab2';
    private const MANAGER_ROLE_ID = 'c3f5a3ee-3f0d-4a88-9b5d-1a0dbfb627af';
    private const MANAGER_LOGIN = 'manager.test';
    private const MANAGER_PASSWORD = 'TestManager123!';

    public function getDescription(): string
    {
        return 'Creates a test worker account with manager privileges.';
    }

    public function up(Schema $schema): void
    {
        $existingWorkerId = $this->connection->fetchOne(
            'SELECT id FROM workers WHERE login = :login',
            ['login' => self::MANAGER_LOGIN],
        );

        if (false !== $existingWorkerId) {
            return;
        }

        $passwordHash = password_hash(self::MANAGER_PASSWORD, PASSWORD_BCRYPT);

        $this->addSql(
            'INSERT INTO workers (id, login, password_hash, is_manager) VALUES (:id, :login, :password_hash, :is_manager)',
            [
                'id' => self::MANAGER_WORKER_ID,
                'login' => self::MANAGER_LOGIN,
                'password_hash' => $passwordHash,
                'is_manager' => true,
            ],
            [
                'id' => Types::GUID,
                'login' => Types::STRING,
                'password_hash' => Types::STRING,
                'is_manager' => Types::BOOLEAN,
            ],
        );

        $this->addSql(
            'INSERT INTO worker_roles (id, worker_id, is_manager) VALUES (:id, :worker_id, :is_manager)',
            [
                'id' => self::MANAGER_ROLE_ID,
                'worker_id' => self::MANAGER_WORKER_ID,
                'is_manager' => true,
            ],
            [
                'id' => Types::GUID,
                'worker_id' => Types::GUID,
                'is_manager' => Types::BOOLEAN,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM worker_roles WHERE id = :id',
            [
                'id' => self::MANAGER_ROLE_ID,
            ],
            [
                'id' => Types::GUID,
            ],
        );

        $this->addSql(
            'DELETE FROM workers WHERE id = :id',
            [
                'id' => self::MANAGER_WORKER_ID,
            ],
            [
                'id' => Types::GUID,
            ],
        );
    }
}
