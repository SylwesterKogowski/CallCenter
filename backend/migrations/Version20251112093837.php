<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112093837 extends AbstractMigration
{
    private const MANAGER_WORKER_ID = 'f8a52a6d-893b-4fcb-9c81-0f7f2e03aab2';

    /**
     * @var string[]
     */
    private const CATEGORY_IDS = [
        '550e8400-e29b-41d4-a716-446655440001',
        '550e8400-e29b-41d4-a716-446655440002',
        '550e8400-e29b-41d4-a716-446655440003',
        '550e8400-e29b-41d4-a716-446655440004',
        '550e8400-e29b-41d4-a716-446655440005',
    ];

    public function getDescription(): string
    {
        return 'Assigns the test manager worker to all ticket categories.';
    }

    public function up(Schema $schema): void
    {
        $assignedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach (self::CATEGORY_IDS as $categoryId) {
            $this->addSql(
                <<<'SQL'
INSERT INTO worker_category_assignments (worker_id, category_id, assigned_at, assigned_by_id)
SELECT :worker_id, :category_id, :assigned_at, :assigned_by_id
WHERE NOT EXISTS (
    SELECT 1 FROM worker_category_assignments
    WHERE worker_id = :worker_id AND category_id = :category_id
)
SQL,
                [
                    'worker_id' => self::MANAGER_WORKER_ID,
                    'category_id' => $categoryId,
                    'assigned_at' => $assignedAt,
                    'assigned_by_id' => self::MANAGER_WORKER_ID,
                ],
                [
                    'worker_id' => Types::GUID,
                    'category_id' => Types::GUID,
                    'assigned_at' => Types::DATETIME_IMMUTABLE,
                    'assigned_by_id' => Types::GUID,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CATEGORY_IDS as $categoryId) {
            $this->addSql(
                'DELETE FROM worker_category_assignments WHERE worker_id = :worker_id AND category_id = :category_id',
                [
                    'worker_id' => self::MANAGER_WORKER_ID,
                    'category_id' => $categoryId,
                ],
                [
                    'worker_id' => Types::GUID,
                    'category_id' => Types::GUID,
                ],
            );
        }
    }
}
