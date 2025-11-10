<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WorkerAvailability\Infrastructure\Persistence\Doctrine;

use App\Modules\Authentication\Infrastructure\Persistence\Doctrine\Entity\Worker as WorkerEntity;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityRepositoryInterface;
use App\Modules\WorkerAvailability\Infrastructure\Persistence\Doctrine\Entity\WorkerAvailability;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class WorkerAvailabilityRepositoryTest extends KernelTestCase
{
    private WorkerAvailabilityRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $this->repository = $container->get(WorkerAvailabilityRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->purgeTables();
    }

    public function testSavePersistsAvailabilityAndAllowsFetchById(): void
    {
        $worker = $this->createWorker('availability.agent');
        $availability = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-04-01 09:00:00'),
            new \DateTimeImmutable('2024-04-01 12:00:00'),
        );

        $this->repository->save($availability);
        $this->entityManager->clear();

        $loaded = $this->repository->findById($availability->getId());

        self::assertNotNull($loaded);
        self::assertSame($availability->getId(), $loaded->getId());
        self::assertSame($worker->getId(), $loaded->getWorkerId());
        self::assertSame('2024-04-01 09:00:00', $loaded->getStartDatetime()->format('Y-m-d H:i:s'));
        self::assertSame('2024-04-01 12:00:00', $loaded->getEndDatetime()->format('Y-m-d H:i:s'));
    }

    public function testFindForDateReturnsChronologicalSlots(): void
    {
        $worker = $this->createWorker('availability.timeline');

        $morning = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-05-10 08:00:00'),
            new \DateTimeImmutable('2024-05-10 10:00:00'),
        );
        $afternoon = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-05-10 14:00:00'),
            new \DateTimeImmutable('2024-05-10 17:00:00'),
        );
        $otherDay = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-05-11 09:00:00'),
            new \DateTimeImmutable('2024-05-11 12:00:00'),
        );

        $this->repository->save($afternoon);
        $this->repository->save($morning);
        $this->repository->save($otherDay);
        $this->entityManager->clear();

        $results = [...$this->repository->findForDate($worker->getId(), new \DateTimeImmutable('2024-05-10 00:00:00'))];

        self::assertCount(2, $results);
        self::assertSame($morning->getId(), $results[0]->getId());
        self::assertSame($afternoon->getId(), $results[1]->getId());
    }

    public function testFindForPeriodIncludesOverlappingRange(): void
    {
        $worker = $this->createWorker('availability.period');

        $dayOne = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-06-01 09:00:00'),
            new \DateTimeImmutable('2024-06-01 11:00:00'),
        );
        $dayTwo = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-06-03 12:00:00'),
            new \DateTimeImmutable('2024-06-03 15:00:00'),
        );
        $outsideRange = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-06-10 08:00:00'),
            new \DateTimeImmutable('2024-06-10 10:00:00'),
        );

        $this->repository->save($dayOne);
        $this->repository->save($dayTwo);
        $this->repository->save($outsideRange);
        $this->entityManager->clear();

        $results = [...$this->repository->findForPeriod(
            $worker->getId(),
            new \DateTimeImmutable('2024-06-01 00:00:00'),
            new \DateTimeImmutable('2024-06-05 23:59:59'),
        )];

        self::assertCount(2, $results);
        $ids = array_map(static fn (WorkerAvailability $availability): string => $availability->getId(), $results);
        self::assertContains($dayOne->getId(), $ids);
        self::assertContains($dayTwo->getId(), $ids);
        self::assertNotContains($outsideRange->getId(), $ids);
    }

    public function testRemoveAndRemoveAllForDate(): void
    {
        $worker = $this->createWorker('availability.cleanup');

        $slotMorning = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-07-15 08:00:00'),
            new \DateTimeImmutable('2024-07-15 10:00:00'),
        );
        $slotEvening = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-07-15 16:00:00'),
            new \DateTimeImmutable('2024-07-15 18:00:00'),
        );
        $nextDay = $this->createAvailability(
            $worker->getId(),
            new \DateTimeImmutable('2024-07-16 09:00:00'),
            new \DateTimeImmutable('2024-07-16 12:00:00'),
        );

        $this->repository->save($slotMorning);
        $this->repository->save($slotEvening);
        $this->repository->save($nextDay);
        $this->entityManager->clear();

        $reloadedMorning = $this->repository->findById($slotMorning->getId());
        self::assertNotNull($reloadedMorning);

        $this->repository->remove($reloadedMorning);
        self::assertNull($this->repository->findById($slotMorning->getId()));

        $this->repository->removeAllForDate($worker->getId(), new \DateTimeImmutable('2024-07-15 00:00:00'));

        $remainings = [...$this->repository->findForPeriod(
            $worker->getId(),
            new \DateTimeImmutable('2024-07-15 00:00:00'),
            new \DateTimeImmutable('2024-07-17 00:00:00'),
        )];

        self::assertCount(1, $remainings);
        self::assertSame($nextDay->getId(), $remainings[0]->getId());
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }

        parent::tearDown();

        static::ensureKernelShutdown();
    }

    private function purgeTables(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->executeStatement('DELETE FROM worker_availability');
        $this->connection->executeStatement('DELETE FROM workers');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $this->entityManager->clear();
    }

    private function createWorker(string $login): WorkerEntity
    {
        $worker = new WorkerEntity(
            Uuid::v7()->toRfc4122(),
            $login,
            password_hash('Password123!', PASSWORD_BCRYPT),
        );

        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        return $worker;
    }

    private function createAvailability(
        string $workerId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): WorkerAvailability {
        return new WorkerAvailability(
            Uuid::v7()->toRfc4122(),
            $workerId,
            $start,
            $end,
        );
    }
}
