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

        $this->repository->assignCategoriesToWorker(
            $workerId,
            ['category-a', 'category-b', 'category-a', ''],
        );

        $assignedCategories = $this->repository->getAssignedCategoryIds($workerId);
        self::assertEqualsCanonicalizing(['category-a', 'category-b'], $assignedCategories);

        $this->repository->assignCategoriesToWorker(
            $workerId,
            ['category-b', 'category-c'],
        );

        $assignedCategories = $this->repository->getAssignedCategoryIds($workerId);
        self::assertEqualsCanonicalizing(['category-b', 'category-c'], $assignedCategories);
    }

    public function testSetManagerRoleCreatesAndUpdatesRole(): void
    {
        $workerId = Uuid::v7()->toRfc4122();
        $this->createWorkerFixture($workerId, 'manager.doe');

        self::assertFalse($this->repository->isManager($workerId));

        $this->repository->setManagerRole($workerId, true);
        self::assertTrue($this->repository->isManager($workerId));

        $this->repository->setManagerRole($workerId, false);
        self::assertFalse($this->repository->isManager($workerId));
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
}
