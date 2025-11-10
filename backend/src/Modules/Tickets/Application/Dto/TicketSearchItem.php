<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\Tickets\Domain\TicketInterface;

final class TicketSearchItem implements TicketSearchItemInterface
{
    public function __construct(
        private readonly TicketInterface $ticket,
        private readonly ClientInterface $client,
        private readonly int $timeSpentMinutes,
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

    public function getTimeSpentMinutes(): int
    {
        return $this->timeSpentMinutes;
    }
}
