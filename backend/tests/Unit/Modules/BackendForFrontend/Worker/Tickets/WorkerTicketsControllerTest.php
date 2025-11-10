<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Tickets;

use App\Modules\BackendForFrontend\Worker\Tickets\WorkerTicketsController;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Dto\TicketSearchFilters;
use App\Modules\Tickets\Application\Dto\TicketSearchItemInterface;
use App\Modules\Tickets\Application\Dto\TicketSearchResultInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerTicketsControllerTest extends BackendForFrontendTestCase
{
    public function testSearchValidatesFiltersAndCategoryAccess(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false, ['category-allowed']),
        );

        $this->ticketSearchService
            ->expects(self::never())
            ->method('searchWorkerTickets');

        $this->createClientWithMocks($provider);

        /** @var WorkerTicketsController $controller */
        $controller = static::getContainer()->get(WorkerTicketsController::class);

        $request = Request::create(
            '/api/worker/tickets/search',
            Request::METHOD_GET,
            [
                'query' => str_repeat('x', 260),
                'categoryId' => 'category-forbidden',
                'status' => str_repeat('s', 60),
                'limit' => '50',
            ],
        );

        $response = $controller->search($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('query', $data['errors']);
        self::assertArrayHasKey('status', $data['errors']);

        $requestValid = Request::create(
            '/api/worker/tickets/search',
            Request::METHOD_GET,
            [
                'query' => 'Reset password',
                'categoryId' => 'category-forbidden',
                'status' => 'OPEN',
                'limit' => '10',
            ],
        );

        $responseForbidden = $controller->search($requestValid);
        $forbiddenData = json_decode((string) $responseForbidden->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $responseForbidden->getStatusCode());
        self::assertSame('Brak uprawnień do wybranej kategorii', $forbiddenData['message'] ?? null);
        self::assertSame('category-forbidden', $forbiddenData['categoryId'] ?? null);
    }

    public function testSearchReturnsNormalizedSearchResult(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false, ['category-1']),
        );

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'category-1',
            'getName' => 'Support',
        ]);

        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-1',
            'getFirstName' => 'Alice',
            'getLastName' => 'Smith',
            'getEmail' => 'alice@example.com',
            'getPhone' => '123456789',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-1',
            'getTitle' => 'Reset password issue',
            'getStatus' => 'OPEN',
            'getCategory' => $category,
            'getCreatedAt' => new \DateTimeImmutable('2024-07-10T12:00:00+00:00'),
        ]);

        $item = $this->createConfiguredMock(TicketSearchItemInterface::class, [
            'getTicket' => $ticket,
            'getClient' => $client,
            'getTimeSpentMinutes' => 15,
        ]);

        $result = $this->createMock(TicketSearchResultInterface::class);
        $result
            ->method('getTickets')
            ->willReturn([$item]);
        $result
            ->method('getTotal')
            ->willReturn(1);

        $this->ticketSearchService
            ->expects(self::once())
            ->method('searchWorkerTickets')
            ->with(
                'worker-id',
                self::callback(static function (TicketSearchFilters $filters): bool {
                    self::assertSame('Reset password', $filters->query);
                    self::assertSame('category-1', $filters->categoryId);
                    self::assertSame('OPEN', $filters->status);
                    self::assertSame(25, $filters->limit);

                    return true;
                }),
            )
            ->willReturn($result);

        $this->createClientWithMocks($provider);

        /** @var WorkerTicketsController $controller */
        $controller = static::getContainer()->get(WorkerTicketsController::class);

        $request = Request::create(
            '/api/worker/tickets/search',
            Request::METHOD_GET,
            [
                'query' => '  Reset password  ',
                'categoryId' => 'category-1',
                'status' => 'OPEN',
                'limit' => '25',
            ],
        );

        $response = $controller->search($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(1, $data['total'] ?? null);
        self::assertCount(1, $data['tickets'] ?? []);
        self::assertSame([
            'id' => 'ticket-1',
            'title' => 'Reset password issue',
            'status' => 'OPEN',
            'category' => [
                'id' => 'category-1',
                'name' => 'Support',
            ],
            'client' => [
                'id' => 'client-1',
                'name' => 'Alice Smith',
                'email' => 'alice@example.com',
                'phone' => '123456789',
            ],
            'createdAt' => '2024-07-10T12:00:00+00:00',
            'timeSpent' => 15,
        ], $data['tickets'][0] ?? null);
    }

    public function testCreateValidatesClientReferenceBeforeDelegation(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false, ['category-1']),
        );

        $this->ticketService
            ->expects(self::never())
            ->method('createTicket');

        $this->createClientWithMocks($provider);

        /** @var WorkerTicketsController $controller */
        $controller = static::getContainer()->get(WorkerTicketsController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets',
            [
                'categoryId' => 'category-1',
                'title' => '  New ticket  ',
            ],
        );

        $response = $controller->create($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Wybierz istniejącego klienta lub podaj dane nowego klienta', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('clientId', $data['errors']);
        self::assertArrayHasKey('clientData', $data['errors']);
    }

    public function testCreateReturnsCreatedTicketWithResolvedRelations(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false, ['category-1']),
        );

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'category-1',
            'getName' => 'Support',
        ]);

        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-1',
            'getFirstName' => 'Alice',
            'getLastName' => 'Smith',
            'getEmail' => 'alice@example.com',
            'getPhone' => '123456789',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-123',
            'getTitle' => 'New ticket',
            'getStatus' => 'NEW',
            'getCategory' => $category,
            'getCreatedAt' => new \DateTimeImmutable('2024-07-11T08:30:00+00:00'),
        ]);

        $this->ticketCategoryService
            ->expects(self::once())
            ->method('getCategoriesByIds')
            ->with(['category-1'])
            ->willReturn([$category]);

        $this->clientService
            ->expects(self::once())
            ->method('getClientById')
            ->with('client-1')
            ->willReturn($client);

        $this->ticketService
            ->expects(self::once())
            ->method('createTicket')
            ->with(
                self::callback(static function (string $id): bool {
                    self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id);

                    return true;
                }),
                self::identicalTo($client),
                self::identicalTo($category),
                'New ticket',
            )
            ->willReturn($ticket);

        $this->createClientWithMocks($provider);

        /** @var WorkerTicketsController $controller */
        $controller = static::getContainer()->get(WorkerTicketsController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets',
            [
                'categoryId' => 'category-1',
                'title' => '  New ticket  ',
                'clientId' => 'client-1',
            ],
        );

        $response = $controller->create($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            'ticket' => [
                'id' => 'ticket-123',
                'title' => 'New ticket',
                'status' => 'NEW',
                'category' => [
                    'id' => 'category-1',
                    'name' => 'Support',
                ],
                'client' => [
                    'id' => 'client-1',
                    'name' => 'Alice Smith',
                    'email' => 'alice@example.com',
                    'phone' => '123456789',
                ],
                'createdAt' => '2024-07-11T08:30:00+00:00',
                'timeSpent' => 0,
            ],
        ], $data);
    }
}
