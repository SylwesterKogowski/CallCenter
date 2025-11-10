<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
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

final class TicketService implements TicketServiceInterface
{
    public function __construct(
        private readonly TicketRepositoryInterface $repository,
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

        return $message;
    }

    public function updateTicketStatus(TicketInterface $ticket, string $status): TicketInterface
    {
        $ticketEntity = $this->assertTicketEntity($ticket);
        $ticketEntity->changeStatus($status);

        $this->repository->update($ticketEntity);

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

        if ($ticketEntity->isInProgress() && !$this->hasAnyActiveSessions($ticketEntity)) {
            $ticketEntity->changeStatus(TicketInterface::STATUS_AWAITING_RESPONSE);
            $this->repository->update($ticketEntity);
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
    }

    public function closeTicket(
        TicketInterface $ticket,
        WorkerInterface $worker,
        ?\DateTimeImmutable $closedAt = null,
    ): TicketInterface {
        $ticketEntity = $this->assertTicketEntity($ticket);
        $ticketEntity->close($worker, $closedAt);

        $this->repository->update($ticketEntity);

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

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
