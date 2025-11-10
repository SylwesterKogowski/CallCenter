<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Stub;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;

final class TicketService implements TicketServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function createTicket(
        string $id,
        ClientInterface $client,
        TicketCategoryInterface $category,
        ?string $title = null,
        ?string $description = null,
    ): TicketInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function getTicketById(string $id): ?TicketInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getTicketMessages(TicketInterface $ticket): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function addMessageToTicket(
        TicketInterface $ticket,
        string $content,
        string $senderType,
        ?string $senderId = null,
        ?string $senderName = null,
    ): TicketMessageInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function updateTicketStatus(TicketInterface $ticket, string $status): TicketInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function addTicketNote(
        TicketInterface $ticket,
        WorkerInterface $worker,
        string $note,
    ): TicketNoteInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function getTicketNotes(TicketInterface $ticket): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getWorkerTimeSpentOnTicket(TicketInterface $ticket, WorkerInterface $worker): int
    {
        return $this->notImplemented(__METHOD__);
    }

    public function registerManualTimeEntry(
        TicketInterface $ticket,
        WorkerInterface $worker,
        int $minutes,
        bool $isPhoneCall,
    ): void {
        $this->notImplemented(__METHOD__);
    }
}


