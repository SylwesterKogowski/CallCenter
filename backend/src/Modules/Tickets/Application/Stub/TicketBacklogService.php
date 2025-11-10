<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\Tickets\Application\Dto\WorkerBacklogFilters;
use App\Modules\Tickets\Application\Dto\WorkerBacklogResultInterface;
use App\Modules\Tickets\Application\TicketBacklogServiceInterface;

final class TicketBacklogService implements TicketBacklogServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function getWorkerBacklog(string $workerId, WorkerBacklogFilters $filters): WorkerBacklogResultInterface
    {
        return $this->notImplemented(__METHOD__);
    }
}
