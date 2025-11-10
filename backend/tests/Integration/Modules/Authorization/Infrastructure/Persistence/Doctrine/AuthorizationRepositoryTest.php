<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Authorization\Infrastructure\Persistence\Doctrine;

use App\Modules\Authentication\Infrastructure\Persistence\Doctrine\Entity\Worker as WorkerEntity;
use App\Modules\Authorization\Domain\AuthorizationRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AuthorizationRepositoryTest extends KernelTestCase
{
    private AuthorizationRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $this->repository = $container->get(AuthorizationRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->connection->executeStatement('DELETE FROM worker_category_assignments');
        $this->connection->executeStatement('DELETE FROM worker_roles');
        $this->connection->executeStatement('DELETE FROM workers');

        $this->entityManager->clear();
    }

    public function testAssignCategoriesPersistsAndSynchronizesRecords(): void
    {
        $workerId = Uuid::v7()->toRfc4122();
        $this->createWorkerFixture($workerId, 'john.doe');

        $permissions = $this->repository->loadPermissions($workerId);
        self::assertSame([], $permissions->getCategoryIds());
        self::assertFalse($permissions->isManager());

        $initialAssignedAt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $permissions->synchronizeCategories(
            ['category-a', 'category-b', 'category-a', ''],
            null,
            $initialAssignedAt,
        );
        $permissions->setManagerRole(true);

        $this->repository->savePermissions($permissions);

        $reloaded = $this->repository->loadPermissions($workerId);
        self::assertEqualsCanonicalizing(['category-a', 'category-b'], $reloaded->getCategoryIds());
        self::assertTrue($reloaded->isManager());

        $assignmentsByCategory = $this->indexAssignmentsByCategory($reloaded);
        self::assertArrayHasKey('category-a', $assignmentsByCategory);
        self::assertArrayHasKey('category-b', $assignmentsByCategory);
        self::assertEquals($initialAssignedAt, $assignmentsByCategory['category-a']->getAssignedAt());
        self::assertEquals($initialAssignedAt, $assignmentsByCategory['category-b']->getAssignedAt());

        $updateAssignedAt = new \DateTimeImmutable('2025-02-01 09:00:00');
        $reloaded->synchronizeCategories(
            ['category-b', 'category-c'],
            null,
            $updateAssignedAt,
        );
        $reloaded->setManagerRole(false);

        $this->repository->savePermissions($reloaded);

        $afterUpdate = $this->repository->loadPermissions($workerId);
        self::assertEqualsCanonicalizing(['category-b', 'category-c'], $afterUpdate->getCategoryIds());
        self::assertFalse($afterUpdate->isManager());

        $afterAssignments = $this->indexAssignmentsByCategory($afterUpdate);
        self::assertEquals($initialAssignedAt, $afterAssignments['category-b']->getAssignedAt());
        self::assertEquals($updateAssignedAt, $afterAssignments['category-c']->getAssignedAt());
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }

        parent::tearDown();

        static::ensureKernelShutdown();
    }

    private function createWorkerFixture(string $id, string $login): void
    {
        $worker = new WorkerEntity(
            $id,
            $login,
            password_hash('secret123', PASSWORD_BCRYPT),
        );

        $this->entityManager->persist($worker);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function indexAssignmentsByCategory(\App\Modules\Authorization\Domain\WorkerPermissions $permissions): array
    {
        $indexed = [];

        foreach ($permissions->getAssignments() as $assignment) {
            $indexed[$assignment->getCategoryId()] = $assignment;
        }

        return $indexed;
    }
}
