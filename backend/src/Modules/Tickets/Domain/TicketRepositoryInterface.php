<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

/**
 * Storage abstraction for ticket aggregates and their related history.
 *
 * The repository coordinates persistence for core use cases exposed through:
 *  - {@see \App\Modules\Tickets\Application\TicketServiceInterface}
 *  - {@see \App\Modules\Tickets\Application\TicketSearchServiceInterface}
 *  - {@see \App\Modules\Tickets\Application\TicketBacklogServiceInterface}
 */
interface TicketRepositoryInterface
{
    public function findById(string $id): ?TicketInterface;

    public function save(TicketInterface $ticket): void;

    public function update(TicketInterface $ticket): void;

    /**
     * @return TicketMessageInterface[]
     */
    public function findTicketMessages(string $ticketId): array;

    public function addMessage(TicketMessageInterface $message): void;

    /**
     * @return TicketNoteInterface[]
     */
    public function findTicketNotes(string $ticketId): array;

    public function addNote(TicketNoteInterface $note): void;

    /**
     * @return TicketRegisteredTimeInterface[]
     */
    public function findTicketRegisteredTimes(string $ticketId): array;

    public function addRegisteredTime(TicketRegisteredTimeInterface $registeredTime): void;

    public function updateRegisteredTime(TicketRegisteredTimeInterface $registeredTime): void;

    public function findActiveRegisteredTime(string $ticketId, string $workerId): ?TicketRegisteredTimeInterface;

    public function getWorkerTimeSpentOnTicket(string $ticketId, string $workerId): int;

    /**
     * @param string[] $workerIds
     *
     * @return array<string, int>
     */
    public function getWorkersTimeSpentForDate(array $workerIds, \DateTimeImmutable $date): array;

    /**
     * @param array{
     *     status?: string|null,
     *     category_id?: string|null,
     *     query?: string|null
     * } $filters
     *
     * @return array{tickets: TicketInterface[], total: int}
     */
    public function searchWorkerTickets(string $workerId, array $filters, int $limit, int $offset): array;

    /**
     * @param array{
     *     statuses?: string[]|null,
     *     category_ids?: string[]|null
     * } $filters
     *
     * @return TicketInterface[]
     */
    public function getWorkerBacklog(string $workerId, array $filters): array;

    /**
     * @return TicketInterface[]
     */
    public function findTicketsByClient(string $clientId, ?string $status = null): array;

    /**
     * @return TicketInterface[]
     */
    public function findTicketsByCategory(string $categoryId, ?string $status = null): array;

    /**
     * @return TicketInterface[]
     */
    public function findTicketsByWorker(string $workerId, ?string $status = null): array;

    /**
     * @return TicketInterface[]
     */
    public function findTicketsInProgressByWorker(string $workerId): array;

    public function getTotalTimeSpentOnTicket(string $ticketId): int;

    /**
     * @return TicketInterface[]
     */
    public function findClosedTicketsByWorkerAndCategory(
        string $workerId,
        string $categoryId,
        ?\DateTimeImmutable $fromDate = null,
        ?\DateTimeImmutable $toDate = null,
    ): array;
}
