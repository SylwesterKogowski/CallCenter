<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\Tickets\Application\Dto\TicketSearchFilters;
use App\Modules\Tickets\Application\Dto\TicketSearchResultInterface;
use App\Modules\Tickets\Application\TicketSearchServiceInterface;

final class TicketSearchService implements TicketSearchServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function searchWorkerTickets(string $workerId, TicketSearchFilters $filters): TicketSearchResultInterface
    {
        return $this->notImplemented(__METHOD__);
    }
}


