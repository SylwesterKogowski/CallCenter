<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

interface WorkerBacklogResultInterface
{
    /**
     * @return iterable<WorkerBacklogTicketInterface>
     */
    public function getTickets(): iterable;

    public function getTotal(): int;
}
