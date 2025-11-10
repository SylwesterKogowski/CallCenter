<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Auth;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class AuthControllerTest extends BackendForFrontendTestCase
{
    public function testRegisterValidatesPayload(): void
    {
        $client = $this->createClientWithMocks();

        $client->jsonRequest(
            'POST',
            '/api/auth/register',
            [
                'login' => '',
                'password' => 'short',
                'categoryIds' => [],
                'isManager' => 'invalid',
            ],
        );

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('login', $data['errors']);
        self::assertArrayHasKey('password', $data['errors']);
        self::assertArrayHasKey('categoryIds', $data['errors']);
        self::assertArrayHasKey('isManager', $data['errors']);
    }

    public function testRegisterRejectsNonExistingCategories(): void
    {
        $client = $this->createClientWithMocks();

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-1',
            'getName' => 'Category 1',
            'getDescription' => null,
            'getDefaultResolutionTimeMinutes' => 30,
        ]);

        $this->ticketCategoryService
            ->expects(self::once())
            ->method('getCategoriesByIds')
            ->with(['cat-1', 'cat-2'])
            ->willReturn([$category]);

        $client->jsonRequest(
            'POST',
            '/api/auth/register',
            [
                'login' => 'valid.login',
                'password' => 'validPassword',
                'categoryIds' => ['cat-1', 'cat-2'],
                'isManager' => false,
            ],
        );

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Niektóre kategorie nie istnieją', $data['message'] ?? null);
        self::assertArrayHasKey('categoryIds', $data['errors'] ?? []);
    }

    public function testRegisterForbidsNonManagerToElevateRole(): void
    {
        $workerProvider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $client = $this->createClientWithMocks($workerProvider);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-1',
            'getName' => 'Category 1',
            'getDescription' => null,
            'getDefaultResolutionTimeMinutes' => 30,
        ]);

        $this->ticketCategoryService
            ->method('getCategoriesByIds')
            ->willReturn([$category]);

        $this->authenticationService
            ->expects(self::never())
            ->method('registerWorker');

        $client->jsonRequest(
            'POST',
            '/api/auth/register',
            [
                'login' => 'valid.login',
                'password' => 'validPassword',
                'categoryIds' => ['cat-1'],
                'isManager' => true,
            ],
        );

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień', $data['message'] ?? null);
    }

    public function testRegisterReturnsWorkerAndCategoriesStructure(): void
    {
        $workerProvider = $this->stubAuthenticatedWorkerProvider(
            $this->createManagerFixture(),
        );

        $client = $this->createClientWithMocks($workerProvider);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-1',
            'getName' => 'Support',
            'getDescription' => null,
            'getDefaultResolutionTimeMinutes' => 30,
        ]);

        $this->ticketCategoryService
            ->method('getCategoriesByIds')
            ->willReturn([$category]);

        $worker = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-123',
            'getLogin' => 'valid.login',
            'isManager' => false,
            'getCreatedAt' => new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        ]);

        $this->authenticationService
            ->expects(self::once())
            ->method('registerWorker')
            ->with('valid.login', 'validPassword')
            ->willReturn($worker);

        $this->authorizationService
            ->expects(self::once())
            ->method('assignCategoriesToWorker')
            ->with('worker-123', ['cat-1']);

        $this->authorizationService
            ->expects(self::once())
            ->method('setManagerRole')
            ->with('worker-123');

        $client->jsonRequest(
            'POST',
            '/api/auth/register',
            [
                'login' => 'valid.login',
                'password' => 'validPassword',
                'categoryIds' => ['cat-1'],
                'isManager' => true,
            ],
        );

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            [
                'id' => 'cat-1',
                'name' => 'Support',
            ],
        ], $data['categories'] ?? null);

        self::assertSame('worker-123', $data['worker']['id'] ?? null);
        self::assertSame('valid.login', $data['worker']['login'] ?? null);
        self::assertTrue($data['worker']['isManager'] ?? false);
        self::assertSame('2024-01-01T10:00:00+00:00', $data['worker']['createdAt'] ?? null);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $workerProvider = $this->createMock(AuthenticatedWorkerProvider::class);
        $workerProvider
            ->expects(self::never())
            ->method('startSession');
        $workerProvider
            ->method('getAuthenticatedWorker')
            ->willReturn($this->createAuthenticatedWorkerFixture());

        $client = $this->createClientWithMocks($workerProvider);

        $this->authenticationService
            ->method('authenticateWorker')
            ->willReturn(null);

        $client->jsonRequest(
            'POST',
            '/api/auth/login',
            [
                'login' => 'worker.login',
                'password' => 'invalidPass',
            ],
        );

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Nieprawidłowy login lub hasło', $data['message'] ?? null);

        $request = $client->getRequest();
        self::assertTrue($request->hasSession());
        self::assertFalse($request->getSession()->isStarted());
    }

    public function testLoginReturnsPayloadAndStartsSession(): void
    {
        $workerProvider = $this->createMock(AuthenticatedWorkerProvider::class);
        $workerProvider
            ->expects(self::once())
            ->method('startSession')
            ->with('worker-1');

        $worker = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-1',
            'getLogin' => 'worker.login',
            'isManager' => false,
            'getCreatedAt' => new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        ]);

        $this->authenticationService
            ->method('authenticateWorker')
            ->willReturn($worker);

        $client = $this->createClientWithMocks($workerProvider);

        $client->jsonRequest(
            'POST',
            '/api/auth/login',
            [
                'login' => 'worker.login',
                'password' => 'validPassword',
            ],
        );

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('worker-1', $data['worker']['id'] ?? null);
        self::assertSame('worker.login', $data['worker']['login'] ?? null);
        self::assertSame('2024-01-01T00:00:00+00:00', $data['worker']['createdAt'] ?? null);

        $token = $data['session']['token'] ?? '';
        self::assertIsString($token);
        self::assertSame(64, strlen($token));

        $expiresAt = $data['session']['expiresAt'] ?? '';
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, (string) $expiresAt));

        $request = $client->getRequest();
        self::assertTrue($request->hasSession());
        $session = $request->getSession();
        $sessionValue = $session->get('worker_session_token');
        self::assertIsArray($sessionValue);
        self::assertSame($token, $sessionValue['token'] ?? null);
        self::assertInstanceOf(\DateTimeImmutable::class, $sessionValue['expires_at'] ?? null);
    }
}
