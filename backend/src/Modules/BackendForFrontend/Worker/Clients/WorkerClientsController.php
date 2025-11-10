<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Clients;

use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Clients\Dto\SearchWorkerClientsQuery;
use App\Modules\Clients\Application\ClientSearchServiceInterface;
use App\Modules\Clients\Application\Dto\ClientSearchItemInterface;
use App\Modules\Clients\Domain\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[RequiresWorker]
#[Route(path: '/api/worker/clients', name: 'backend_for_frontend_worker_clients_')]
final class WorkerClientsController extends AbstractJsonController
{
    private const DEFAULT_SEARCH_LIMIT = 10;

    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private readonly AuthenticatedWorkerProvider $workerProvider,
        private readonly ClientSearchServiceInterface $clientSearchService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '/search', name: 'search', methods: [Request::METHOD_GET])]
    public function search(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $this->workerProvider->getAuthenticatedWorker();
            $queryDto = $this->hydrateQuery($request);
            $this->validateDto($queryDto);

            if (null === $queryDto->query) {
                return [
                    'clients' => [],
                    'total' => 0,
                ];
            }

            $result = $this->clientSearchService->searchClients(
                $queryDto->query,
                $queryDto->limit,
            );

            $clients = [];

            foreach ($result->getClients() as $item) {
                $clients[] = $this->formatSearchItem($item);
            }

            return [
                'clients' => $clients,
                'total' => $result->getTotal(),
            ];
        });
    }

    private function hydrateQuery(Request $request): SearchWorkerClientsQuery
    {
        $query = $this->trimString($request->query->get('query'));

        $limit = $this->normalizeLimit(
            $request->query->get('limit'),
            self::DEFAULT_SEARCH_LIMIT,
        );

        return new SearchWorkerClientsQuery(
            query: $query,
            limit: $limit,
        );
    }

    private function normalizeLimit(mixed $value, int $default): int
    {
        if (null === $value) {
            return $default;
        }

        if (is_numeric($value)) {
            $limit = (int) round((float) $value);

            return max(1, min($limit, 100));
        }

        return $default;
    }

    private function trimString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @return array<string, string|int|float>
     */
    private function formatSearchItem(ClientSearchItemInterface $item): array
    {
        $client = $item->getClient();

        return array_filter(
            [
                'id' => $client->getId(),
                'name' => $this->formatClientName($client),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
                'matchScore' => $item->getMatchScore(),
            ],
            static fn (mixed $value): bool => null !== $value,
        );
    }

    private function formatClientName(ClientInterface $client): string
    {
        $parts = array_filter([$client->getFirstName(), $client->getLastName()]);

        if ([] !== $parts) {
            return implode(' ', $parts);
        }

        return $client->getEmail()
            ?? $client->getPhone()
            ?? 'Klient';
    }
}
