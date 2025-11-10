<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Clients;

use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Clients\WorkerClientsController;
use App\Modules\Clients\Application\Dto\ClientSearchItemInterface;
use App\Modules\Clients\Application\Dto\ClientSearchResultInterface;
use App\Modules\Clients\Domain\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerClientsControllerTest extends BackendForFrontendTestCase
{
    public function testSearchRequiresAuthenticatedWorker(): void
    {
        $provider = $this->createMock(AuthenticatedWorkerProvider::class);
        $provider
            ->expects(self::once())
            ->method('getAuthenticatedWorker')
            ->willThrowException(new AuthenticationException('Brak aktywnej sesji pracownika'));

        $this->clientSearchService
            ->expects(self::never())
            ->method('searchClients');

        $this->createClientWithMocks($provider);

        /** @var WorkerClientsController $controller */
        $controller = static::getContainer()->get(WorkerClientsController::class);

        $response = $controller->search(Request::create(
            '/api/worker/clients/search',
            Request::METHOD_GET,
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Brak aktywnej sesji pracownika', $data['message'] ?? null);
    }

    public function testSearchValidatesQueryParameters(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->clientSearchService
            ->expects(self::never())
            ->method('searchClients');

        $this->createClientWithMocks($provider);

        /** @var WorkerClientsController $controller */
        $controller = static::getContainer()->get(WorkerClientsController::class);

        $request = Request::create(
            '/api/worker/clients/search',
            Request::METHOD_GET,
            [
                'query' => str_repeat('a', 300),
                'limit' => 'not-a-number',
            ],
        );

        $response = $controller->search($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertSame(
            'Fraza wyszukiwania jest zbyt długa',
            $data['errors']['query'][0] ?? null,
        );
    }

    public function testSearchReturnsPaginatedSearchResultsStructure(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-1',
            'getFirstName' => 'Alice',
            'getLastName' => 'Smith',
            'getEmail' => 'alice@example.com',
            'getPhone' => '123456789',
        ]);

        $item = $this->createMock(ClientSearchItemInterface::class);
        $item
            ->method('getClient')
            ->willReturn($client);
        $item
            ->method('getMatchScore')
            ->willReturn(0.85);

        $result = $this->createMock(ClientSearchResultInterface::class);
        $result
            ->method('getClients')
            ->willReturn([$item]);
        $result
            ->method('getTotal')
            ->willReturn(1);

        $this->clientSearchService
            ->expects(self::once())
            ->method('searchClients')
            ->with('Alice', 25)
            ->willReturn($result);

        $this->createClientWithMocks($provider);

        /** @var WorkerClientsController $controller */
        $controller = static::getContainer()->get(WorkerClientsController::class);

        $request = Request::create(
            '/api/worker/clients/search',
            Request::METHOD_GET,
            [
                'query' => '  Alice  ',
                'limit' => '25',
            ],
        );

        $response = $controller->search($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(1, $data['total'] ?? null);
        self::assertSame([
            [
                'id' => 'client-1',
                'name' => 'Alice Smith',
                'email' => 'alice@example.com',
                'phone' => '123456789',
                'matchScore' => 0.85,
            ],
        ], $data['clients'] ?? null);
    }

    public function testSearchSkipsLookupWhenQueryIsEmpty(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->clientSearchService
            ->expects(self::never())
            ->method('searchClients');

        $this->createClientWithMocks($provider);

        /** @var WorkerClientsController $controller */
        $controller = static::getContainer()->get(WorkerClientsController::class);

        $response = $controller->search(Request::create(
            '/api/worker/clients/search',
            Request::METHOD_GET,
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([], $data['clients'] ?? null);
        self::assertSame(0, $data['total'] ?? null);
    }
}
