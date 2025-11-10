<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\TicketInterface;

interface WorkerBacklogTicketInterface
{
    public function getTicket(): TicketInterface;

    public function getClient(): ClientInterface;

    public function getCategory(): TicketCategoryInterface;

    public function getPriority(): string;

    public function getEstimatedTimeMinutes(): int;

    public function getCreatedAt(): \DateTimeInterface;

    public function getScheduledDate(): ?\DateTimeInterface;
}
