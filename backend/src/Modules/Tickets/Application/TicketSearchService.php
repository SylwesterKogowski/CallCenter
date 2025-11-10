<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application;

use App\Modules\Tickets\Application\Dto\TicketSearchFilters;
use App\Modules\Tickets\Application\Dto\TicketSearchItem;
use App\Modules\Tickets\Application\Dto\TicketSearchItemInterface;
use App\Modules\Tickets\Application\Dto\TicketSearchResult;
use App\Modules\Tickets\Application\Dto\TicketSearchResultInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;

final class TicketSearchService implements TicketSearchServiceInterface
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly TicketRepositoryInterface $repository,
    ) {
    }

    public function searchWorkerTickets(string $workerId, TicketSearchFilters $filters): TicketSearchResultInterface
    {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');
        $limit = $this->normalizeLimit($filters->limit);

        $criteria = [
            'status' => $this->normalizeNullableString($filters->status),
            'category_id' => $this->normalizeNullableString($filters->categoryId),
            'query' => $this->normalizeNullableString($filters->query),
        ];

        $result = $this->repository->searchWorkerTickets($normalizedWorkerId, $criteria, $limit, 0);
        $items = [];

        foreach ($result['tickets'] as $ticket) {
            $items[] = $this->mapToItem($ticket, $normalizedWorkerId);
        }

        return new TicketSearchResult($items, $result['total']);
    }

    private function mapToItem(TicketInterface $ticket, string $workerId): TicketSearchItemInterface
    {
        $timeSpent = $this->repository->getWorkerTimeSpentOnTicket($ticket->getId(), $workerId);

        return new TicketSearchItem(
            $ticket,
            $ticket->getClient(),
            $timeSpent,
        );
    }

    private function normalizeId(string $value, string $errorMessage): string
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $normalized;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit <= 0) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }
}
