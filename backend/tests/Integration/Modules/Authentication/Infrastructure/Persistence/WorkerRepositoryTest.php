<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Authentication\Infrastructure\Persistence;

use App\Modules\Authentication\Domain\WorkerRepositoryInterface;
use App\Modules\Authentication\Interface\Persistence\Doctrine\Entity\Worker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class WorkerRepositoryTest extends KernelTestCase
{
    private WorkerRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $this->repository = $container->get(WorkerRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->connection->executeStatement('DELETE FROM workers');
        $this->entityManager->clear();
    }

    public function testSavePersistsWorkerAndAllowsFetchByIdAndLogin(): void
    {
        $worker = new Worker(
            Uuid::v4()->toRfc4122(),
            'john.doe',
            password_hash('secret123', PASSWORD_BCRYPT),
        );

        $this->repository->save($worker);
        $this->entityManager->clear();

        $loadedById = $this->repository->findById($worker->getId());
        self::assertNotNull($loadedById);
        self::assertSame('john.doe', $loadedById->getLogin());

        $loadedByLogin = $this->repository->findByLogin('john.doe');
        self::assertNotNull($loadedByLogin);
        self::assertSame($worker->getId(), $loadedByLogin->getId());
    }

    public function testUpdatePersistsStateChanges(): void
    {
        $worker = new Worker(
            Uuid::v4()->toRfc4122(),
            'jane.doe',
            password_hash('initial123', PASSWORD_BCRYPT),
        );

        $this->repository->save($worker);

        $newHash = password_hash('newPassword!', PASSWORD_BCRYPT);
        $worker->changePasswordHash($newHash);
        $worker->promoteToManager();

        $this->repository->update($worker);
        $this->entityManager->clear();

        $reloaded = $this->repository->findByLogin('jane.doe');

        self::assertNotNull($reloaded);
        self::assertInstanceOf(Worker::class, $reloaded);
        self::assertTrue($reloaded->isManager());
        self::assertSame($newHash, $reloaded->getPasswordHash());
        self::assertNotNull($reloaded->getUpdatedAt());
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }

        parent::tearDown();

        static::ensureKernelShutdown();
    }
}

