<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;
use App\Modules\Tickets\Domain\TicketRegisteredTimeInterface;

interface TicketServiceInterface
{
    public function createTicket(
        string $id,
        ClientInterface $client,
        TicketCategoryInterface $category,
        ?string $title = null,
        ?string $description = null,
    ): TicketInterface;

    public function getTicketById(string $id): ?TicketInterface;

    /**
     * @return TicketMessageInterface[]
     */
    public function getTicketMessages(TicketInterface $ticket): array;

    /**
     * @return TicketInterface[]
     */
    public function getTicketsByClient(ClientInterface $client, ?string $status = null): array;

    /**
     * @return TicketInterface[]
     */
    public function getTicketsByCategory(TicketCategoryInterface $category, ?string $status = null): array;

    /**
     * @return TicketInterface[]
     */
    public function getTicketsByWorker(WorkerInterface $worker, ?string $status = null): array;

    public function addMessageToTicket(
        TicketInterface $ticket,
        string $content,
        string $senderType,
        ?string $senderId = null,
        ?string $senderName = null,
    ): TicketMessageInterface;

    public function updateTicketStatus(TicketInterface $ticket, string $status): TicketInterface;

    public function addTicketNote(
        TicketInterface $ticket,
        WorkerInterface $worker,
        string $note,
    ): TicketNoteInterface;

    /**
     * @return TicketNoteInterface[]
     */
    public function getTicketNotes(TicketInterface $ticket): array;

    /**
     * @return TicketRegisteredTimeInterface[]
     */
    public function getTicketRegisteredTime(TicketInterface $ticket): array;

    public function getWorkerTimeSpentOnTicket(
        TicketInterface $ticket,
        WorkerInterface $worker,
    ): int;

    /**
     * @param string[] $workerIds
     *
     * @return array<string, int>
     */
    public function getWorkersTimeSpentForDate(array $workerIds, \DateTimeImmutable $date): array;

    public function getTotalTimeSpentOnTicket(TicketInterface $ticket): int;

    public function startTicketWork(TicketInterface $ticket, WorkerInterface $worker): TicketRegisteredTimeInterface;

    public function stopTicketWork(TicketInterface $ticket, WorkerInterface $worker): TicketRegisteredTimeInterface;

    public function registerManualTimeEntry(
        TicketInterface $ticket,
        WorkerInterface $worker,
        int $minutes,
        bool $isPhoneCall,
    ): void;

    public function closeTicket(
        TicketInterface $ticket,
        WorkerInterface $worker,
        ?\DateTimeImmutable $closedAt = null,
    ): TicketInterface;

    /**
     * @return TicketInterface[]
     */
    public function getTicketsInProgress(WorkerInterface $worker): array;

    public function calculateWorkerEfficiency(
        WorkerInterface $worker,
        TicketCategoryInterface $category,
        ?\DateTimeImmutable $fromDate = null,
        ?\DateTimeImmutable $toDate = null,
    ): float;
}
