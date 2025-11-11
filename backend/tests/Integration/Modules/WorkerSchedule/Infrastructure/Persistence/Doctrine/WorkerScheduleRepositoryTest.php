<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WorkerSchedule\Infrastructure\Persistence\Doctrine;

use App\Modules\Authentication\Infrastructure\Persistence\Doctrine\Entity\Worker as WorkerEntity;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\Ticket;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketCategorySnapshot;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketClientSnapshot;
use App\Modules\WorkerSchedule\Domain\WorkerScheduleInterface;
use App\Modules\WorkerSchedule\Domain\WorkerScheduleRepositoryInterface;
use App\Modules\WorkerSchedule\Infrastructure\Persistence\Doctrine\Entity\WorkerSchedule;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class WorkerScheduleRepositoryTest extends KernelTestCase
{
    private WorkerScheduleRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $this->repository = $container->get(WorkerScheduleRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->purgeTables();
    }

    public function testSavePersistsScheduleAndAllowsFetchById(): void
    {
        $worker = $this->createWorker('worker.schedule.save');
        $ticket = $this->createTicket('customer.save@example.com');
        $scheduledDate = new \DateTimeImmutable('+1 day');

        $assignment = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticket->getId(),
            $scheduledDate,
            assignedById: $worker->getId(),
            isAutoAssigned: false,
            priority: 5,
        );

        $this->repository->save($assignment);
        $this->entityManager->clear();

        $loaded = $this->repository->findById($assignment->getId());

        self::assertNotNull($loaded);
        self::assertSame($assignment->getId(), $loaded->getId());
        self::assertSame($worker->getId(), $loaded->getWorkerId());
        self::assertSame($ticket->getId(), $loaded->getTicketId());
        self::assertTrue($loaded->isOnDate($scheduledDate));
        self::assertSame(5, $loaded->getPriority());
        self::assertFalse($loaded->isAutoAssigned());
    }

    public function testFindByWorkerAndDateReturnsAssignmentsOrderedByPriority(): void
    {
        $worker = $this->createWorker('worker.schedule.order');
        $ticketHigh = $this->createTicket('customer.high@example.com');
        $ticketLow = $this->createTicket('customer.low@example.com');
        $scheduledDate = new \DateTimeImmutable('+2 days');

        $highPriority = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticketHigh->getId(),
            $scheduledDate,
            priority: 10,
        );
        $lowPriority = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticketLow->getId(),
            $scheduledDate,
            priority: 1,
        );

        $this->repository->save($lowPriority);
        $this->repository->save($highPriority);
        $this->entityManager->clear();

        $results = [...$this->repository->findByWorkerAndDate($worker->getId(), $scheduledDate)];

        self::assertCount(2, $results);
        self::assertSame($highPriority->getId(), $results[0]->getId());
        self::assertSame($lowPriority->getId(), $results[1]->getId());
    }

    public function testFindByWorkerAndPeriodReturnsAssignmentsWithinRange(): void
    {
        $worker = $this->createWorker('worker.schedule.period');
        $ticketOne = $this->createTicket('customer.period1@example.com');
        $ticketTwo = $this->createTicket('customer.period2@example.com');
        $ticketOutside = $this->createTicket('customer.period3@example.com');

        $rangeStart = new \DateTimeImmutable('+3 days');
        $rangeEnd = new \DateTimeImmutable('+7 days');

        $insideOne = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticketOne->getId(),
            $rangeStart->modify('+1 day'),
        );
        $insideTwo = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticketTwo->getId(),
            $rangeEnd->modify('-1 day'),
        );
        $outside = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticketOutside->getId(),
            $rangeEnd->modify('+3 days'),
        );

        $this->repository->save($insideOne);
        $this->repository->save($insideTwo);
        $this->repository->save($outside);
        $this->entityManager->clear();

        $results = [...$this->repository->findByWorkerAndPeriod($worker->getId(), $rangeStart, $rangeEnd)];

        self::assertCount(2, $results);
        $ids = array_map(static fn (WorkerScheduleInterface $assignment): string => $assignment->getId(), $results);
        self::assertContains($insideOne->getId(), $ids);
        self::assertContains($insideTwo->getId(), $ids);
        self::assertNotContains($outside->getId(), $ids);
    }

    public function testFindByTicketAndDateReturnsAssignments(): void
    {
        $workerOne = $this->createWorker('worker.schedule.ticket1');
        $workerTwo = $this->createWorker('worker.schedule.ticket2');
        $ticket = $this->createTicket('customer.ticket@example.com');
        $date = new \DateTimeImmutable('+4 days');

        $assignmentOne = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $workerOne->getId(),
            $ticket->getId(),
            $date,
        );
        $assignmentTwo = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $workerTwo->getId(),
            $ticket->getId(),
            $date,
        );

        $this->repository->save($assignmentOne);
        $this->repository->save($assignmentTwo);
        $this->entityManager->clear();

        $results = [...$this->repository->findByTicketAndDate($ticket->getId(), $date)];

        self::assertCount(2, $results);
        $ids = array_map(static fn (WorkerScheduleInterface $assignment): string => $assignment->getId(), $results);
        self::assertContains($assignmentOne->getId(), $ids);
        self::assertContains($assignmentTwo->getId(), $ids);
    }

    public function testFindOneByWorkerTicketAndDateFindsExactMatch(): void
    {
        $worker = $this->createWorker('worker.schedule.single');
        $ticket = $this->createTicket('customer.single@example.com');
        $date = new \DateTimeImmutable('+5 days');

        $assignment = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticket->getId(),
            $date,
        );
        $this->repository->save($assignment);
        $this->entityManager->clear();

        $found = $this->repository->findOneByWorkerTicketAndDate($ticket->getId(), $worker->getId(), $date);

        self::assertNotNull($found);
        self::assertSame($assignment->getId(), $found->getId());

        $missing = $this->repository->findOneByWorkerTicketAndDate(
            $ticket->getId(),
            $worker->getId(),
            $date->modify('+1 day'),
        );

        self::assertNull($missing);
    }

    public function testRemoveDeletesAssignment(): void
    {
        $worker = $this->createWorker('worker.schedule.remove');
        $ticket = $this->createTicket('customer.remove@example.com');
        $date = new \DateTimeImmutable('+6 days');

        $assignment = new WorkerSchedule(
            Uuid::v7()->toRfc4122(),
            $worker->getId(),
            $ticket->getId(),
            $date,
        );
        $this->repository->save($assignment);
        $this->entityManager->clear();

        $loaded = $this->repository->findById($assignment->getId());
        self::assertNotNull($loaded);

        $this->repository->remove($loaded);

        self::assertNull($this->repository->findById($assignment->getId()));
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
        $this->connection->executeStatement('DELETE FROM worker_schedule');
        $this->connection->executeStatement('DELETE FROM tickets');
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

    private function createTicket(string $clientEmail): Ticket
    {
        $client = new TicketClientSnapshot(
            Uuid::v7()->toRfc4122(),
            $clientEmail,
            '+48 111 222 333',
            'Test',
            'Customer',
        );
        $category = new TicketCategorySnapshot(
            Uuid::v7()->toRfc4122(),
            'Support',
            'General support inquiries',
            30,
        );

        $ticket = new Ticket(
            Uuid::v7()->toRfc4122(),
            $client,
            $category,
            TicketInterface::STATUS_IN_PROGRESS,
            'Example ticket',
            'Ticket description',
        );

        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        return $ticket;
    }
}
