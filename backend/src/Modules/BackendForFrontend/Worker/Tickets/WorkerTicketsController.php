<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Tickets;

use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\ResourceNotFoundException;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Tickets\Dto\CreateWorkerTicketClientDto;
use App\Modules\BackendForFrontend\Worker\Tickets\Dto\CreateWorkerTicketRequest;
use App\Modules\BackendForFrontend\Worker\Tickets\Dto\SearchWorkerTicketsQuery;
use App\Modules\Clients\Application\ClientServiceInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Dto\TicketSearchFilters;
use App\Modules\Tickets\Application\Dto\TicketSearchItemInterface;
use App\Modules\Tickets\Application\TicketSearchServiceInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[RequiresWorker]
#[Route(path: '/api/worker/tickets', name: 'backend_for_frontend_worker_tickets_')]
final class WorkerTicketsController extends AbstractJsonController
{
    private const DEFAULT_SEARCH_LIMIT = 20;

    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private readonly AuthenticatedWorkerProvider $workerProvider,
        private readonly TicketSearchServiceInterface $ticketSearchService,
        private readonly TicketServiceInterface $ticketService,
        private readonly TicketCategoryServiceInterface $ticketCategoryService,
        private readonly ClientServiceInterface $clientService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '/search', name: 'search', methods: [Request::METHOD_GET])]
    public function search(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $queryDto = $this->hydrateSearchQuery($request);
            $this->validateDto($queryDto);

            $this->assertCategoryAccess($worker, $queryDto->categoryId);

            $filters = new TicketSearchFilters(
                query: $queryDto->query,
                categoryId: $queryDto->categoryId,
                status: $queryDto->status,
                limit: $queryDto->limit,
            );

            $result = $this->ticketSearchService->searchWorkerTickets(
                $worker->getId(),
                $filters,
            );

            $tickets = [];

            foreach ($result->getTickets() as $item) {
                $tickets[] = $this->formatSearchItem($item);
            }

            return [
                'tickets' => $tickets,
                'total' => $result->getTotal(),
            ];
        });
    }

    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    public function create(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateCreateRequest($payload);
            $this->validateDto($dto);

            if (!$dto->hasClientReference()) {
                throw new ValidationException('Wybierz istniejącego klienta lub podaj dane nowego klienta', ['clientId' => ['Wymagane jest wskazanie klienta'], 'clientData' => ['Wymagane jest wskazanie klienta']]);
            }

            $this->assertCategoryAccess($worker, $dto->categoryId);

            $category = $this->resolveCategory($dto->categoryId);
            $client = $this->resolveClient($dto->clientId, $dto->clientData);

            $ticket = $this->ticketService->createTicket(
                Uuid::v4()->toRfc4122(),
                $client,
                $category,
                $this->normalizeTitle($dto->title),
            );

            return [
                'ticket' => $this->formatCreatedTicket($ticket, $client, $category),
            ];
        }, Response::HTTP_CREATED);
    }

    private function requireWorker(): AuthenticatedWorker
    {
        return $this->workerProvider->getAuthenticatedWorker();
    }

    private function hydrateSearchQuery(Request $request): SearchWorkerTicketsQuery
    {
        $query = $this->trimString($request->query->get('query'));
        $categoryId = $this->trimString($request->query->get('categoryId'));
        $status = $this->trimString($request->query->get('status'));
        $limit = $this->normalizeLimit(
            $request->query->get('limit'),
            self::DEFAULT_SEARCH_LIMIT,
        );

        return new SearchWorkerTicketsQuery(
            query: $query,
            categoryId: $categoryId,
            status: $status,
            limit: $limit,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateCreateRequest(array $payload): CreateWorkerTicketRequest
    {
        $clientData = null;

        if (isset($payload['clientData']) && is_array($payload['clientData'])) {
            $clientDataPayload = $payload['clientData'];
            $parsedNames = $this->splitClientName(
                isset($clientDataPayload['name']) ? (string) $clientDataPayload['name'] : null,
            );

            $clientData = new CreateWorkerTicketClientDto(
                firstName: $parsedNames['firstName'],
                lastName: $parsedNames['lastName'],
                email: isset($clientDataPayload['email']) ? $this->trimNullableString($clientDataPayload['email']) : null,
                phone: isset($clientDataPayload['phone']) ? $this->trimNullableString($clientDataPayload['phone']) : null,
            );
        }

        return new CreateWorkerTicketRequest(
            categoryId: (string) ($payload['categoryId'] ?? ''),
            title: isset($payload['title']) ? $this->trimNullableString($payload['title']) : null,
            clientId: isset($payload['clientId']) ? $this->trimNullableString($payload['clientId']) : null,
            clientData: $clientData,
        );
    }

    private function assertCategoryAccess(AuthenticatedWorker $worker, ?string $categoryId): void
    {
        if (null === $categoryId || '' === $categoryId || $worker->isManager()) {
            return;
        }

        if (!in_array($categoryId, $worker->getCategoryIds(), true)) {
            throw new AccessDeniedException('Brak uprawnień do wybranej kategorii', ['categoryId' => $categoryId]);
        }
    }

    private function resolveCategory(string $categoryId): TicketCategoryInterface
    {
        $category = $this->ticketCategoryService->getCategoriesByIds([$categoryId])[0] ?? null;

        if (null === $category) {
            throw new ResourceNotFoundException('Kategoria nie została znaleziona');
        }

        return $category;
    }

    private function resolveClient(?string $clientId, ?CreateWorkerTicketClientDto $clientData): ClientInterface
    {
        if (null !== $clientId && '' !== $clientId) {
            $client = $this->clientService->getClientById($clientId);

            if (null === $client) {
                throw new ResourceNotFoundException('Klient nie został znaleziony');
            }

            return $client;
        }

        if (null === $clientData) {
            throw new ValidationException('Dane klienta są wymagane', ['clientData' => ['Dane klienta są wymagane']]);
        }

        if (!$clientData->hasContactData()) {
            throw new ValidationException('Podaj dane kontaktowe klienta (email lub telefon)', ['clientData.email' => ['Podaj email lub numer telefonu'], 'clientData.phone' => ['Podaj email lub numer telefonu']]);
        }

        $matched = null;

        if (null !== $clientData->email) {
            $matched = $this->clientService->findClientByEmail($clientData->email);
        }

        if (null === $matched && null !== $clientData->phone) {
            $matched = $this->clientService->findClientByPhone($clientData->phone);
        }

        if (null !== $matched) {
            return $matched;
        }

        return $this->clientService->createClient(
            Uuid::v4()->toRfc4122(),
            $clientData->email,
            $clientData->phone,
            $clientData->firstName,
            $clientData->lastName,
        );
    }

    private function normalizeTitle(?string $title): ?string
    {
        if (null === $title) {
            return null;
        }

        $trimmed = trim($title);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     category: array{id: string, name: string},
     *     client: array{id: string, name: string, email: ?string, phone: ?string},
     *     createdAt: string,
     *     timeSpent: int
     * }
     */
    private function formatCreatedTicket(
        TicketInterface $ticket,
        ClientInterface $client,
        TicketCategoryInterface $category,
    ): array {
        return [
            'id' => $ticket->getId(),
            'title' => $this->normalizeTicketTitle($ticket),
            'status' => $ticket->getStatus(),
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
            ],
            'client' => [
                'id' => $client->getId(),
                'name' => $this->formatClientName($client),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
            ],
            'createdAt' => $ticket->getCreatedAt()->format(DATE_ATOM),
            'timeSpent' => 0,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     category: array{id: string, name: string},
     *     client: array{id: string, name: string, email: ?string, phone: ?string},
     *     createdAt: string,
     *     timeSpent: int
     * }
     */
    private function formatSearchItem(TicketSearchItemInterface $item): array
    {
        $ticket = $item->getTicket();
        $client = $item->getClient();
        $category = $ticket->getCategory();

        return [
            'id' => $ticket->getId(),
            'title' => $this->normalizeTicketTitle($ticket),
            'status' => $ticket->getStatus(),
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
            ],
            'client' => [
                'id' => $client->getId(),
                'name' => $this->formatClientName($client),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
            ],
            'createdAt' => $ticket->getCreatedAt()->format(DATE_ATOM),
            'timeSpent' => $item->getTimeSpentMinutes(),
        ];
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

    /**
     * @return array{firstName: ?string, lastName: ?string}
     */
    private function splitClientName(?string $value): array
    {
        if (null === $value) {
            return ['firstName' => null, 'lastName' => null];
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            return ['firstName' => null, 'lastName' => null];
        }

        $segments = preg_split('/\s+/', $trimmed, 2) ?: [];

        $firstName = $segments[0] ?? null;
        $lastName = $segments[1] ?? null;

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];
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

    private function trimNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function normalizeTicketTitle(TicketInterface $ticket): string
    {
        $title = $ticket->getTitle();

        if (null === $title || '' === trim($title)) {
            return sprintf('Ticket %s', $ticket->getId());
        }

        return $title;
    }
}
