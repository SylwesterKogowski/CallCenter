<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Planning;

use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Planning\WorkerPlanningController;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Dto\WorkerBacklogFilters;
use App\Modules\Tickets\Application\Dto\WorkerBacklogResultInterface;
use App\Modules\Tickets\Application\Dto\WorkerBacklogTicketInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerSchedulePredictionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerPlanningControllerTest extends BackendForFrontendTestCase
{
    public function testGetBacklogRequiresAuthenticatedWorker(): void
    {
        $provider = $this->createMock(AuthenticatedWorkerProvider::class);
        $provider
            ->expects(self::once())
            ->method('getAuthenticatedWorker')
            ->willThrowException(new AuthenticationException('Brak aktywnej sesji pracownika'));

        $this->ticketBacklogService
            ->expects(self::never())
            ->method('getWorkerBacklog');

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $response = $controller->getBacklog(Request::create(
            '/api/worker/tickets/backlog',
            Request::METHOD_GET,
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Brak aktywnej sesji pracownika', $data['message'] ?? null);
    }

    public function testGetBacklogFiltersTicketsByWorkerCategories(): void
    {
        $worker = new AuthenticatedWorker('worker-id', 'worker-login', false, ['cat-1', 'cat-2']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-1',
            'getTitle' => 'Ticket title',
            'getStatus' => 'waiting',
            'getCategory' => $this->createConfiguredMock(TicketCategoryInterface::class, [
                'getId' => 'cat-1',
                'getName' => 'Support',
                'getDefaultResolutionTimeMinutes' => 60,
            ]),
            'getClient' => $this->createConfiguredMock(ClientInterface::class, [
                'getId' => 'client-1',
                'getFirstName' => 'Alice',
                'getLastName' => 'Smith',
                'getEmail' => 'alice@example.com',
                'getPhone' => '+48 000 000 000',
            ]),
        ]);

        $backlogTicket = $this->createConfiguredMock(WorkerBacklogTicketInterface::class, [
            'getTicket' => $ticket,
            'getClient' => $ticket->getClient(),
            'getCategory' => $ticket->getCategory(),
            'getPriority' => 'high',
            'getEstimatedTimeMinutes' => 90,
            'getCreatedAt' => new \DateTimeImmutable('2024-06-10T09:15:00+00:00'),
            'getScheduledDate' => new \DateTimeImmutable('2024-06-11'),
        ]);

        $result = $this->createMock(WorkerBacklogResultInterface::class);
        $result
            ->method('getTickets')
            ->willReturn([$backlogTicket]);
        $result
            ->method('getTotal')
            ->willReturn(1);

        $this->ticketBacklogService
            ->expects(self::once())
            ->method('getWorkerBacklog')
            ->with(
                'worker-id',
                self::callback(static function (WorkerBacklogFilters $filters): bool {
                    self::assertSame(['cat-1', 'cat-2'], $filters->getCategories());
                    self::assertSame([], $filters->getStatuses());
                    self::assertSame([], $filters->getPriorities());
                    self::assertNull($filters->getSearch());
                    self::assertNull($filters->getSort());

                    return true;
                }),
            )
            ->willReturn($result);

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $response = $controller->getBacklog(Request::create(
            '/api/worker/tickets/backlog',
            Request::METHOD_GET,
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(1, $data['total'] ?? null);
        self::assertCount(1, $data['tickets'] ?? []);

        $ticketPayload = $data['tickets'][0];

        self::assertSame('ticket-1', $ticketPayload['id'] ?? null);
        self::assertSame('Ticket title', $ticketPayload['title'] ?? null);
        self::assertSame('waiting', $ticketPayload['status'] ?? null);
        self::assertSame('high', $ticketPayload['priority'] ?? null);
        self::assertSame(90, $ticketPayload['estimatedTime'] ?? null);
        self::assertSame('2024-06-10T09:15:00+00:00', $ticketPayload['createdAt'] ?? null);
        self::assertSame('2024-06-11', $ticketPayload['scheduledDate'] ?? null);
        self::assertSame('cat-1', $ticketPayload['category']['id'] ?? null);
        self::assertSame('Support', $ticketPayload['category']['name'] ?? null);
        self::assertSame(60, $ticketPayload['category']['defaultResolutionTimeMinutes'] ?? null);
        self::assertSame(60, $ticketPayload['category']['defaultResolutionTime'] ?? null);
        self::assertSame('client-1', $ticketPayload['client']['id'] ?? null);
        self::assertSame('Alice Smith', $ticketPayload['client']['name'] ?? null);
        self::assertSame('alice@example.com', $ticketPayload['client']['email'] ?? null);
        self::assertSame('+48 000 000 000', $ticketPayload['client']['phone'] ?? null);
    }

    public function testGetBacklogRequiresWorkerAuthorizationForCategories(): void
    {
        $worker = new AuthenticatedWorker('worker-id', 'worker-login', false, ['cat-1']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $this->ticketBacklogService
            ->expects(self::never())
            ->method('getWorkerBacklog');

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $response = $controller->getBacklog(Request::create(
            '/api/worker/tickets/backlog',
            Request::METHOD_GET,
            ['categories' => 'cat-1,cat-2'],
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień do wybranych kategorii', $data['message'] ?? null);
        self::assertSame(['categories' => ['cat-2']], array_intersect_key($data, ['categories' => 1]));
    }

    public function testGetWeekScheduleMergesAvailabilityAndAssignments(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $assignmentTicket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-100',
            'getTitle' => '',
            'getCategory' => $this->createConfiguredMock(TicketCategoryInterface::class, [
                'getId' => 'cat-2',
                'getName' => 'Billing',
                'getDefaultResolutionTimeMinutes' => 45,
            ]),
            'getStatus' => 'in_progress',
        ]);

        $assignmentMonday = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $assignmentTicket,
            'getScheduledDate' => new \DateTimeImmutable('2024-06-10'),
            'getAssignedAt' => new \DateTimeImmutable('2024-06-09T10:00:00+00:00'),
            'getEstimatedTimeMinutes' => 120,
            'getPriority' => 'high',
        ]);

        $assignmentTuesday = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $assignmentTicket,
            'getScheduledDate' => new \DateTimeImmutable('2024-06-11'),
            'getAssignedAt' => new \DateTimeImmutable('2024-06-09T11:00:00+00:00'),
            'getEstimatedTimeMinutes' => 60,
            'getPriority' => null,
        ]);

        $slotMorning = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getStartDatetime' => new \DateTimeImmutable('2024-06-10T09:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-10T12:00:00+00:00'),
        ]);

        $slotAfternoon = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getStartDatetime' => new \DateTimeImmutable('2024-06-11T13:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-11T16:00:00+00:00'),
        ]);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('getWorkerScheduleForWeek')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-10' === $date->format('Y-m-d')),
            )
            ->willReturn([$assignmentMonday, $assignmentTuesday]);

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('getWorkerAvailabilityForWeek')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-10' === $date->format('Y-m-d')),
            )
            ->willReturn([$slotMorning, $slotAfternoon]);

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $response = $controller->getWeekSchedule(Request::create(
            '/api/worker/schedule/week',
            Request::METHOD_GET,
            ['startDate' => '2024-06-10'],
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $schedule = $data['schedule'] ?? [];

        self::assertCount(7, $schedule);

        $monday = $schedule[0];
        self::assertSame('2024-06-10', $monday['date'] ?? null);
        self::assertTrue($monday['isAvailable'] ?? false);
        self::assertSame([['startTime' => '09:00', 'endTime' => '12:00']], $monday['availabilityHours'] ?? null);
        self::assertCount(1, $monday['tickets'] ?? []);
        self::assertSame('ticket-100', $monday['tickets'][0]['id'] ?? null);
        self::assertSame('Ticket ticket-100', $monday['tickets'][0]['title'] ?? null);
        self::assertSame(120, $monday['totalEstimatedTime'] ?? null);

        $tuesday = $schedule[1];
        self::assertSame('2024-06-11', $tuesday['date'] ?? null);
        self::assertTrue($tuesday['isAvailable'] ?? false);
        self::assertSame([['startTime' => '13:00', 'endTime' => '16:00']], $tuesday['availabilityHours'] ?? null);
        self::assertSame(60, $tuesday['totalEstimatedTime'] ?? null);
    }

    public function testGetPredictionsReturnsCalculatedForecast(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $predictionMonday = $this->createConfiguredMock(WorkerSchedulePredictionInterface::class, [
            'getDate' => new \DateTimeImmutable('2024-06-10'),
            'getPredictedTicketCount' => 5,
            'getAvailableTimeMinutes' => 300,
            'getEfficiency' => 0.85,
        ]);

        $predictionTuesday = $this->createConfiguredMock(WorkerSchedulePredictionInterface::class, [
            'getDate' => new \DateTimeImmutable('2024-06-11'),
            'getPredictedTicketCount' => 3,
            'getAvailableTimeMinutes' => 240,
            'getEfficiency' => 0.9,
        ]);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('getPredictionsForWeek')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-10' === $date->format('Y-m-d')),
            )
            ->willReturn([$predictionMonday, $predictionTuesday]);

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $response = $controller->getPredictions(Request::create(
            '/api/worker/schedule/predictions',
            Request::METHOD_GET,
            ['startDate' => '2024-06-10'],
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            [
                'date' => '2024-06-10',
                'predictedTicketCount' => 5,
                'availableTime' => 300,
                'efficiency' => 0.85,
            ],
            [
                'date' => '2024-06-11',
                'predictedTicketCount' => 3,
                'availableTime' => 240,
                'efficiency' => 0.9,
            ],
        ], $data['predictions'] ?? null);
    }

    public function testAssignTicketValidatesPayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerScheduleService
            ->expects(self::never())
            ->method('assignTicketToWorker');

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/schedule/assign',
            [
                'ticketId' => '',
                'date' => 'invalid-date',
            ],
        );

        $response = $controller->assignTicket($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('ticketId', $data['errors']);
        self::assertArrayHasKey('date', $data['errors']);
    }

    public function testAssignTicketCreatesScheduleAssignment(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $assignment = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $this->createConfiguredMock(TicketInterface::class, [
                'getId' => 'ticket-123',
            ]),
            'getScheduledDate' => new \DateTimeImmutable('2024-06-12'),
            'getAssignedAt' => new \DateTimeImmutable('2024-06-09T12:34:56+00:00'),
        ]);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('assignTicketToWorker')
            ->with(
                '550e8400-e29b-41d4-a716-446655440000',
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-12' === $date->format('Y-m-d')),
                'worker-id',
            )
            ->willReturn($assignment);

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/schedule/assign',
            [
                'ticketId' => '550e8400-e29b-41d4-a716-446655440000',
                'date' => '2024-06-12',
            ],
        );

        $response = $controller->assignTicket($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            'ticketId' => 'ticket-123',
            'date' => '2024-06-12',
            'assignedAt' => '2024-06-09T12:34:56+00:00',
        ], $data['assignment'] ?? null);
    }

    public function testUnassignTicketValidatesPayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerScheduleService
            ->expects(self::never())
            ->method('removeTicketFromSchedule');

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_DELETE,
            '/api/worker/schedule/assign',
            [
                'ticketId' => '',
                'date' => '',
            ],
        );

        $response = $controller->unassignTicket($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
    }

    public function testUnassignTicketRemovesAssignment(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerScheduleService
            ->expects(self::once())
            ->method('removeTicketFromSchedule')
            ->with(
                '550e8400-e29b-41d4-a716-446655440000',
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-13' === $date->format('Y-m-d')),
            );

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_DELETE,
            '/api/worker/schedule/assign',
            [
                'ticketId' => '550e8400-e29b-41d4-a716-446655440000',
                'date' => '2024-06-13',
            ],
        );

        $response = $controller->unassignTicket($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($data['success'] ?? false);
    }

    public function testAutoAssignValidatesPayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerScheduleService
            ->expects(self::never())
            ->method('autoAssignTicketsForWorker');

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/schedule/auto-assign',
            [
                'weekStartDate' => '',
                'categories' => ['   '],
            ],
        );

        $response = $controller->autoAssign($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('weekStartDate', $data['errors']);
    }

    public function testAutoAssignTriggersWorkerScheduleService(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-1', 'cat-2', 'cat-3']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $assignmentOne = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $this->createConfiguredMock(TicketInterface::class, [
                'getId' => 'ticket-a',
            ]),
            'getScheduledDate' => new \DateTimeImmutable('2024-06-10'),
            'getAssignedAt' => new \DateTimeImmutable('2024-06-07T10:00:00+00:00'),
        ]);

        $assignmentTwo = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $this->createConfiguredMock(TicketInterface::class, [
                'getId' => 'ticket-b',
            ]),
            'getScheduledDate' => new \DateTimeImmutable('2024-06-11'),
            'getAssignedAt' => new \DateTimeImmutable('2024-06-07T11:00:00+00:00'),
        ]);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('autoAssignTicketsForWorker')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-10' === $date->format('Y-m-d')),
                self::callback(static function (?array $categories): bool {
                    self::assertSame(['cat-1', 'cat-2'], $categories);

                    return true;
                }),
            )
            ->willReturn([$assignmentOne, $assignmentTwo]);

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/schedule/auto-assign',
            [
                'weekStartDate' => '2024-06-10',
                'categories' => [
                    'cat-1',
                    ' cat-2 ',
                ],
            ],
        );

        $response = $controller->autoAssign($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            [
                'ticketId' => 'ticket-a',
                'date' => '2024-06-10',
            ],
            [
                'ticketId' => 'ticket-b',
                'date' => '2024-06-11',
            ],
        ], $data['assignments'] ?? null);
        self::assertSame(2, $data['totalAssigned'] ?? null);
    }

    public function testAutoAssignMapsDomainExceptionToJsonProblemResponse(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-9']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('autoAssignTicketsForWorker')
            ->willThrowException(new AccessDeniedException('Brak uprawnień do kategorii', [
                'categories' => ['cat-9'],
            ]));

        $this->createClientWithMocks($provider);

        /** @var WorkerPlanningController $controller */
        $controller = static::getContainer()->get(WorkerPlanningController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/schedule/auto-assign',
            [
                'weekStartDate' => '2024-06-10',
                'categories' => ['cat-9'],
            ],
        );

        $response = $controller->autoAssign($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień do kategorii', $data['message'] ?? null);
        self::assertSame(['cat-9'], $data['categories'] ?? null);
    }
}
