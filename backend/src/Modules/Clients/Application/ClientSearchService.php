<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Application\Dto\ClientSearchItem;
use App\Modules\Clients\Application\Dto\ClientSearchItemInterface;
use App\Modules\Clients\Application\Dto\ClientSearchResult;
use App\Modules\Clients\Application\Dto\ClientSearchResultInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\Clients\Domain\ClientRepositoryInterface;

final class ClientSearchService implements ClientSearchServiceInterface
{
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function searchClients(string $query, int $limit): ClientSearchResultInterface
    {
        $normalizedQuery = $this->normalizeQuery($query);
        $normalizedLimit = $this->normalizeLimit($limit);

        if ('' === $normalizedQuery) {
            return new ClientSearchResult([], 0);
        }

        $results = $this->clientRepository->search($normalizedQuery, $normalizedLimit);

        $items = array_map(
            /**
             * @param array{client: ClientInterface, matchScore: float|null} $row
             */
            static function (array $row): ClientSearchItemInterface {
                return new ClientSearchItem($row['client'], $row['matchScore']);
            },
            $results,
        );

        return new ClientSearchResult($items, count($items));
    }

    private function normalizeQuery(string $query): string
    {
        return trim($query);
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit <= 0) {
            return 1;
        }

        if ($limit > self::MAX_LIMIT) {
            return self::MAX_LIMIT;
        }

        return $limit;
    }
}
