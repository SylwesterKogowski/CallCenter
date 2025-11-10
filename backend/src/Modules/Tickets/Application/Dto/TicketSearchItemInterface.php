<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\Tickets\Domain\TicketInterface;

interface TicketSearchItemInterface
{
    public function getTicket(): TicketInterface;

    public function getClient(): ClientInterface;

    public function getTimeSpentMinutes(): int;
}
