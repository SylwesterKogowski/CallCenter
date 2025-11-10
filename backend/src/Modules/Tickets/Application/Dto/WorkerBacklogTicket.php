<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\TicketInterface;

final class WorkerBacklogTicket implements WorkerBacklogTicketInterface
{
    public function __construct(
        private readonly TicketInterface $ticket,
        private readonly ClientInterface $client,
        private readonly TicketCategoryInterface $category,
        private readonly string $priority,
        private readonly int $estimatedTimeMinutes,
        private readonly \DateTimeInterface $createdAt,
        private readonly ?\DateTimeInterface $scheduledDate,
    ) {
    }

    public function getTicket(): TicketInterface
    {
        return $this->ticket;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function getCategory(): TicketCategoryInterface
    {
        return $this->category;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getEstimatedTimeMinutes(): int
    {
        return $this->estimatedTimeMinutes;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getScheduledDate(): ?\DateTimeInterface
    {
        return $this->scheduledDate;
    }
}
