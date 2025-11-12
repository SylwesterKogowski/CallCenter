<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Event\TicketAddedEvent;
use App\Modules\Tickets\Application\Event\TicketChangedEvent;
use App\Modules\Tickets\Application\Event\TicketEventInterface;
use App\Modules\Tickets\Application\Event\TicketMessageEvent;
use App\Modules\Tickets\Application\Event\TicketStatusChangedEvent;
use App\Modules\Tickets\Application\Event\TicketUpdatedEvent;
use App\Modules\Tickets\Domain\Exception\ActiveTicketWorkExistsException;
use App\Modules\Tickets\Domain\Exception\InvalidTicketTimeEntryException;
use App\Modules\Tickets\Domain\Exception\TicketWorkNotFoundException;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;
use App\Modules\Tickets\Domain\TicketRegisteredTimeInterface;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\Ticket;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketMessage;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketNote;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketRegisteredTime;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Test w {@see \Tests\Unit\Modules\Tickets\Application\TicketServiceTest}.
 */
final class TicketService implements TicketServiceInterface
{
    public function __construct(
        private readonly TicketRepositoryInterface $repository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function createTicket(
        string $id,
        ClientInterface $client,
        TicketCategoryInterface $category,
        ?string $title = null,
        ?string $description = null,
    ): TicketInterface {
        $normalizedId = $this->normalizeId($id);

        if (null !== $this->repository->findById($normalizedId)) {
            throw new \InvalidArgumentException(sprintf('Ticket with id "%s" already exists.', $normalizedId));
        }

        $ticket = new Ticket(
            $normalizedId,
            $client,
            $category,
            TicketInterface::STATUS_AWAITING_RESPONSE,
            $title,
            $description,
            $this->now(),
        );

        $this->repository->save($ticket);

        $this->dispatchTicketEvent(new TicketAddedEvent(
            $ticket->getId(),
            [
                'ticket' => $this->createTicketSnapshot($ticket),
            ],
        ));

        return $ticket;
    }

    public function getTicketById(string $id): ?TicketInterface
    {
        $normalizedId = $this->normalizeId($id);

        return $this->repository->findById($normalizedId);
    }

    public function getTicketMessages(TicketInterface $ticket): array
    {
        return $this->repository->findTicketMessages($ticket->getId());
    }

    public function getTicketsByClient(ClientInterface $client, ?string $status = null): array
    {
        return $this->repository->findTicketsByClient($client->getId(), $status);
    }

    public function getTicketsByCategory(TicketCategoryInterface $category, ?string $status = null): array
    {
        return $this->repository->findTicketsByCategory($category->getId(), $status);
    }

    public function getTicketsByWorker(WorkerInterface $worker, ?string $status = null): array
    {
        return $this->repository->findTicketsByWorker($worker->getId(), $status);
    }

    public function addMessageToTicket(
        TicketInterface $ticket,
        string $content,
        string $senderType,
        ?string $senderId = null,
        ?string $senderName = null,
    ): TicketMessageInterface {
        $ticketEntity = $this->assertTicketEntity($ticket);
        $normalizedContent = $this->normalizeContent($content, 'Message content cannot be empty.');

        $message = new TicketMessage(
            Uuid::v7()->toRfc4122(),
            $ticketEntity,
            $normalizedContent,
            $senderType,
            $this->normalizeNullableId($senderId),
            $this->normalizeNullableString($senderName),
        );

        $this->repository->addMessage($message);

        $messageSnapshot = $this->createTicketMessageSnapshot($message);
        $ticketSnapshot = $this->createTicketSnapshot($ticketEntity);
        $workerId = 'worker' === $message->getSenderType() ? $message->getSenderId() : null;

        $this->dispatchTicketEvent(new TicketMessageEvent(
            $ticketEntity->getId(),
            $messageSnapshot,
            workerId: $workerId,
        ));

        $this->dispatchTicketEvent(new TicketUpdatedEvent(
            $ticketEntity->getId(),
            [
                'message' => $messageSnapshot,
                'ticket' => $ticketSnapshot,
            ],
        ));

        return $message;
    }

    public function updateTicketStatus(TicketInterface $ticket, string $status): TicketInterface
    {
        $ticketEntity = $this->assertTicketEntity($ticket);
        $previousStatus = $ticketEntity->getStatus();
        $ticketEntity->changeStatus($status);

        $this->repository->update($ticketEntity);

        if ($previousStatus !== $ticketEntity->getStatus()) {
            $snapshot = $this->createTicketSnapshot($ticketEntity);

            $this->dispatchTicketEvent(new TicketStatusChangedEvent(
                $ticketEntity->getId(),
                [
                    'ticketId' => $ticketEntity->getId(),
                    'oldStatus' => $previousStatus,
                    'newStatus' => $ticketEntity->getStatus(),
                    'ticket' => $snapshot,
                ],
            ));

            $this->dispatchTicketEvent(new TicketChangedEvent(
                $ticketEntity->getId(),
                [
                    'ticketId' => $ticketEntity->getId(),
                    'status' => $ticketEntity->getStatus(),
                    'ticket' => $snapshot,
                ],
            ));
        }

        return $ticketEntity;
    }

    public function addTicketNote(
        TicketInterface $ticket,
        WorkerInterface $worker,
        string $note,
    ): TicketNoteInterface {
        $ticketEntity = $this->assertTicketEntity($ticket);
        $normalizedNote = $this->normalizeContent($note, 'Ticket note cannot be empty.');

        $noteEntity = new TicketNote(
            Uuid::v7()->toRfc4122(),
            $ticketEntity,
            $worker->getId(),
            $normalizedNote,
            $this->now(),
        );

        $this->repository->addNote($noteEntity);

        $this->dispatchTicketEvent(new TicketUpdatedEvent(
            $ticketEntity->getId(),
            [
                'note' => $this->createTicketNoteSnapshot($noteEntity),
                'ticket' => $this->createTicketSnapshot($ticketEntity),
            ],
            workerId: $worker->getId(),
        ));

        return $noteEntity;
    }

    public function getTicketNotes(TicketInterface $ticket): array
    {
        return $this->repository->findTicketNotes($ticket->getId());
    }

    public function getTicketRegisteredTime(TicketInterface $ticket): array
    {
        return $this->repository->findTicketRegisteredTimes($ticket->getId());
    }

    public function getWorkerTimeSpentOnTicket(
        TicketInterface $ticket,
        WorkerInterface $worker,
    ): int {
        return $this->repository->getWorkerTimeSpentOnTicket($ticket->getId(), $worker->getId());
    }

    public function getWorkersTimeSpentForDate(array $workerIds, \DateTimeImmutable $date): array
    {
        return $this->repository->getWorkersTimeSpentForDate($workerIds, $date);
    }

    public function getTotalTimeSpentOnTicket(TicketInterface $ticket): int
    {
        return $this->repository->getTotalTimeSpentOnTicket($ticket->getId());
    }

    public function startTicketWork(TicketInterface $ticket, WorkerInterface $worker): TicketRegisteredTimeInterface
    {
        $active = $this->repository->findActiveRegisteredTime($ticket->getId(), $worker->getId());

        if (null !== $active) {
            throw ActiveTicketWorkExistsException::forWorker($ticket->getId(), $worker->getId());
        }

        $ticketEntity = $this->assertTicketEntity($ticket);
        $previousStatus = $ticketEntity->getStatus();

        $registeredTime = new TicketRegisteredTime(
            Uuid::v7()->toRfc4122(),
            $ticketEntity,
            $worker->getId(),
            $this->now(),
        );

        if (!$ticketEntity->isInProgress()) {
            $ticketEntity->changeStatus(TicketInterface::STATUS_IN_PROGRESS);
        }

        $this->repository->addRegisteredTime($registeredTime);
        $this->repository->update($ticketEntity);

        $ticketSnapshot = $this->createTicketSnapshot($ticketEntity);
        $registeredTimeSnapshot = $this->createRegisteredTimeSnapshot($registeredTime);

        $this->dispatchTicketEvent(new TicketUpdatedEvent(
            $ticketEntity->getId(),
            [
                'registeredTime' => $registeredTimeSnapshot,
                'ticket' => $ticketSnapshot,
            ],
            workerId: $worker->getId(),
        ));

        if ($previousStatus !== $ticketEntity->getStatus()) {
            $this->dispatchTicketEvent(new TicketStatusChangedEvent(
                $ticketEntity->getId(),
                [
                    'ticketId' => $ticketEntity->getId(),
                    'oldStatus' => $previousStatus,
                    'newStatus' => $ticketEntity->getStatus(),
                    'ticket' => $ticketSnapshot,
                ],
                workerId: $worker->getId(),
            ));

            $this->dispatchTicketEvent(new TicketChangedEvent(
                $ticketEntity->getId(),
                [
                    'ticketId' => $ticketEntity->getId(),
                    'status' => $ticketEntity->getStatus(),
                    'ticket' => $ticketSnapshot,
                ],
                workerId: $worker->getId(),
            ));
        }

        return $registeredTime;
    }

    public function stopTicketWork(TicketInterface $ticket, WorkerInterface $worker): TicketRegisteredTimeInterface
    {
        $active = $this->repository->findActiveRegisteredTime($ticket->getId(), $worker->getId());

        if (null === $active) {
            throw TicketWorkNotFoundException::forWorker($ticket->getId(), $worker->getId());
        }

        $active->end($this->now());
        $this->repository->updateRegisteredTime($active);

        $ticketEntity = $this->assertTicketEntity($ticket);
        $previousStatus = $ticketEntity->getStatus();

        if ($ticketEntity->isInProgress() && !$this->hasAnyActiveSessions($ticketEntity)) {
            $ticketEntity->changeStatus(TicketInterface::STATUS_AWAITING_RESPONSE);
            $this->repository->update($ticketEntity);
        }

        $ticketSnapshot = $this->createTicketSnapshot($ticketEntity);
        $registeredTimeSnapshot = $this->createRegisteredTimeSnapshot($active);

        $this->dispatchTicketEvent(new TicketUpdatedEvent(
            $ticketEntity->getId(),
            [
                'registeredTime' => $registeredTimeSnapshot,
                'ticket' => $ticketSnapshot,
            ],
            workerId: $worker->getId(),
        ));

        if ($previousStatus !== $ticketEntity->getStatus()) {
            $this->dispatchTicketEvent(new TicketStatusChangedEvent(
                $ticketEntity->getId(),
                [
                    'ticketId' => $ticketEntity->getId(),
                    'oldStatus' => $previousStatus,
                    'newStatus' => $ticketEntity->getStatus(),
                    'ticket' => $ticketSnapshot,
                ],
                workerId: $worker->getId(),
            ));

            $this->dispatchTicketEvent(new TicketChangedEvent(
                $ticketEntity->getId(),
                [
                    'ticketId' => $ticketEntity->getId(),
                    'status' => $ticketEntity->getStatus(),
                    'ticket' => $ticketSnapshot,
                ],
                workerId: $worker->getId(),
            ));
        }

        return $active;
    }

    public function registerManualTimeEntry(
        TicketInterface $ticket,
        WorkerInterface $worker,
        int $minutes,
        bool $isPhoneCall,
    ): void {
        if ($minutes <= 0) {
            throw InvalidTicketTimeEntryException::minutesMustBePositive($minutes);
        }

        $ticketEntity = $this->assertTicketEntity($ticket);
        $endedAt = $this->now();
        $startedAt = $endedAt->sub(new \DateInterval(sprintf('PT%dM', $minutes)));

        $registeredTime = new TicketRegisteredTime(
            Uuid::v7()->toRfc4122(),
            $ticketEntity,
            $worker->getId(),
            $startedAt,
            $isPhoneCall,
        );

        $registeredTime->end($endedAt, $minutes);

        if ($isPhoneCall) {
            $registeredTime->markAsPhoneCall();
        }

        $this->repository->addRegisteredTime($registeredTime);

        $this->dispatchTicketEvent(new TicketUpdatedEvent(
            $ticketEntity->getId(),
            [
                'registeredTime' => $this->createRegisteredTimeSnapshot($registeredTime),
                'ticket' => $this->createTicketSnapshot($ticketEntity),
            ],
            workerId: $worker->getId(),
        ));
    }

    public function closeTicket(
        TicketInterface $ticket,
        WorkerInterface $worker,
        ?\DateTimeImmutable $closedAt = null,
    ): TicketInterface {
        $ticketEntity = $this->assertTicketEntity($ticket);
        $previousStatus = $ticketEntity->getStatus();
        $ticketEntity->close($worker, $closedAt);

        $this->repository->update($ticketEntity);

        $ticketSnapshot = $this->createTicketSnapshot($ticketEntity);

        $this->dispatchTicketEvent(new TicketStatusChangedEvent(
            $ticketEntity->getId(),
            [
                'ticketId' => $ticketEntity->getId(),
                'oldStatus' => $previousStatus,
                'newStatus' => $ticketEntity->getStatus(),
                'ticket' => $ticketSnapshot,
            ],
            workerId: $worker->getId(),
        ));

        $this->dispatchTicketEvent(new TicketChangedEvent(
            $ticketEntity->getId(),
            [
                'ticketId' => $ticketEntity->getId(),
                'status' => $ticketEntity->getStatus(),
                'ticket' => $ticketSnapshot,
            ],
            workerId: $worker->getId(),
        ));

        return $ticketEntity;
    }

    public function getTicketsInProgress(WorkerInterface $worker): array
    {
        return $this->repository->findTicketsInProgressByWorker($worker->getId());
    }

    public function calculateWorkerEfficiency(
        WorkerInterface $worker,
        TicketCategoryInterface $category,
        ?\DateTimeImmutable $fromDate = null,
        ?\DateTimeImmutable $toDate = null,
    ): float {
        $tickets = $this->repository->findClosedTicketsByWorkerAndCategory(
            $worker->getId(),
            $category->getId(),
            $fromDate,
            $toDate,
        );

        if ([] === $tickets) {
            return 0.0;
        }

        $totalDefault = 0;
        $totalActual = 0;

        foreach ($tickets as $closedTicket) {
            $defaultMinutes = $closedTicket->getCategory()->getDefaultResolutionTimeMinutes();

            if ($defaultMinutes <= 0) {
                continue;
            }

            $totalDefault += $defaultMinutes;
            $totalActual += $this->repository->getWorkerTimeSpentOnTicket(
                $closedTicket->getId(),
                $worker->getId(),
            );
        }

        if ($totalActual <= 0) {
            return 0.0;
        }

        return round($totalDefault / $totalActual, 2);
    }

    /**
     * @param iterable<TicketRegisteredTimeInterface> $registeredTimes
     */
    private function hasActiveSession(iterable $registeredTimes): bool
    {
        foreach ($registeredTimes as $registeredTime) {
            if ($registeredTime->isActive()) {
                return true;
            }
        }

        return false;
    }

    private function hasAnyActiveSessions(Ticket $ticket): bool
    {
        $registeredTimes = $this->repository->findTicketRegisteredTimes($ticket->getId());

        return $this->hasActiveSession($registeredTimes);
    }

    private function assertTicketEntity(TicketInterface $ticket): Ticket
    {
        if ($ticket instanceof Ticket) {
            return $ticket;
        }

        $managed = $this->repository->findById($ticket->getId());

        if ($managed instanceof Ticket) {
            return $managed;
        }

        throw new \InvalidArgumentException(sprintf('Ticket "%s" is not managed by Doctrine context.', $ticket->getId()));
    }

    private function normalizeId(string $id): string
    {
        $normalized = trim($id);

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Ticket id cannot be empty.');
        }

        return $normalized;
    }

    private function normalizeContent(string $content, string $errorMessage): string
    {
        $normalized = trim($content);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $normalized;
    }

    private function normalizeNullableId(?string $id): ?string
    {
        if (null === $id) {
            return null;
        }

        $normalized = trim($id);

        return '' === $normalized ? null : $normalized;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }

    private function dispatchTicketEvent(TicketEventInterface $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }

    /**
     * @return array<string, mixed>
     */
    private function createTicketSnapshot(TicketInterface $ticket): array
    {
        $category = $ticket->getCategory();
        $client = $ticket->getClient();

        return [
            'id' => $ticket->getId(),
            'status' => $ticket->getStatus(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'createdAt' => $this->formatDate($ticket->getCreatedAt()),
            'updatedAt' => $this->formatDate($ticket->getUpdatedAt()),
            'closedAt' => $this->formatDate($ticket->getClosedAt()),
            'closedByWorkerId' => $ticket->getClosedByWorkerId(),
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'defaultResolutionMinutes' => $category->getDefaultResolutionTimeMinutes(),
            ],
            'client' => [
                'id' => $client->getId(),
                'firstName' => $client->getFirstName(),
                'lastName' => $client->getLastName(),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createTicketMessageSnapshot(TicketMessageInterface $message): array
    {
        $snapshot = [
            'id' => $message->getId(),
            'ticketId' => $message->getTicketId(),
            'senderType' => $message->getSenderType(),
            'senderId' => $message->getSenderId(),
            'senderName' => $message->getSenderName(),
            'content' => $message->getContent(),
            'createdAt' => $this->formatDate($message->getCreatedAt()),
            'status' => $message->getStatus(),
        ];

        return array_filter(
            $snapshot,
            static fn (mixed $value): bool => null !== $value,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createTicketNoteSnapshot(TicketNoteInterface $note): array
    {
        return [
            'id' => $note->getId(),
            'ticketId' => $note->getTicketId(),
            'workerId' => $note->getWorkerId(),
            'content' => $note->getContent(),
            'createdAt' => $this->formatDate($note->getCreatedAt()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRegisteredTimeSnapshot(TicketRegisteredTimeInterface $registeredTime): array
    {
        return [
            'id' => $registeredTime->getId(),
            'ticketId' => $registeredTime->getTicketId(),
            'workerId' => $registeredTime->getWorkerId(),
            'startedAt' => $this->formatDate($registeredTime->getStartedAt()),
            'endedAt' => $this->formatDate($registeredTime->getEndedAt()),
            'durationMinutes' => $registeredTime->getDurationMinutes(),
            'isPhoneCall' => $registeredTime->isPhoneCall(),
            'isActive' => $registeredTime->isActive(),
        ];
    }

    private function formatDate(?\DateTimeInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
