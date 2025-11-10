<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Tickets\Application\Dto\WorkerBacklogFilters;
use App\Modules\Tickets\Application\Dto\WorkerBacklogResult;
use App\Modules\Tickets\Application\Dto\WorkerBacklogResultInterface;
use App\Modules\Tickets\Application\Dto\WorkerBacklogTicket;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;

final class TicketBacklogService implements TicketBacklogServiceInterface
{
    public function __construct(
        private readonly TicketRepositoryInterface $repository,
    ) {
    }

    public function getWorkerBacklog(string $workerId, WorkerBacklogFilters $filters): WorkerBacklogResultInterface
    {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');

        $criteria = [];

        $statuses = $this->normalizeArray($filters->getStatuses());
        if (!empty($statuses)) {
            $criteria['statuses'] = $statuses;
        }

        $categoryIds = $this->normalizeArray($filters->getCategories());
        if (!empty($categoryIds)) {
            $criteria['category_ids'] = $categoryIds;
        }

        $tickets = $this->repository->getWorkerBacklog($normalizedWorkerId, $criteria);
        $items = [];

        foreach ($tickets as $ticket) {
            $items[] = new WorkerBacklogTicket(
                $ticket,
                $ticket->getClient(),
                $ticket->getCategory(),
                $this->determinePriority($ticket),
                $ticket->getCategory()->getDefaultResolutionTimeMinutes(),
                $ticket->getCreatedAt(),
                null,
            );
        }

        return new WorkerBacklogResult($items, \count($items));
    }

    /**
     * @param string[] $values
     *
     * @return string[]
     */
    private function normalizeArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $candidate = trim((string) $value);

            if ('' === $candidate) {
                continue;
            }

            $normalized[] = $candidate;
        }

        return $normalized;
    }

    private function determinePriority(TicketInterface $ticket): string
    {
        return match ($ticket->getStatus()) {
            TicketInterface::STATUS_IN_PROGRESS => 'high',
            TicketInterface::STATUS_AWAITING_CUSTOMER => 'low',
            TicketInterface::STATUS_AWAITING_RESPONSE => 'medium',
            default => 'medium',
        };
    }

    private function normalizeId(string $value, string $errorMessage): string
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $normalized;
    }
}
