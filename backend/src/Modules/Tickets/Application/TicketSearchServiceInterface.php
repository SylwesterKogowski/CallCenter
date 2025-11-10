<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Tickets\Application\Dto\TicketSearchFilters;
use App\Modules\Tickets\Application\Dto\TicketSearchResultInterface;

interface TicketSearchServiceInterface
{
    public function searchWorkerTickets(string $workerId, TicketSearchFilters $filters): TicketSearchResultInterface;
}
