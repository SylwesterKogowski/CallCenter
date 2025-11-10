<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Manager;

use App\Modules\BackendForFrontend\Manager\ManagerMonitoringController;
use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentSettingsInput;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class ManagerMonitoringControllerTest extends BackendForFrontendTestCase
{
    public function testMonitoringForbidsAccessForNonManager(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::never())
            ->method('getMonitoringData');

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/api/manager/monitoring',
            'GET',
            ['date' => '2024-05-01'],
        );

        $response = $controller->monitoring($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień', $data['message'] ?? null);
    }

    public function testMonitoringValidatesDateQueryParameter(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createManagerFixture(),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::never())
            ->method('getMonitoringData');

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/api/manager/monitoring',
            'GET',
            ['date' => 'invalid-date'],
        );

        $response = $controller->monitoring($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('date', $data['errors']);
    }

    public function testMonitoringReturnsNormalizedMonitoringPayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createManagerFixture(),
        );

        $this->createClientWithMocks($provider);

        $rawMonitoringData = [
            'date' => '2024-05-01',
            'summary' => [
                'totalTickets' => 25,
                'totalWorkers' => 3,
                'totalQueues' => 2,
                'averageWorkload' => 72.5,
                'averageResolutionTime' => 45,
                'waitingTicketsTotal' => 10,
                'inProgressTicketsTotal' => 8,
                'completedTicketsTotal' => 7,
            ],
            'workerStats' => [
                [
                    'workerId' => 'worker-1',
                    'workerLogin' => 'alice.login',
                    'ticketsCount' => 10,
                    'timeSpent' => 120,
                    'timePlanned' => 180,
                    'workloadLevel' => 'HIGH',
                    'efficiency' => 0.75,
                    'categories' => ['support', 'billing'],
                    'completedTickets' => 5,
                    'inProgressTickets' => 3,
                    'waitingTickets' => 2,
                ],
            ],
            'queueStats' => [
                [
                    'queueId' => 'queue-1',
                    'queueName' => 'Support',
                    'waitingTickets' => 4,
                    'inProgressTickets' => 3,
                    'completedTickets' => 3,
                    'totalTickets' => 10,
                    'averageResolutionTime' => 50,
                    'assignedWorkers' => 2,
                ],
            ],
            'autoAssignmentSettings' => [
                'enabled' => true,
                'lastRun' => new \DateTimeImmutable('2024-05-01T12:34:56+00:00'),
                'ticketsAssigned' => 6,
                'settings' => [
                    'considerEfficiency' => true,
                    'considerAvailability' => false,
                    'maxTicketsPerWorker' => 5,
                ],
            ],
        ];

        $this->managerMonitoringService
            ->expects(self::once())
            ->method('getMonitoringData')
            ->with(
                'worker-id',
                self::callback(static function (\DateTimeImmutable $date): bool {
                    return '2024-05-01' === $date->format('Y-m-d');
                }),
            )
            ->willReturn($rawMonitoringData);

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/api/manager/monitoring',
            'GET',
            ['date' => '2024-05-01'],
        );

        $response = $controller->monitoring($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'date' => '2024-05-01',
            'summary' => [
                'totalTickets' => 25,
                'totalWorkers' => 3,
                'totalQueues' => 2,
                'averageWorkload' => 72.5,
                'averageResolutionTime' => 45,
                'waitingTicketsTotal' => 10,
                'inProgressTicketsTotal' => 8,
                'completedTicketsTotal' => 7,
            ],
            'workerStats' => [
                [
                    'workerId' => 'worker-1',
                    'workerLogin' => 'alice.login',
                    'ticketsCount' => 10,
                    'timeSpent' => 120,
                    'timePlanned' => 180,
                    'workloadLevel' => 'HIGH',
                    'efficiency' => 0.75,
                    'categories' => ['support', 'billing'],
                    'completedTickets' => 5,
                    'inProgressTickets' => 3,
                    'waitingTickets' => 2,
                ],
            ],
            'queueStats' => [
                [
                    'queueId' => 'queue-1',
                    'queueName' => 'Support',
                    'waitingTickets' => 4,
                    'inProgressTickets' => 3,
                    'completedTickets' => 3,
                    'totalTickets' => 10,
                    'averageResolutionTime' => 50,
                    'assignedWorkers' => 2,
                ],
            ],
            'autoAssignmentSettings' => [
                'enabled' => true,
                'lastRun' => '2024-05-01T12:34:56+00:00',
                'ticketsAssigned' => 6,
                'settings' => [
                    'considerEfficiency' => true,
                    'considerAvailability' => false,
                    'maxTicketsPerWorker' => 5,
                ],
            ],
        ], $data);
    }

    public function testUpdateAutoAssignmentValidatesPayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createManagerFixture(),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::never())
            ->method('updateAutoAssignmentSettings');

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = $this->createJsonRequest(
            'PUT',
            '/api/manager/auto-assignment',
            [
                'settings' => [
                    'considerEfficiency' => true,
                    'considerAvailability' => true,
                    'maxTicketsPerWorker' => 5,
                ],
            ],
        );

        $response = $controller->updateAutoAssignment($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Pole "enabled" jest wymagane', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('errors', $data['errors']);
        self::assertArrayHasKey('enabled', $data['errors']['errors']);
    }

    public function testUpdateAutoAssignmentForbidsAccessWithoutManagerRole(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::never())
            ->method('updateAutoAssignmentSettings');

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = $this->createJsonRequest(
            'PUT',
            '/api/manager/auto-assignment',
            [
                'enabled' => true,
                'settings' => [
                    'considerEfficiency' => true,
                    'considerAvailability' => true,
                    'maxTicketsPerWorker' => 5,
                ],
            ],
        );

        $response = $controller->updateAutoAssignment($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień', $data['message'] ?? null);
    }

    public function testUpdateAutoAssignmentReturnsUpdatedSettingsWithTimestamp(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createManagerFixture(),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::once())
            ->method('updateAutoAssignmentSettings')
            ->with(
                'worker-id',
                true,
                self::callback(static function (UpdateAutoAssignmentSettingsInput $settings): bool {
                    self::assertTrue($settings->considerEfficiency);
                    self::assertFalse($settings->considerAvailability);
                    self::assertSame(8, $settings->maxTicketsPerWorker);

                    return true;
                }),
            )
            ->willReturn([
                'autoAssignmentSettings' => [
                    'enabled' => true,
                    'lastRun' => new \DateTimeImmutable('2024-05-02T10:00:00+00:00'),
                    'ticketsAssigned' => 12,
                    'settings' => [
                        'considerEfficiency' => true,
                        'considerAvailability' => false,
                        'maxTicketsPerWorker' => 8,
                    ],
                ],
                'updatedAt' => new \DateTimeImmutable('2024-05-02T11:00:00+00:00'),
            ]);

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = $this->createJsonRequest(
            'PUT',
            '/api/manager/auto-assignment',
            [
                'enabled' => true,
                'settings' => [
                    'considerEfficiency' => true,
                    'considerAvailability' => false,
                    'maxTicketsPerWorker' => 8,
                ],
            ],
        );

        $response = $controller->updateAutoAssignment($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'autoAssignmentSettings' => [
                'enabled' => true,
                'lastRun' => '2024-05-02T10:00:00+00:00',
                'ticketsAssigned' => 12,
                'settings' => [
                    'considerEfficiency' => true,
                    'considerAvailability' => false,
                    'maxTicketsPerWorker' => 8,
                ],
            ],
            'updatedAt' => '2024-05-02T11:00:00+00:00',
        ], $data);
    }

    public function testTriggerAutoAssignmentValidatesDatePayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createManagerFixture(),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::never())
            ->method('triggerAutoAssignment');

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = $this->createJsonRequest(
            'POST',
            '/api/manager/auto-assignment/trigger',
            [
                'date' => 'not-a-date',
            ],
        );

        $response = $controller->triggerAutoAssignment($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('date', $data['errors']);
    }

    public function testTriggerAutoAssignmentReturnsAcceptedResponse(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createManagerFixture(),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::once())
            ->method('triggerAutoAssignment')
            ->with(
                'worker-id',
                self::callback(static function (\DateTimeImmutable $date): bool {
                    return '2024-05-03' === $date->format('Y-m-d');
                }),
            )
            ->willReturn([
                'message' => 'Auto-assignment started',
                'ticketsAssigned' => 9,
                'assignedTo' => [
                    [
                        'workerId' => 'worker-1',
                        'ticketsCount' => 5,
                    ],
                    [
                        'workerId' => 'worker-2',
                        'ticketsCount' => 4,
                    ],
                ],
                'completedAt' => new \DateTimeImmutable('2024-05-03T15:30:00+00:00'),
            ]);

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = $this->createJsonRequest(
            'POST',
            '/api/manager/auto-assignment/trigger',
            [
                'date' => '2024-05-03',
            ],
        );

        $response = $controller->triggerAutoAssignment($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame([
            'message' => 'Auto-assignment started',
            'ticketsAssigned' => 9,
            'assignedTo' => [
                [
                    'workerId' => 'worker-1',
                    'ticketsCount' => 5,
                ],
                [
                    'workerId' => 'worker-2',
                    'ticketsCount' => 4,
                ],
            ],
            'completedAt' => '2024-05-03T15:30:00+00:00',
        ], $data);
    }

    public function testTriggerAutoAssignmentForbidsAccessWithoutManagerRole(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->createClientWithMocks($provider);

        $this->managerMonitoringService
            ->expects(self::never())
            ->method('triggerAutoAssignment');

        /** @var ManagerMonitoringController $controller */
        $controller = static::getContainer()->get(ManagerMonitoringController::class);

        $request = $this->createJsonRequest(
            'POST',
            '/api/manager/auto-assignment/trigger',
            [
                'date' => '2024-05-03',
            ],
        );

        $response = $controller->triggerAutoAssignment($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień', $data['message'] ?? null);
    }
}
