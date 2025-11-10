<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;

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

    public function getWorkerTimeSpentOnTicket(
        TicketInterface $ticket,
        WorkerInterface $worker,
    ): int;

    public function registerManualTimeEntry(
        TicketInterface $ticket,
        WorkerInterface $worker,
        int $minutes,
        bool $isPhoneCall,
    ): void;
}

