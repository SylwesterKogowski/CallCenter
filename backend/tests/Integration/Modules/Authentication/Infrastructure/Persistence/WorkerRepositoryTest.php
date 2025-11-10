<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Authentication\Infrastructure\Persistence;

use App\Modules\Authentication\Domain\Worker;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authentication\Domain\WorkerRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
        $worker = Worker::register('john.doe', 'secret123');

        $this->repository->save($worker);
        $this->entityManager->clear();

        $loadedById = $this->repository->findById($worker->getId());
        self::assertNotNull($loadedById);
        self::assertInstanceOf(WorkerInterface::class, $loadedById);
        self::assertSame('john.doe', $loadedById->getLogin());
        self::assertTrue($loadedById->verifyPassword('secret123'));

        $loadedByLogin = $this->repository->findByLogin('john.doe');
        self::assertNotNull($loadedByLogin);
        self::assertSame($worker->getId(), $loadedByLogin->getId());
        self::assertTrue($loadedByLogin->verifyPassword('secret123'));
    }

    public function testUpdatePersistsStateChanges(): void
    {
        $worker = Worker::register('jane.doe', 'initial123');

        $this->repository->save($worker);

        $worker->changePassword('initial123', 'newPassword!');
        $worker->promoteToManager();

        $this->repository->update($worker);
        $this->entityManager->clear();

        $reloaded = $this->repository->findByLogin('jane.doe');

        self::assertNotNull($reloaded);
        self::assertInstanceOf(WorkerInterface::class, $reloaded);
        self::assertTrue($reloaded->isManager());
        self::assertTrue($reloaded->verifyPassword('newPassword!'));
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
