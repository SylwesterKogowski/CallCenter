<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

interface TicketSearchResultInterface
{
    /**
     * @return iterable<TicketSearchItemInterface>
     */
    public function getTickets(): iterable;

    public function getTotal(): int;
}


