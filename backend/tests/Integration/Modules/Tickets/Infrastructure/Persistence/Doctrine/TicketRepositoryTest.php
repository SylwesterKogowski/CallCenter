<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Tickets\Infrastructure\Persistence\Doctrine;

use App\Modules\Authentication\Infrastructure\Persistence\Doctrine\Entity\Worker as WorkerEntity;
use App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity\WorkerCategoryAssignment;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\Ticket;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketCategorySnapshot;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketClientSnapshot;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketMessage;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketNote;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketRegisteredTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class TicketRepositoryTest extends KernelTestCase
{
    private TicketRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $this->repository = $container->get(TicketRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->purgeTables();
    }

    public function testSavePersistsTicketAndAllowsFetchById(): void
    {
        $ticket = $this->createTicket();

        $this->repository->save($ticket);
        $this->entityManager->clear();

        $loaded = $this->repository->findById($ticket->getId());

        self::assertNotNull($loaded);
        self::assertSame($ticket->getId(), $loaded->getId());
        self::assertSame('Test ticket', $loaded->getTitle());
        self::assertSame('support', $loaded->getCategory()->getName());
        self::assertSame('awaiting_response', $loaded->getStatus());
    }

    public function testRelatedEntitiesPersistence(): void
    {
        $worker = $this->createWorker('agent.one');
        $ticket = $this->createTicket();

        $this->repository->save($ticket);

        $message = new TicketMessage(
            Uuid::v7()->toRfc4122(),
            $ticket,
            'Hello from client',
            'client',
            null,
            'John Smith',
        );
        $this->repository->addMessage($message);

        $note = new TicketNote(
            Uuid::v7()->toRfc4122(),
            $ticket,
            $worker->getId(),
            'Handled initial troubleshooting',
        );
        $this->repository->addNote($note);

        $registeredTime = new TicketRegisteredTime(
            Uuid::v7()->toRfc4122(),
            $ticket,
            $worker->getId(),
            new \DateTimeImmutable('2024-01-01 10:00:00'),
            true,
        );

        $this->repository->addRegisteredTime($registeredTime);

        $registeredTime->end(new \DateTimeImmutable('2024-01-01 10:30:00'));
        $this->repository->updateRegisteredTime($registeredTime);

        $this->entityManager->clear();

        $messages = $this->repository->findTicketMessages($ticket->getId());
        self::assertCount(1, $messages);
        self::assertSame('Hello from client', $messages[0]->getContent());

        $notes = $this->repository->findTicketNotes($ticket->getId());
        self::assertCount(1, $notes);
        self::assertSame('Handled initial troubleshooting', $notes[0]->getContent());

        $times = $this->repository->findTicketRegisteredTimes($ticket->getId());
        self::assertCount(1, $times);
        self::assertSame(30, $times[0]->getDurationMinutes());
        self::assertTrue($times[0]->isPhoneCall());

        $timeSpent = $this->repository->getWorkerTimeSpentOnTicket($ticket->getId(), $worker->getId());
        self::assertSame(30, $timeSpent);
    }

    public function testSearchWorkerTicketsAndBacklog(): void
    {
        $worker = $this->createWorker('agent.search');

        $ticketA = $this->createTicket(status: 'in_progress', title: 'Router issue', categoryId: 'cat-1', categoryName: 'support');
        $ticketB = $this->createTicket(status: 'awaiting_customer', title: 'Billing question', categoryId: 'cat-2', categoryName: 'billing');

        $this->repository->save($ticketA);
        $this->repository->save($ticketB);

        $assignmentSupport = new WorkerCategoryAssignment($worker->getId(), 'cat-1');
        $assignmentBilling = new WorkerCategoryAssignment($worker->getId(), 'cat-2');
        $this->entityManager->persist($assignmentSupport);
        $this->entityManager->persist($assignmentBilling);
        $this->entityManager->flush();

        $ticketARegisteredTime = new TicketRegisteredTime(
            Uuid::v7()->toRfc4122(),
            $ticketA,
            $worker->getId(),
            new \DateTimeImmutable('2024-01-01 11:00:00'),
        );
        $this->repository->addRegisteredTime($ticketARegisteredTime);

        $result = $this->repository->searchWorkerTickets(
            $worker->getId(),
            ['status' => 'in_progress'],
            10,
            0,
        );

        self::assertSame(1, $result['total']);
        self::assertCount(1, $result['tickets']);
        self::assertSame('Router issue', $result['tickets'][0]->getTitle());

        $backlog = $this->repository->getWorkerBacklog(
            $worker->getId(),
            ['statuses' => ['awaiting_customer', 'awaiting_response']],
        );

        self::assertCount(1, $backlog);
        self::assertSame('Billing question', $backlog[0]->getTitle());
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
        $this->connection->executeStatement('DELETE FROM ticket_registered_time');
        $this->connection->executeStatement('DELETE FROM ticket_notes');
        $this->connection->executeStatement('DELETE FROM ticket_messages');
        $this->connection->executeStatement('DELETE FROM tickets');
        $this->connection->executeStatement('DELETE FROM worker_category_assignments');
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

    private function createTicket(
        string $status = 'awaiting_response',
        string $title = 'Test ticket',
        string $categoryId = 'cat-support',
        string $categoryName = 'support',
    ): Ticket {
        $client = new TicketClientSnapshot(
            Uuid::v7()->toRfc4122(),
            'client@example.com',
            '+48100100200',
            'John',
            'Doe',
        );

        $category = new TicketCategorySnapshot(
            $categoryId,
            $categoryName,
            'Support category',
            60,
        );

        return new Ticket(
            Uuid::v7()->toRfc4122(),
            $client,
            $category,
            $status,
            $title,
            'Initial description',
            new \DateTimeImmutable('2024-01-01 09:00:00'),
        );
    }
}
