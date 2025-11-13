<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Schedule;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Schedule\WorkerScheduleController;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\Exception\TicketWorkNotFoundException;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;
use App\Modules\Tickets\Domain\TicketRegisteredTimeInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerScheduleControllerTest extends BackendForFrontendTestCase
{
    public function testGetScheduleRequiresAuthenticatedWorker(): void
    {
        $provider = $this->createMock(AuthenticatedWorkerProvider::class);
        $provider
            ->expects(self::once())
            ->method('getAuthenticatedWorker')
            ->willThrowException(new AuthenticationException('Brak aktywnej sesji pracownika'));

        $this->workerScheduleService
            ->expects(self::never())
            ->method('getWorkerScheduleForPeriod');

        $this->ticketService
            ->expects(self::never())
            ->method('getWorkerTimeSpentOnTicket');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $response = $controller->getSchedule();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Brak aktywnej sesji pracownika', $data['message'] ?? null);
    }

    public function testGetScheduleReturnsActiveTicketAndRecentAssignments(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
            'getName' => 'Wsparcie',
            'getDefaultResolutionTimeMinutes' => 45,
        ]);

        $clientActive = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-1',
            'getFirstName' => 'Alicja',
            'getLastName' => 'Nowak',
            'getEmail' => 'alice@example.com',
            'getPhone' => '+48123123123',
        ]);

        $clientWaiting = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-2',
            'getFirstName' => null,
            'getLastName' => null,
            'getEmail' => 'bob@example.com',
            'getPhone' => null,
        ]);

        $activeTicket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-active',
            'getTitle' => 'Reset hasła',
            'getCategory' => $category,
            'getStatus' => 'in_progress',
            'getClient' => $clientActive,
        ]);

        $waitingTicket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-waiting',
            'getTitle' => 'Konfiguracja VPN',
            'getCategory' => $category,
            'getStatus' => 'waiting',
            'getClient' => $clientWaiting,
        ]);

        $activeAssignment = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $activeTicket,
            'getScheduledDate' => new \DateTimeImmutable('2024-06-10'),
            'getAssignedAt' => new \DateTimeImmutable('2024-06-09T09:00:00'),
            'getEstimatedTimeMinutes' => 60,
        ]);

        $waitingAssignment = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $waitingTicket,
            'getScheduledDate' => new \DateTimeImmutable('2024-06-11'),
            'getAssignedAt' => new \DateTimeImmutable('2024-06-09T10:00:00'),
            'getEstimatedTimeMinutes' => 45,
        ]);

        $note = $this->createConfiguredMock(TicketNoteInterface::class, [
            'getId' => 'note-1',
            'getContent' => 'Klient prosi o kontakt po 12:00',
            'getCreatedAt' => new \DateTimeImmutable('2024-06-10T10:15:00+00:00'),
            'getWorkerId' => 'worker-id',
        ]);

        $today = new \DateTimeImmutable('today');
        $expectedStart = $today->sub(new \DateInterval('P1D'));
        $expectedEnd = $today->add(new \DateInterval('P6D'));

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('getWorkerScheduleForPeriod')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $start): bool => $start->format('Y-m-d') === $expectedStart->format('Y-m-d')),
                self::callback(static fn (\DateTimeImmutable $end): bool => $end->format('Y-m-d') === $expectedEnd->format('Y-m-d')),
            )
            ->willReturn([$activeAssignment, $waitingAssignment]);

        $this->ticketService
            ->expects(self::exactly(2))
            ->method('getWorkerTimeSpentOnTicket')
            ->willReturnMap([
                [$activeTicket, $workerEntity, 30],
                [$waitingTicket, $workerEntity, 15],
            ]);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketNotes')
            ->with($activeTicket)
            ->willReturn([$note]);

        $this->ticketService
            ->expects(self::never())
            ->method('getTicketById');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $response = $controller->getSchedule();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $schedule = $data['schedule'] ?? null;
        self::assertIsArray($schedule);
        self::assertCount(2, $schedule);

        self::assertSame([
            'date' => '2024-06-10',
            'tickets' => [
                [
                    'id' => 'ticket-active',
                    'title' => 'Reset hasła',
                    'category' => [
                        'id' => 'cat-support',
                        'name' => 'Wsparcie',
                        'defaultResolutionTimeMinutes' => 45,
                        'defaultResolutionTime' => 45,
                    ],
                    'status' => 'in_progress',
                    'timeSpent' => 30,
                    'estimatedTime' => 60,
                    'scheduledDate' => '2024-06-10',
                    'client' => [
                        'id' => 'client-1',
                        'name' => 'Alicja Nowak',
                        'email' => 'alice@example.com',
                        'phone' => '+48123123123',
                    ],
                    'isActive' => true,
                ],
            ],
            'totalTimeSpent' => 30,
        ], $schedule[0]);

        self::assertSame([
            'date' => '2024-06-11',
            'tickets' => [
                [
                    'id' => 'ticket-waiting',
                    'title' => 'Konfiguracja VPN',
                    'category' => [
                        'id' => 'cat-support',
                        'name' => 'Wsparcie',
                        'defaultResolutionTimeMinutes' => 45,
                        'defaultResolutionTime' => 45,
                    ],
                    'status' => 'waiting',
                    'timeSpent' => 15,
                    'estimatedTime' => 45,
                    'scheduledDate' => '2024-06-11',
                    'client' => [
                        'id' => 'client-2',
                        'name' => 'bob@example.com',
                        'email' => 'bob@example.com',
                        'phone' => null,
                    ],
                ],
            ],
            'totalTimeSpent' => 15,
        ], $schedule[1]);

        self::assertSame([
            'id' => 'ticket-active',
            'title' => 'Reset hasła',
            'category' => [
                'id' => 'cat-support',
                'name' => 'Wsparcie',
                'defaultResolutionTimeMinutes' => 45,
                'defaultResolutionTime' => 45,
            ],
            'status' => 'in_progress',
            'timeSpent' => 30,
            'estimatedTime' => 60,
            'scheduledDate' => '2024-06-10',
            'client' => [
                'id' => 'client-1',
                'name' => 'Alicja Nowak',
                'email' => 'alice@example.com',
                'phone' => '+48123123123',
            ],
            'isActive' => true,
            'notes' => [
                [
                    'id' => 'note-1',
                    'content' => 'Klient prosi o kontakt po 12:00',
                    'createdAt' => '2024-06-10T10:15:00+00:00',
                    'createdBy' => 'worker-id',
                ],
            ],
            'messages' => [],
        ], $data['activeTicket'] ?? null);
    }

    public function testGetWorkStatusAggregatesScheduleStatisticsAndTicketMetrics(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $ticketTodayOne = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-1',
            'getCategory' => $this->createConfiguredMock(TicketCategoryInterface::class, [
                'getId' => 'cat-support',
            ]),
            'getStatus' => 'in_progress',
        ]);

        $ticketTodayTwo = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-2',
            'getCategory' => $this->createConfiguredMock(TicketCategoryInterface::class, [
                'getId' => 'cat-support',
            ]),
            'getStatus' => 'waiting',
        ]);

        $assignmentOne = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $ticketTodayOne,
            'getScheduledDate' => new \DateTimeImmutable('today'),
            'getAssignedAt' => new \DateTimeImmutable('today 08:00'),
        ]);

        $assignmentTwo = $this->createConfiguredMock(WorkerScheduleAssignmentInterface::class, [
            'getTicket' => $ticketTodayTwo,
            'getScheduledDate' => new \DateTimeImmutable('today'),
            'getAssignedAt' => new \DateTimeImmutable('today 10:00'),
        ]);

        $today = new \DateTimeImmutable('today');

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('getWorkerScheduleStatistics')
            ->with('worker-id', self::callback(static fn (\DateTimeImmutable $date): bool => $date->format('Y-m-d') === $today->format('Y-m-d')))
            ->willReturn([
                'ticketsCount' => 5,
                'timePlanned' => 240,
                'completedTickets' => 2,
                'inProgressTickets' => 2,
                'waitingTickets' => 1,
            ]);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('getWorkerScheduleForPeriod')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $start): bool => $start->format('Y-m-d') === $today->format('Y-m-d')),
                self::callback(static fn (\DateTimeImmutable $end): bool => $end->format('Y-m-d') === $today->format('Y-m-d')),
            )
            ->willReturn([$assignmentOne, $assignmentTwo]);

        $this->ticketService
            ->expects(self::exactly(2))
            ->method('getWorkerTimeSpentOnTicket')
            ->willReturnOnConsecutiveCalls(25, 35);

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('getAvailableTimeForDate')
            ->with('worker-id', self::callback(static fn (\DateTimeImmutable $date): bool => $date->format('Y-m-d') === $today->format('Y-m-d')))
            ->willReturn(600); // 10 hours = 600 minutes, ratio = 240/600 = 0.4 -> 'low'

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $response = $controller->getWorkStatus();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        self::assertSame([
            'level' => 'low',
            'message' => 'Masz sporo wolnego czasu – rozważ przejęcie dodatkowych ticketów',
            'ticketsCount' => 5,
            'timeSpent' => 60,
            'timePlanned' => 240,
        ], $data['status'] ?? null);

        self::assertSame([
            'date' => $today->format('Y-m-d'),
            'ticketsCount' => 5,
            'timeSpent' => 60,
            'timePlanned' => 240,
            'completedTickets' => 2,
            'inProgressTickets' => 2,
            'waitingTickets' => 1,
        ], $data['todayStats'] ?? null);
    }

    public function testUpdateTicketStatusRejectsInvalidStatus(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-1',
            'getCategory' => $category,
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-1')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::never())
            ->method('updateTicketStatus');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-1/status',
            ['status' => 'invalid_status'],
        );

        $response = $controller->updateTicketStatus('ticket-1', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertSame(
            ['Nieprawidłowy status ticketa'],
            $data['errors']['status'] ?? null,
        );
    }

    public function testUpdateTicketStatusDeniesAccessForUnauthorizedWorker(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-billing',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-2',
            'getCategory' => $category,
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-2')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::never())
            ->method('updateTicketStatus');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-2/status',
            ['status' => 'in_progress'],
        );

        $response = $controller->updateTicketStatus('ticket-2', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień do obsługi ticketa', $data['message'] ?? null);
        self::assertSame('ticket-2', $data['ticketId'] ?? null);
        self::assertSame('cat-billing', $data['categoryId'] ?? null);
    }

    public function testAddTicketTimeRejectsNonPositiveMinutes(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-3',
            'getCategory' => $category,
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-3')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::never())
            ->method('registerManualTimeEntry');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-3/time',
            ['minutes' => 0, 'type' => 'work'],
        );

        $response = $controller->addTicketTime('ticket-3', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertSame(
            ['Liczba minut musi być dodatnia'],
            $data['errors']['minutes'] ?? null,
        );
    }

    public function testAddTicketTimeRegistersEntryAndReturnsUpdatedTotals(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-4',
            'getCategory' => $category,
        ]);

        $refreshedTicket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-4',
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T15:00:00+00:00'),
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::exactly(2))
            ->method('getTicketById')
            ->with('ticket-4')
            ->willReturnOnConsecutiveCalls($ticket, $refreshedTicket);

        $this->ticketService
            ->expects(self::once())
            ->method('registerManualTimeEntry')
            ->with($ticket, $workerEntity, 30, true);

        $this->ticketService
            ->expects(self::once())
            ->method('getWorkerTimeSpentOnTicket')
            ->with($ticket, $workerEntity)
            ->willReturn(95);

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-4/time',
            ['minutes' => 30, 'type' => 'phone_call'],
        );

        $response = $controller->addTicketTime('ticket-4', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'id' => 'ticket-4',
            'timeSpent' => 95,
            'updatedAt' => '2024-06-12T15:00:00+00:00',
        ], $data['ticket'] ?? null);
    }

    public function testAddTicketNoteRejectsTooShortContent(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-5',
            'getCategory' => $category,
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-5')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::never())
            ->method('addTicketNote');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-5/notes',
            ['content' => 'Ok'],
        );

        $response = $controller->addTicketNote('ticket-5', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertSame(
            ['Notatka musi zawierać co najmniej 3 znaki'],
            $data['errors']['content'] ?? null,
        );
    }

    public function testAddTicketNotePersistsNoteAndReturnsPayload(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-6',
            'getCategory' => $category,
        ]);

        $note = $this->createConfiguredMock(TicketNoteInterface::class, [
            'getId' => 'note-2',
            'getContent' => 'Skontaktowano się z klientem i omówiono szczegóły',
            'getCreatedAt' => new \DateTimeImmutable('2024-06-12T14:30:00+00:00'),
            'getWorkerId' => 'worker-id',
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-6')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::once())
            ->method('addTicketNote')
            ->with($ticket, $workerEntity, 'Skontaktowano się z klientem i omówiono szczegóły')
            ->willReturn($note);

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-6/notes',
            ['content' => 'Skontaktowano się z klientem i omówiono szczegóły'],
        );

        $response = $controller->addTicketNote('ticket-6', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            'id' => 'note-2',
            'content' => 'Skontaktowano się z klientem i omówiono szczegóły',
            'createdAt' => '2024-06-12T14:30:00+00:00',
            'createdBy' => 'worker-id',
        ], $data['note'] ?? null);
    }

    public function testAddTicketMessagePersistsMessageAndReturnsPayload(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
            'getLogin' => 'john.doe',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-7',
            'getCategory' => $category,
        ]);

        $message = $this->createConfiguredMock(TicketMessageInterface::class, [
            'getId' => 'msg-1',
            'getTicketId' => 'ticket-7',
            'getSenderType' => 'worker',
            'getSenderId' => 'worker-id',
            'getSenderName' => 'john.doe',
            'getContent' => 'Przeprowadzono rozmowę z klientem.',
            'getCreatedAt' => new \DateTimeImmutable('2024-06-12T16:00:00+00:00'),
            'getStatus' => 'sent',
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-7')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::once())
            ->method('addMessageToTicket')
            ->with($ticket, 'Przeprowadzono rozmowę z klientem.', 'worker', 'worker-id', 'john.doe')
            ->willReturn($message);

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-7/messages',
            ['content' => 'Przeprowadzono rozmowę z klientem.'],
        );

        $response = $controller->addTicketMessage('ticket-7', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            'id' => 'msg-1',
            'ticketId' => 'ticket-7',
            'senderType' => 'worker',
            'senderId' => 'worker-id',
            'senderName' => 'john.doe',
            'content' => 'Przeprowadzono rozmowę z klientem.',
            'createdAt' => '2024-06-12T16:00:00+00:00',
            'status' => 'sent',
        ], $data['message'] ?? null);
    }

    public function testUpdateTicketStatusReturnsNotFoundWhenTicketIsMissing(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-missing')
            ->willReturn(null);

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/tickets/ticket-missing/status',
            ['status' => 'waiting'],
        );

        $response = $controller->updateTicketStatus('ticket-missing', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('Ticket nie został znaleziony', $data['message'] ?? null);
    }

    public function testCloseTicketDeniesAccessForUnauthorizedWorker(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-billing',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-8',
            'getCategory' => $category,
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-8')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::never())
            ->method('stopTicketWork');

        $this->ticketService
            ->expects(self::never())
            ->method('closeTicket');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $response = $controller->closeTicket('ticket-8');
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Brak uprawnień do obsługi ticketa', $data['message'] ?? null);
        self::assertSame('ticket-8', $data['ticketId'] ?? null);
        self::assertSame('cat-billing', $data['categoryId'] ?? null);
    }

    public function testCloseTicketReturnsNotFoundWhenTicketIsMissing(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-missing-close')
            ->willReturn(null);

        $this->ticketService
            ->expects(self::never())
            ->method('stopTicketWork');

        $this->ticketService
            ->expects(self::never())
            ->method('closeTicket');

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $response = $controller->closeTicket('ticket-missing-close');
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('Ticket nie został znaleziony', $data['message'] ?? null);
    }

    public function testCloseTicketStopsActiveWorkAndClosesTicket(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-9',
            'getCategory' => $category,
        ]);

        $registeredTime = $this->createConfiguredMock(TicketRegisteredTimeInterface::class, [
            'getId' => 'time-1',
        ]);

        $closedTicket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-9',
            'getStatus' => 'closed',
            'getClosedAt' => new \DateTimeImmutable('2024-06-12T17:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T17:00:00+00:00'),
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-9')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::once())
            ->method('stopTicketWork')
            ->with($ticket, $workerEntity)
            ->willReturn($registeredTime);

        $this->ticketService
            ->expects(self::once())
            ->method('closeTicket')
            ->with($ticket, $workerEntity)
            ->willReturn($closedTicket);

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $response = $controller->closeTicket('ticket-9');
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'id' => 'ticket-9',
            'status' => 'closed',
            'closedAt' => '2024-06-12T17:00:00+00:00',
            'updatedAt' => '2024-06-12T17:00:00+00:00',
        ], $data['ticket'] ?? null);
    }

    public function testCloseTicketClosesTicketWhenNoActiveWork(): void
    {
        $worker = $this->createAuthenticatedWorkerFixture(false, ['cat-support']);
        $provider = $this->stubAuthenticatedWorkerProvider($worker);

        $workerEntity = $this->createConfiguredMock(WorkerInterface::class, [
            'getId' => 'worker-id',
        ]);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-support',
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-10',
            'getCategory' => $category,
        ]);

        $closedTicket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-10',
            'getStatus' => 'closed',
            'getClosedAt' => new \DateTimeImmutable('2024-06-12T18:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T18:00:00+00:00'),
        ]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-id')
            ->willReturn($workerEntity);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-10')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::once())
            ->method('stopTicketWork')
            ->with($ticket, $workerEntity)
            ->willThrowException(TicketWorkNotFoundException::forWorker('ticket-10', 'worker-id'));

        $this->ticketService
            ->expects(self::once())
            ->method('closeTicket')
            ->with($ticket, $workerEntity)
            ->willReturn($closedTicket);

        $this->createClientWithMocks($provider);

        /** @var WorkerScheduleController $controller */
        $controller = static::getContainer()->get(WorkerScheduleController::class);

        $response = $controller->closeTicket('ticket-10');
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'id' => 'ticket-10',
            'status' => 'closed',
            'closedAt' => '2024-06-12T18:00:00+00:00',
            'updatedAt' => '2024-06-12T18:00:00+00:00',
        ], $data['ticket'] ?? null);
    }
}
