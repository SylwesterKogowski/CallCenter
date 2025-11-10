<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Phone;

use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Phone\WorkerPhoneController;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerPhoneControllerTest extends BackendForFrontendTestCase
{
    public function testStartCallRequiresAuthenticatedWorker(): void
    {
        $provider = $this->createMock(AuthenticatedWorkerProvider::class);
        $provider
            ->expects(self::once())
            ->method('getAuthenticatedWorker')
            ->willThrowException(new AuthenticationException('Brak aktywnej sesji pracownika'));

        $this->workerPhoneService
            ->expects(self::never())
            ->method('startCall');

        $this->createClientWithMocks($provider);

        /** @var WorkerPhoneController $controller */
        $controller = static::getContainer()->get(WorkerPhoneController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/phone/receive',
            ['workerId' => 'worker-id'],
        );

        $response = $controller->startCall($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Brak aktywnej sesji pracownika', $data['message'] ?? null);
    }

    public function testStartCallDelegatesToWorkerPhoneService(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerPhoneService
            ->expects(self::once())
            ->method('startCall')
            ->with('worker-id')
            ->willReturn([
                'callId' => 'call-123',
                'startTime' => new DateTimeImmutable('2024-07-01T09:00:00+00:00'),
                'pausedTickets' => [],
            ]);

        $this->createClientWithMocks($provider);

        /** @var WorkerPhoneController $controller */
        $controller = static::getContainer()->get(WorkerPhoneController::class);

        $response = $controller->startCall($this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/phone/receive',
            ['workerId' => 'worker-id'],
        ));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testStartCallReturnsCallSessionPayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerPhoneService
            ->expects(self::once())
            ->method('startCall')
            ->with('worker-id')
            ->willReturn([
                'callId' => 'call-456',
                'startTime' => new DateTimeImmutable('2024-07-02T11:15:00+00:00'),
                'pausedTickets' => [
                    [
                        'ticketId' => 'ticket-1',
                        'previousStatus' => 'in_progress',
                        'newStatus' => 'waiting',
                    ],
                    [
                        'ticketId' => 'ticket-2',
                        'previousStatus' => 'in_progress',
                        'newStatus' => 'waiting',
                    ],
                ],
            ]);

        $this->createClientWithMocks($provider);

        /** @var WorkerPhoneController $controller */
        $controller = static::getContainer()->get(WorkerPhoneController::class);

        $response = $controller->startCall($this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/phone/receive',
            ['workerId' => 'worker-id'],
        ));

        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            'callId' => 'call-456',
            'startTime' => '2024-07-02T11:15:00+00:00',
            'pausedTickets' => [
                [
                    'ticketId' => 'ticket-1',
                    'previousStatus' => 'in_progress',
                    'newStatus' => 'waiting',
                ],
                [
                    'ticketId' => 'ticket-2',
                    'previousStatus' => 'in_progress',
                    'newStatus' => 'waiting',
                ],
            ],
        ], $data);
    }

    public function testEndCallRequiresWorkerAndValidatesPayload(): void
    {
        $provider = $this->createMock(AuthenticatedWorkerProvider::class);
        $provider
            ->expects(self::once())
            ->method('getAuthenticatedWorker')
            ->willReturn($this->createAuthenticatedWorkerFixture(false));

        $this->workerPhoneService
            ->expects(self::never())
            ->method('endCall');

        $this->createClientWithMocks($provider);

        /** @var WorkerPhoneController $controller */
        $controller = static::getContainer()->get(WorkerPhoneController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/phone/end',
            [
                'callId' => '',
                'ticketId' => null,
                'duration' => 120,
                'notes' => '',
                'startTime' => '',
                'endTime' => '',
            ],
        );

        $response = $controller->endCall($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('callId', $data['errors']);
        self::assertArrayHasKey('startTime', $data['errors']);
        self::assertArrayHasKey('endTime', $data['errors']);
    }

    public function testEndCallClosesSessionAndReturnsSummary(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $callStart = new DateTimeImmutable('2024-07-03T08:00:00+00:00');
        $callEnd = new DateTimeImmutable('2024-07-03T08:20:00+00:00');
        $ticketUpdated = new DateTimeImmutable('2024-07-03T09:00:00+00:00');
        $previousUpdated = new DateTimeImmutable('2024-07-02T17:30:00+00:00');
        $scheduledDate = new DateTimeImmutable('2024-07-04');

        $this->workerPhoneService
            ->expects(self::once())
            ->method('endCall')
            ->with(
                'worker-id',
                'call-789',
                'ticket-456',
                320,
                null,
                self::callback(static fn (DateTimeImmutable $start): bool => '2024-07-03T08:00:00+00:00' === $start->format(DATE_ATOM)),
                self::callback(static fn (DateTimeImmutable $end): bool => '2024-07-03T08:20:00+00:00' === $end->format(DATE_ATOM)),
            )
            ->willReturn([
                'call' => [
                    'id' => 'call-789',
                    'ticketId' => 'ticket-456',
                    'duration' => 320,
                    'startTime' => $callStart,
                    'endTime' => $callEnd,
                ],
                'ticket' => [
                    'id' => 'ticket-456',
                    'status' => 'in_progress',
                    'timeSpent' => 180,
                    'scheduledDate' => $scheduledDate,
                    'updatedAt' => $ticketUpdated,
                ],
                'previousTicket' => [
                    'id' => 'ticket-123',
                    'status' => 'waiting',
                    'updatedAt' => $previousUpdated,
                ],
            ]);

        $this->createClientWithMocks($provider);

        /** @var WorkerPhoneController $controller */
        $controller = static::getContainer()->get(WorkerPhoneController::class);

        $response = $controller->endCall($this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/phone/end',
            [
                'callId' => 'call-789',
                'ticketId' => '   ticket-456  ',
                'duration' => 320,
                'notes' => '   ',
                'startTime' => '2024-07-03T08:00:00+00:00',
                'endTime' => '2024-07-03T08:20:00+00:00',
            ],
        ));

        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'call' => [
                'id' => 'call-789',
                'ticketId' => 'ticket-456',
                'duration' => 320,
                'startTime' => '2024-07-03T08:00:00+00:00',
                'endTime' => '2024-07-03T08:20:00+00:00',
            ],
            'ticket' => [
                'id' => 'ticket-456',
                'status' => 'in_progress',
                'timeSpent' => 180,
                'updatedAt' => '2024-07-03T09:00:00+00:00',
                'scheduledDate' => '2024-07-04',
            ],
            'previousTicket' => [
                'id' => 'ticket-123',
                'status' => 'waiting',
                'updatedAt' => '2024-07-02T17:30:00+00:00',
            ],
        ], $data);
    }
}
