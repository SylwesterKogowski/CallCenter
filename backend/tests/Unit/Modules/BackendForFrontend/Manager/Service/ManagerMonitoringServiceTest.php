<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Manager\Service;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentSettingsInput;
use App\Modules\BackendForFrontend\Manager\Persistence\Entity\ManagerAutoAssignmentSettings;
use App\Modules\BackendForFrontend\Manager\Persistence\ManagerAutoAssignmentSettingsRepositoryInterface;
use App\Modules\BackendForFrontend\Manager\Service\ManagerMonitoringService;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\TicketCategories\Domain\TicketCategory;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ManagerMonitoringServiceTest extends TestCase
{
    /** @var AuthenticationServiceInterface&MockObject */
    private AuthenticationServiceInterface $authenticationService;

    /** @var AuthorizationServiceInterface&MockObject */
    private AuthorizationServiceInterface $authorizationService;

    /** @var TicketCategoryServiceInterface&MockObject */
    private TicketCategoryServiceInterface $ticketCategoryService;

    /** @var TicketServiceInterface&MockObject */
    private TicketServiceInterface $ticketService;

    /** @var ManagerAutoAssignmentSettingsRepositoryInterface&MockObject */
    private ManagerAutoAssignmentSettingsRepositoryInterface $settingsRepository;

    /** @var WorkerScheduleServiceInterface&MockObject */
    private WorkerScheduleServiceInterface $workerScheduleService;

    private ManagerMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationService = $this->createMock(AuthenticationServiceInterface::class);
        $this->authorizationService = $this->createMock(AuthorizationServiceInterface::class);
        $this->ticketCategoryService = $this->createMock(TicketCategoryServiceInterface::class);
        $this->ticketService = $this->createMock(TicketServiceInterface::class);
        $this->settingsRepository = $this->createMock(ManagerAutoAssignmentSettingsRepositoryInterface::class);
        $this->workerScheduleService = $this->createMock(WorkerScheduleServiceInterface::class);

        $this->service = new ManagerMonitoringService(
            $this->authenticationService,
            $this->authorizationService,
            $this->ticketCategoryService,
            $this->ticketService,
            $this->settingsRepository,
            $this->workerScheduleService,
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2024-05-03T12:00:00+00:00'),
        );
    }

    public function testGetMonitoringDataAggregatesStats(): void
    {
        $assignments = [
            [
                'worker_id' => 'worker-1',
                'worker_login' => 'alice',
                'ticket_id' => 'ticket-1',
                'status' => TicketInterface::STATUS_IN_PROGRESS,
                'category_id' => 'cat-1',
                'category_name' => 'Support',
                'category_default_resolution_minutes' => 30,
                'created_at' => '2024-05-01 09:00:00',
                'closed_at' => null,
            ],
            [
                'worker_id' => 'worker-1',
                'worker_login' => 'alice',
                'ticket_id' => 'ticket-2',
                'status' => TicketInterface::STATUS_AWAITING_RESPONSE,
                'category_id' => 'cat-2',
                'category_name' => 'Billing',
                'category_default_resolution_minutes' => 45,
                'created_at' => '2024-05-01 10:00:00',
                'closed_at' => '2024-05-01 11:00:00',
            ],
            [
                'worker_id' => 'worker-2',
                'worker_login' => 'bob',
                'ticket_id' => 'ticket-3',
                'status' => TicketInterface::STATUS_CLOSED,
                'category_id' => 'cat-1',
                'category_name' => 'Support',
                'category_default_resolution_minutes' => 60,
                'created_at' => '2024-05-01 08:00:00',
                'closed_at' => '2024-05-01 10:00:00',
            ],
        ];

        $timeSpentMap = [
            'worker-1' => 45,
            'worker-2' => 30,
        ];

        $this->workerScheduleService
            ->expects(self::once())
            ->method('fetchAssignmentsForDate')
            ->with(self::callback(static function (\DateTimeImmutable $date): bool {
                return '2024-05-01' === $date->format('Y-m-d');
            }))
            ->willReturn($assignments);

        $this->ticketService
            ->expects(self::once())
            ->method('getWorkersTimeSpentForDate')
            ->with(
                ['worker-1', 'worker-2'],
                self::callback(static function (\DateTimeImmutable $date): bool {
                    return '2024-05-01' === $date->format('Y-m-d');
                }),
            )
            ->willReturn($timeSpentMap);

        $this->authenticationService
            ->expects(self::once())
            ->method('countNonManagerWorkers')
            ->willReturn(7);

        $this->authorizationService
            ->expects(self::exactly(2))
            ->method('getAssignedCategoryIds')
            ->willReturnOnConsecutiveCalls(['cat-1', 'cat-2'], ['cat-1']);

        $this->ticketCategoryService
            ->expects(self::once())
            ->method('getAllCategories')
            ->willReturn([
                new TicketCategory('cat-1', 'Support', null, 30),
                new TicketCategory('cat-2', 'Billing', null, 45),
            ]);

        $this->settingsRepository
            ->expects(self::once())
            ->method('find')
            ->with('manager-1')
            ->willReturn(null);

        $result = $this->service->getMonitoringData('manager-1', new \DateTimeImmutable('2024-05-01'));

        self::assertSame('2024-05-01', $result['date']);
        self::assertSame(3, $result['summary']['totalTickets']);
        self::assertSame(7, $result['summary']['totalWorkers']);
        self::assertSame(2, $result['summary']['totalQueues']);
        self::assertSame(55.0, $result['summary']['averageWorkload']);
        self::assertSame(90, $result['summary']['averageResolutionTime']);
        self::assertSame(2, \count($result['workerStats']));
        self::assertSame(2, \count($result['queueStats']));

        $aliceStats = $result['workerStats'][0];
        self::assertSame('worker-1', $aliceStats['workerId']);
        self::assertSame('alice', $aliceStats['workerLogin']);
        self::assertSame(2, $aliceStats['ticketsCount']);
        self::assertSame(45, $aliceStats['timeSpent']);
        self::assertSame(75, $aliceStats['timePlanned']);
        self::assertSame('normal', $aliceStats['workloadLevel']);
        self::assertSame(0.6, $aliceStats['efficiency']);
        self::assertSame(['Billing', 'Support'], $aliceStats['categories']);
        self::assertSame(0, $aliceStats['completedTickets']);
        self::assertSame(1, $aliceStats['inProgressTickets']);
        self::assertSame(1, $aliceStats['waitingTickets']);

        $supportQueue = array_values(array_filter(
            $result['queueStats'],
            static fn (array $queue): bool => 'cat-1' === $queue['queueId'],
        ))[0];

        self::assertSame(2, $supportQueue['totalTickets']);
        self::assertSame(0, $supportQueue['waitingTickets']);
        self::assertSame(1, $supportQueue['inProgressTickets']);
        self::assertSame(1, $supportQueue['completedTickets']);
        self::assertSame(120, $supportQueue['averageResolutionTime']);
        self::assertSame(2, $supportQueue['assignedWorkers']);

        $settings = $result['autoAssignmentSettings'];
        self::assertFalse($settings['enabled']);
        self::assertNull($settings['lastRun']);
        self::assertSame(0, $settings['ticketsAssigned']);
        self::assertSame(
            [
                'considerEfficiency' => true,
                'considerAvailability' => true,
                'maxTicketsPerWorker' => 10,
            ],
            $settings['settings'],
        );
    }

    public function testUpdateAutoAssignmentSettingsPersistsChanges(): void
    {
        $settingsEntity = new ManagerAutoAssignmentSettings('manager-1');
        $settingsEntity->setEnabled(false);
        $settingsEntity->setConsiderEfficiency(true);
        $settingsEntity->setConsiderAvailability(true);
        $settingsEntity->setMaxTicketsPerWorker(8);

        $this->settingsRepository
            ->expects(self::once())
            ->method('find')
            ->with('manager-1')
            ->willReturn($settingsEntity);

        $this->settingsRepository
            ->expects(self::once())
            ->method('save')
            ->with($settingsEntity);

        $input = new UpdateAutoAssignmentSettingsInput(
            considerEfficiency: false,
            considerAvailability: false,
            maxTicketsPerWorker: 5,
        );

        $result = $this->service->updateAutoAssignmentSettings('manager-1', true, $input);

        self::assertTrue($settingsEntity->isEnabled());
        self::assertFalse($settingsEntity->shouldConsiderEfficiency());
        self::assertFalse($settingsEntity->shouldConsiderAvailability());
        self::assertSame(5, $settingsEntity->getMaxTicketsPerWorker());
        self::assertInstanceOf(\DateTimeImmutable::class, $result['updatedAt']);
        self::assertTrue($result['autoAssignmentSettings']['enabled']);
    }

    public function testTriggerAutoAssignmentUpdatesSettingsAndReturnsSummary(): void
    {
        $this->authenticationService
            ->expects(self::once())
            ->method('getNonManagerWorkerIds')
            ->willReturn(['worker-1', 'worker-2']);

        $assignmentStub = $this->createAssignmentStub('worker-1', 'ticket-foo');

        $autoAssignCalls = [];

        $this->workerScheduleService
            ->expects(self::exactly(2))
            ->method('autoAssignTicketsForWorker')
            ->willReturnCallback(
                static function (string $workerId, \DateTimeImmutable $date) use (&$autoAssignCalls, $assignmentStub) {
                    $autoAssignCalls[] = [$workerId, $date];

                    if ('worker-1' === $workerId) {
                        return [$assignmentStub, $assignmentStub];
                    }

                    return [];
                },
            );

        $settingsEntity = new ManagerAutoAssignmentSettings('manager-1');

        $this->settingsRepository
            ->expects(self::once())
            ->method('find')
            ->with('manager-1')
            ->willReturn($settingsEntity);

        $this->settingsRepository
            ->expects(self::once())
            ->method('save')
            ->with($settingsEntity);

        $result = $this->service->triggerAutoAssignment('manager-1', new \DateTimeImmutable('2024-05-02'));

        self::assertSame('Automatyczne przypisywanie zostało uruchomione.', $result['message']);
        self::assertSame(2, $result['ticketsAssigned']);
        self::assertSame([['workerId' => 'worker-1', 'ticketsCount' => 2]], $result['assignedTo']);
        self::assertInstanceOf(\DateTimeImmutable::class, $result['completedAt']);
        self::assertSame(2, $settingsEntity->getTicketsAssigned());
        self::assertInstanceOf(\DateTimeImmutable::class, $settingsEntity->getLastRun());
        self::assertCount(2, $autoAssignCalls);
        self::assertSame('worker-1', $autoAssignCalls[0][0]);
        self::assertInstanceOf(\DateTimeImmutable::class, $autoAssignCalls[0][1]);
        self::assertSame('worker-2', $autoAssignCalls[1][0]);
    }

    private function createAssignmentStub(
        string $workerId,
        string $ticketId,
        int $estimatedMinutes = 30,
    ): WorkerScheduleAssignmentInterface {
        $ticket = $this->createTicketStub($ticketId);
        $scheduledDate = new \DateTimeImmutable();
        $assignedAt = new \DateTimeImmutable();

        return new class($workerId, $ticket, $scheduledDate, $assignedAt, $estimatedMinutes) implements WorkerScheduleAssignmentInterface {
            public function __construct(
                private readonly string $workerId,
                private readonly TicketInterface $ticket,
                private readonly \DateTimeImmutable $scheduledDate,
                private readonly \DateTimeImmutable $assignedAt,
                private readonly int $estimatedMinutes,
            ) {
            }

            public function getId(): string
            {
                return 'assignment-'.$this->ticket->getId();
            }

            public function getWorkerId(): string
            {
                return $this->workerId;
            }

            public function getTicket(): TicketInterface
            {
                return $this->ticket;
            }

            public function getScheduledDate(): \DateTimeImmutable
            {
                return $this->scheduledDate;
            }

            public function getAssignedAt(): \DateTimeImmutable
            {
                return $this->assignedAt;
            }

            public function getEstimatedTimeMinutes(): int
            {
                return $this->estimatedMinutes;
            }

            public function getPriority(): ?string
            {
                return null;
            }

            public function isAutoAssigned(): bool
            {
                return true;
            }
        };
    }

    private function createTicketStub(
        string $ticketId,
        string $status = TicketInterface::STATUS_IN_PROGRESS,
    ): TicketInterface {
        $category = new TicketCategory('cat-stub', 'Stub', null, 15);

        return new class($ticketId, $status, $category) implements TicketInterface {
            private ?\DateTimeImmutable $closedAt = null;

            public function __construct(
                private string $id,
                private string $status,
                private TicketCategory $category,
            ) {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getClient(): \App\Modules\Clients\Domain\ClientInterface
            {
                return new class implements \App\Modules\Clients\Domain\ClientInterface {
                    public function getId(): string
                    {
                        return 'client-stub';
                    }

                    public function getEmail(): ?string
                    {
                        return null;
                    }

                    public function getPhone(): ?string
                    {
                        return null;
                    }

                    public function getFirstName(): ?string
                    {
                        return null;
                    }

                    public function getLastName(): ?string
                    {
                        return null;
                    }

                    public function getFullName(): ?string
                    {
                        return null;
                    }

                    public function hasContactData(): bool
                    {
                        return false;
                    }

                    public function isAnonymous(): bool
                    {
                        return true;
                    }

                    public function getCreatedAt(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable();
                    }

                    public function getUpdatedAt(): ?\DateTimeImmutable
                    {
                        return null;
                    }

                    public function getIdentifiedAt(): ?\DateTimeImmutable
                    {
                        return null;
                    }

                    public function identify(string $email, ?string $phone = null, ?string $firstName = null, ?string $lastName = null, ?\DateTimeImmutable $identifiedAt = null): void
                    {
                    }

                    public function updateContact(?string $email = null, ?string $phone = null): void
                    {
                    }

                    public function updatePersonalData(?string $firstName = null, ?string $lastName = null): void
                    {
                    }
                };
            }

            public function getCategory(): \App\Modules\TicketCategories\Domain\TicketCategoryInterface
            {
                return $this->category;
            }

            public function getTitle(): ?string
            {
                return null;
            }

            public function getDescription(): ?string
            {
                return null;
            }

            public function getStatus(): string
            {
                return $this->status;
            }

            public function getCreatedAt(): \DateTimeInterface
            {
                return new \DateTimeImmutable();
            }

            public function getUpdatedAt(): ?\DateTimeInterface
            {
                return null;
            }

            public function getClosedAt(): ?\DateTimeInterface
            {
                return $this->closedAt;
            }

            public function getClosedByWorkerId(): ?string
            {
                return null;
            }

            public function changeStatus(string $status): void
            {
                $this->status = $status;
            }

            public function close(\App\Modules\Authentication\Domain\WorkerInterface $worker, ?\DateTimeImmutable $closedAt = null): void
            {
                $this->status = TicketInterface::STATUS_CLOSED;
                $this->closedAt = $closedAt ?? new \DateTimeImmutable();
            }

            public function isClosed(): bool
            {
                return TicketInterface::STATUS_CLOSED === $this->status;
            }

            public function isInProgress(): bool
            {
                return TicketInterface::STATUS_IN_PROGRESS === $this->status;
            }

            public function isAwaitingResponse(): bool
            {
                return TicketInterface::STATUS_AWAITING_RESPONSE === $this->status;
            }

            public function isAwaitingCustomer(): bool
            {
                return TicketInterface::STATUS_AWAITING_CUSTOMER === $this->status;
            }

            public function updateDescription(?string $description): void
            {
            }

            public function updateTitle(?string $title): void
            {
            }
        };
    }
}
