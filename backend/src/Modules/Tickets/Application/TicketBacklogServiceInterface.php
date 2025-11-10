<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Tickets\Application\Dto\WorkerBacklogFilters;
use App\Modules\Tickets\Application\Dto\WorkerBacklogResultInterface;

interface TicketBacklogServiceInterface
{
    public function getWorkerBacklog(string $workerId, WorkerBacklogFilters $filters): WorkerBacklogResultInterface;
}
