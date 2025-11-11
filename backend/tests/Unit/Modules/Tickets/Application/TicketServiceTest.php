<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tickets\Application;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Event\TicketAddedEvent;
use App\Modules\Tickets\Application\Event\TicketChangedEvent;
use App\Modules\Tickets\Application\Event\TicketStatusChangedEvent;
use App\Modules\Tickets\Application\Event\TicketUpdatedEvent;
use App\Modules\Tickets\Application\TicketService;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\Exception\ActiveTicketWorkExistsException;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;
use App\Modules\Tickets\Domain\TicketRegisteredTimeInterface;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\Ticket;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketRegisteredTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TicketServiceTest extends TestCase
{
    private TicketServiceInterface $service;

    private InMemoryTicketRepository $repository;

    private RecordingEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->repository = new InMemoryTicketRepository();
        $this->dispatcher = new RecordingEventDispatcher();
        $this->service = new TicketService($this->repository, $this->dispatcher);
    }

    public function testCreateTicketPersistsTicket(): void
    {
        $client = $this->createClient('client-1');
        $category = $this->createCategory('category-1', 30);

        $ticket = $this->service->createTicket('ticket-1', $client, $category, 'Title', 'Description');

        self::assertSame($ticket, $this->repository->findById('ticket-1'));
        self::assertSame(TicketInterface::STATUS_AWAITING_RESPONSE, $ticket->getStatus());
        self::assertEventDispatched(TicketAddedEvent::class);
    }

    public function testStartTicketWorkCreatesActiveEntryAndSetsStatus(): void
    {
        $ticket = $this->seedTicket('ticket-1');
        $worker = $this->createWorker('worker-1');

        $registeredTime = $this->service->startTicketWork($ticket, $worker);

        self::assertTrue($registeredTime->isActive());
        self::assertSame(TicketInterface::STATUS_IN_PROGRESS, $this->repository->findById('ticket-1')?->getStatus());
        self::assertEventDispatched(TicketUpdatedEvent::class);
        self::assertEventDispatched(TicketStatusChangedEvent::class);
        self::assertEventDispatched(TicketChangedEvent::class);
    }

    public function testStartTicketWorkThrowsWhenActiveSessionExists(): void
    {
        $ticket = $this->seedTicket('ticket-1');
        $worker = $this->createWorker('worker-1');

        $this->service->startTicketWork($ticket, $worker);

        $this->expectException(ActiveTicketWorkExistsException::class);

        $this->service->startTicketWork($ticket, $worker);
    }

    public function testStopTicketWorkEndsSessionAndResetsStatus(): void
    {
        $ticket = $this->seedTicket('ticket-1');
        $worker = $this->createWorker('worker-1');

        $this->service->startTicketWork($ticket, $worker);
        $this->dispatcher->reset();
        $registeredTime = $this->service->stopTicketWork($ticket, $worker);

        self::assertFalse($registeredTime->isActive());
        self::assertSame(TicketInterface::STATUS_AWAITING_RESPONSE, $this->repository->findById('ticket-1')?->getStatus());
        self::assertNotNull($registeredTime->getEndedAt());
        self::assertEventDispatched(TicketUpdatedEvent::class);
        self::assertEventDispatched(TicketStatusChangedEvent::class);
        self::assertEventDispatched(TicketChangedEvent::class);
    }

    public function testGetWorkersTimeSpentForDateAggregatesMinutes(): void
    {
        $ticket = $this->seedTicket('ticket-1');
        $workerA = $this->createWorker('worker-1');
        $workerB = $this->createWorker('worker-2');

        $ticketEntity = $this->repository->findById($ticket->getId());
        \assert($ticketEntity instanceof Ticket);

        $sessionA = new TicketRegisteredTime(
            'session-1',
            $ticketEntity,
            $workerA->getId(),
            new \DateTimeImmutable('2024-05-01T09:00:00+00:00'),
        );
        $sessionA->end(new \DateTimeImmutable('2024-05-01T09:45:00+00:00'), 45);
        $this->repository->addRegisteredTime($sessionA);

        $sessionB = new TicketRegisteredTime(
            'session-2',
            $ticketEntity,
            $workerB->getId(),
            new \DateTimeImmutable('2024-05-01T10:00:00+00:00'),
        );
        $sessionB->end(new \DateTimeImmutable('2024-05-01T10:30:00+00:00'), 30);
        $this->repository->addRegisteredTime($sessionB);

        $result = $this->service->getWorkersTimeSpentForDate(
            ['worker-1', 'worker-2'],
            new \DateTimeImmutable('2024-05-01T00:00:00+00:00'),
        );

        self::assertSame(
            [
                'worker-1' => 45,
                'worker-2' => 30,
            ],
            $result,
        );
    }

    public function testCalculateWorkerEfficiencyUsesRegisteredTime(): void
    {
        $ticket = $this->seedTicket('ticket-1', defaultMinutes: 60, categoryId: 'category-1');
        $worker = $this->createWorker('worker-1');

        $session = new TicketRegisteredTime(
            'session-1',
            $this->repository->findById($ticket->getId()),
            $worker->getId(),
            new \DateTimeImmutable('-2 hours'),
        );
        $session->end(new \DateTimeImmutable('-1 hour'), 60);
        $this->repository->addRegisteredTime($session);

        $ticketEntity = $this->repository->findById('ticket-1');
        \assert($ticketEntity instanceof Ticket);
        $ticketEntity->close($worker, new \DateTimeImmutable('-30 minutes'));
        $this->repository->update($ticketEntity);

        $efficiency = $this->service->calculateWorkerEfficiency(
            $worker,
            $this->createCategory('category-1', 60),
        );

        self::assertSame(1.0, $efficiency);
    }

    private function seedTicket(string $id, int $defaultMinutes = 30, ?string $categoryId = null): TicketInterface
    {
        $categoryId ??= 'category-'.$id;

        $ticket = new Ticket(
            $id,
            $this->createClient('client-'.$id),
            $this->createCategory($categoryId, $defaultMinutes),
            TicketInterface::STATUS_AWAITING_RESPONSE,
            'Title',
            'Description',
            new \DateTimeImmutable(),
        );

        $this->repository->save($ticket);

        return $ticket;
    }

    private function createClient(string $id): ClientInterface
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('getId')->willReturn($id);
        $client->method('getEmail')->willReturn(null);
        $client->method('getPhone')->willReturn(null);
        $client->method('getFirstName')->willReturn(null);
        $client->method('getLastName')->willReturn(null);

        return $client;
    }

    private function createCategory(string $id, int $defaultMinutes): TicketCategoryInterface
    {
        $category = $this->createStub(TicketCategoryInterface::class);
        $category->method('getId')->willReturn($id);
        $category->method('getName')->willReturn('Category');
        $category->method('getDescription')->willReturn(null);
        $category->method('getDefaultResolutionTimeMinutes')->willReturn($defaultMinutes);

        return $category;
    }

    private function createWorker(string $id): WorkerInterface
    {
        $worker = $this->createStub(WorkerInterface::class);
        $worker->method('getId')->willReturn($id);

        return $worker;
    }

    private function assertEventDispatched(string $eventClass): void
    {
        self::assertNotNull(
            $this->dispatcher->firstEventOf($eventClass),
            sprintf('Failed asserting that event "%s" was dispatched.', $eventClass),
        );
    }
}

final class RecordingEventDispatcher extends EventDispatcher
{
    /** @var list<object> */
    private array $events = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        return parent::dispatch($event, $eventName ?? $event::class);
    }

    public function reset(): void
    {
        $this->events = [];
    }

    public function firstEventOf(string $eventClass): ?object
    {
        foreach ($this->events as $event) {
            if ($event instanceof $eventClass) {
                return $event;
            }
        }

        return null;
    }
}

/**
 * @internal
 */
final class InMemoryTicketRepository implements TicketRepositoryInterface
{
    /** @var array<string, TicketInterface> */
    private array $tickets = [];

    /** @var array<string, TicketMessageInterface[]> */
    private array $messages = [];

    /** @var array<string, TicketNoteInterface[]> */
    private array $notes = [];

    /** @var array<string, TicketRegisteredTimeInterface> */
    private array $registeredTimes = [];

    public function findById(string $id): ?TicketInterface
    {
        return $this->tickets[$id] ?? null;
    }

    public function save(TicketInterface $ticket): void
    {
        $this->tickets[$ticket->getId()] = $ticket;
    }

    public function update(TicketInterface $ticket): void
    {
        $this->tickets[$ticket->getId()] = $ticket;
    }

    public function findTicketMessages(string $ticketId): array
    {
        return $this->messages[$ticketId] ?? [];
    }

    public function addMessage(TicketMessageInterface $message): void
    {
        $this->messages[$message->getTicketId()][] = $message;
    }

    public function findTicketNotes(string $ticketId): array
    {
        return $this->notes[$ticketId] ?? [];
    }

    public function addNote(TicketNoteInterface $note): void
    {
        $this->notes[$note->getTicketId()][] = $note;
    }

    public function findTicketRegisteredTimes(string $ticketId): array
    {
        return array_values(
            array_filter(
                $this->registeredTimes,
                static fn (TicketRegisteredTimeInterface $time): bool => $time->getTicketId() === $ticketId,
            ),
        );
    }

    public function addRegisteredTime(TicketRegisteredTimeInterface $registeredTime): void
    {
        $this->registeredTimes[$registeredTime->getId()] = $registeredTime;
    }

    public function updateRegisteredTime(TicketRegisteredTimeInterface $registeredTime): void
    {
        $this->registeredTimes[$registeredTime->getId()] = $registeredTime;
    }

    public function findActiveRegisteredTime(string $ticketId, string $workerId): ?TicketRegisteredTimeInterface
    {
        foreach ($this->registeredTimes as $registeredTime) {
            if ($registeredTime->getTicketId() === $ticketId
                && $registeredTime->getWorkerId() === $workerId
                && $registeredTime->isActive()
            ) {
                return $registeredTime;
            }
        }

        return null;
    }

    public function getWorkerTimeSpentOnTicket(string $ticketId, string $workerId): int
    {
        $total = 0;

        foreach ($this->registeredTimes as $registeredTime) {
            if ($registeredTime->getTicketId() !== $ticketId || $registeredTime->getWorkerId() !== $workerId) {
                continue;
            }

            $total += $registeredTime->getDurationMinutes() ?? 0;
        }

        return $total;
    }

    public function searchWorkerTickets(string $workerId, array $filters, int $limit, int $offset): array
    {
        return [
            'tickets' => [],
            'total' => 0,
        ];
    }

    public function getWorkerBacklog(string $workerId, array $filters): array
    {
        return [];
    }

    public function getWorkersTimeSpentForDate(array $workerIds, \DateTimeImmutable $date): array
    {
        if ([] === $workerIds) {
            return [];
        }

        $targetDate = $date->format('Y-m-d');
        $minutesByWorker = [];

        foreach ($this->registeredTimes as $registeredTime) {
            $workerId = $registeredTime->getWorkerId();

            if (!\in_array($workerId, $workerIds, true)) {
                continue;
            }

            $startedAt = $registeredTime->getStartedAt();

            if ($startedAt->format('Y-m-d') !== $targetDate) {
                continue;
            }

            $duration = $registeredTime->getDurationMinutes();

            if (null === $duration) {
                $endedAt = $registeredTime->getEndedAt() ?? $startedAt;
                $duration = max(
                    0,
                    (int) floor(($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 60),
                );
            }

            $minutesByWorker[$workerId] = ($minutesByWorker[$workerId] ?? 0) + max(0, $duration);
        }

        return $minutesByWorker;
    }

    public function findTicketsByClient(string $clientId, ?string $status = null): array
    {
        return array_values(
            array_filter(
                $this->tickets,
                static fn (TicketInterface $ticket): bool => $ticket->getClient()->getId() === $clientId
                    && (null === $status || $ticket->getStatus() === $status),
            ),
        );
    }

    public function findTicketsByCategory(string $categoryId, ?string $status = null): array
    {
        return array_values(
            array_filter(
                $this->tickets,
                static fn (TicketInterface $ticket): bool => $ticket->getCategory()->getId() === $categoryId
                    && (null === $status || $ticket->getStatus() === $status),
            ),
        );
    }

    public function findTicketsByWorker(string $workerId, ?string $status = null): array
    {
        $ticketIds = [];

        foreach ($this->registeredTimes as $registeredTime) {
            if ($registeredTime->getWorkerId() === $workerId) {
                $ticketIds[$registeredTime->getTicketId()] = true;
            }
        }

        $results = [];

        foreach (array_keys($ticketIds) as $ticketId) {
            $ticket = $this->findById($ticketId);

            if (null === $ticket) {
                continue;
            }

            if (null !== $status && $ticket->getStatus() !== $status) {
                continue;
            }

            $results[] = $ticket;
        }

        return $results;
    }

    public function findTicketsInProgressByWorker(string $workerId): array
    {
        return array_values(
            array_filter(
                $this->findTicketsByWorker($workerId, TicketInterface::STATUS_IN_PROGRESS),
                static fn (TicketInterface $ticket): bool => true,
            ),
        );
    }

    public function getTotalTimeSpentOnTicket(string $ticketId): int
    {
        $total = 0;

        foreach ($this->registeredTimes as $registeredTime) {
            if ($registeredTime->getTicketId() !== $ticketId) {
                continue;
            }

            $total += $registeredTime->getDurationMinutes() ?? 0;
        }

        return $total;
    }

    public function findClosedTicketsByWorkerAndCategory(
        string $workerId,
        string $categoryId,
        ?\DateTimeImmutable $fromDate = null,
        ?\DateTimeImmutable $toDate = null,
    ): array {
        return array_values(
            array_filter(
                $this->tickets,
                static function (TicketInterface $ticket) use ($categoryId, $fromDate, $toDate): bool {
                    if (TicketInterface::STATUS_CLOSED !== $ticket->getStatus()) {
                        return false;
                    }

                    if ($ticket->getCategory()->getId() !== $categoryId) {
                        return false;
                    }

                    $closedAt = $ticket->getClosedAt();

                    if (!$closedAt instanceof \DateTimeInterface) {
                        return false;
                    }

                    if (null !== $fromDate && $closedAt < $fromDate) {
                        return false;
                    }

                    if (null !== $toDate && $closedAt > $toDate) {
                        return false;
                    }

                    return true;
                },
            ),
        );
    }
}
