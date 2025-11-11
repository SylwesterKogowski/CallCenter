<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WorkerSchedule\Application;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Dto\WorkerBacklogFilters;
use App\Modules\Tickets\Application\TicketBacklogServiceInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerSchedulePredictionInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleService;
use App\Modules\WorkerSchedule\Domain\WorkerScheduleRepositoryInterface;
use App\Modules\WorkerSchedule\Infrastructure\Persistence\Doctrine\Entity\WorkerSchedule;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WorkerScheduleServiceTest extends TestCase
{
    private WorkerScheduleRepositoryInterface&MockObject $repository;

    private TicketServiceInterface&MockObject $ticketService;

    private TicketBacklogServiceInterface&MockObject $ticketBacklogService;

    private WorkerAvailabilityServiceInterface&MockObject $workerAvailabilityService;

    private AuthorizationServiceInterface&MockObject $authorizationService;

    private TicketCategoryServiceInterface&MockObject $ticketCategoryService;

    private AuthenticationServiceInterface&MockObject $authenticationService;

    private WorkerScheduleService $service;

    private int $uuidCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(WorkerScheduleRepositoryInterface::class);
        $this->ticketService = $this->createMock(TicketServiceInterface::class);
        $this->ticketBacklogService = $this->createMock(TicketBacklogServiceInterface::class);
        $this->workerAvailabilityService = $this->createMock(WorkerAvailabilityServiceInterface::class);
        $this->authorizationService = $this->createMock(AuthorizationServiceInterface::class);
        $this->ticketCategoryService = $this->createMock(TicketCategoryServiceInterface::class);
        $this->authenticationService = $this->createMock(AuthenticationServiceInterface::class);

        $this->service = new WorkerScheduleService(
            $this->repository,
            $this->ticketService,
            $this->ticketBacklogService,
            $this->workerAvailabilityService,
            $this->authorizationService,
            $this->ticketCategoryService,
            $this->authenticationService,
            function (): string {
                return sprintf('00000000-0000-0000-0000-%012d', $this->uuidCounter++);
            },
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2024-06-01T10:00:00+00:00'),
        );
    }

    public function testGetWorkerScheduleForWeekReturnsAssignments(): void
    {
        $workerId = 'worker-1';
        $ticketId = 'ticket-1';
        $scheduledDate = (new \DateTimeImmutable('+10 days'))->setTime(0, 0);
        $assignedAt = (new \DateTimeImmutable('+5 days'))->setTime(8, 0);
        $schedule = new WorkerSchedule(
            '00000000-0000-0000-0000-000000000999',
            $workerId,
            $ticketId,
            $scheduledDate,
            $assignedAt,
            $workerId,
            false,
            9,
        );

        $category = $this->createCategory('cat-1', 30);
        $ticket = $this->createTicket($ticketId, $category, TicketInterface::STATUS_IN_PROGRESS);
        $worker = $this->createWorker($workerId);

        $this->repository
            ->method('findByWorkerAndPeriod')
            ->willReturn([$schedule]);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with($ticketId)
            ->willReturn($ticket);

        $this->authenticationService
            ->method('getWorkerById')
            ->with($workerId)
            ->willReturn($worker);

        $this->ticketService
            ->expects(self::once())
            ->method('calculateWorkerEfficiency')
            ->with($worker, $category)
            ->willReturn(1.5);

        $weekStart = $scheduledDate->sub(new \DateInterval('P2D'));
        $assignments = [...$this->service->getWorkerScheduleForWeek($workerId, $weekStart)];

        self::assertCount(1, $assignments);
        $assignment = $assignments[0];

        self::assertSame($ticketId, $assignment->getTicket()->getId());
        self::assertSame($workerId, $assignment->getWorkerId());
        self::assertSame($scheduledDate->format('Y-m-d'), $assignment->getScheduledDate()->format('Y-m-d'));
        self::assertSame(20, $assignment->getEstimatedTimeMinutes());
        self::assertSame('high', $assignment->getPriority());
        self::assertFalse($assignment->isAutoAssigned());
    }

    public function testAssignTicketToWorkerCreatesAssignment(): void
    {
        $workerId = 'worker-2';
        $ticketId = 'ticket-200';
        $date = (new \DateTimeImmutable('+15 days'))->setTime(0, 0);
        $category = $this->createCategory('cat-2', 45);
        $ticket = $this->createTicket($ticketId, $category, TicketInterface::STATUS_AWAITING_RESPONSE);
        $worker = $this->createWorker($workerId);
        $availability = $this->createAvailabilitySlot($workerId, $date, 120);

        $this->ticketService
            ->method('getTicketById')
            ->with($ticketId)
            ->willReturn($ticket);

        $this->authorizationService
            ->expects(self::once())
            ->method('canWorkerAccessCategory')
            ->with($workerId, $category->getId())
            ->willReturn(true);

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('getWorkerAvailabilityForWeek')
            ->with($workerId, $date)
            ->willReturn([$availability]);

        $this->repository
            ->expects(self::once())
            ->method('findOneByWorkerTicketAndDate')
            ->with($ticketId, $workerId, $date)
            ->willReturn(null);

        $savedAssignments = [];

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (WorkerSchedule $entity) use (&$savedAssignments): void {
                $savedAssignments[] = $entity;
            });

        $this->authenticationService
            ->method('getWorkerById')
            ->with($workerId)
            ->willReturn($worker);

        $this->ticketService
            ->expects(self::once())
            ->method('calculateWorkerEfficiency')
            ->with($worker, $category)
            ->willReturn(1.0);

        $assignment = $this->service->assignTicketToWorker($ticketId, $workerId, $date, $workerId);

        self::assertInstanceOf(WorkerScheduleAssignmentInterface::class, $assignment);
        self::assertCount(1, $savedAssignments);
        self::assertSame($ticketId, $assignment->getTicket()->getId());
        self::assertFalse($assignment->isAutoAssigned());
        self::assertSame(45, $assignment->getEstimatedTimeMinutes());
    }

    public function testAutoAssignTicketsForWorkerRespectsAvailability(): void
    {
        $workerId = 'worker-3';
        $weekStart = (new \DateTimeImmutable('+21 days'))->setTime(0, 0);
        $worker = $this->createWorker($workerId);
        $category = $this->createCategory('cat-backlog', 60);

        $this->repository
            ->method('findByWorkerAndPeriod')
            ->willReturn([]);

        $this->repository
            ->method('findOneByWorkerTicketAndDate')
            ->willReturn(null);

        $availabilitySlots = [
            $this->createAvailabilitySlot($workerId, $weekStart, 180),
            $this->createAvailabilitySlot($workerId, $weekStart->add(new \DateInterval('P1D')), 60),
        ];

        $this->workerAvailabilityService
            ->method('getWorkerAvailabilityForWeek')
            ->with($workerId, $weekStart)
            ->willReturn($availabilitySlots);

        $ticketOne = $this->createTicket('ticket-1', $category, TicketInterface::STATUS_AWAITING_RESPONSE);
        $ticketTwo = $this->createTicket('ticket-2', $category, TicketInterface::STATUS_AWAITING_CUSTOMER);

        $backlogResult = new class($ticketOne, $ticketTwo) implements \App\Modules\Tickets\Application\Dto\WorkerBacklogResultInterface {
            /**
             * @var array<int, \App\Modules\Tickets\Application\Dto\WorkerBacklogTicketInterface>
             */
            private array $tickets;

            public function __construct(TicketInterface $one, TicketInterface $two)
            {
                $this->tickets = [
                    new class($one) implements \App\Modules\Tickets\Application\Dto\WorkerBacklogTicketInterface {
                        public function __construct(private TicketInterface $ticket)
                        {
                        }

                        public function getTicket(): TicketInterface
                        {
                            return $this->ticket;
                        }

                        public function getClient(): \App\Modules\Clients\Domain\ClientInterface
                        {
                            throw new \LogicException('Not needed in tests.');
                        }

                        public function getCategory(): TicketCategoryInterface
                        {
                            return $this->ticket->getCategory();
                        }

                        public function getPriority(): string
                        {
                            return 'medium';
                        }

                        public function getEstimatedTimeMinutes(): int
                        {
                            return 60;
                        }

                        public function getCreatedAt(): \DateTimeInterface
                        {
                            return new \DateTimeImmutable();
                        }

                        public function getScheduledDate(): ?\DateTimeInterface
                        {
                            return null;
                        }
                    },
                    new class($two) implements \App\Modules\Tickets\Application\Dto\WorkerBacklogTicketInterface {
                        public function __construct(private TicketInterface $ticket)
                        {
                        }

                        public function getTicket(): TicketInterface
                        {
                            return $this->ticket;
                        }

                        public function getClient(): \App\Modules\Clients\Domain\ClientInterface
                        {
                            throw new \LogicException('Not needed in tests.');
                        }

                        public function getCategory(): TicketCategoryInterface
                        {
                            return $this->ticket->getCategory();
                        }

                        public function getPriority(): string
                        {
                            return 'medium';
                        }

                        public function getEstimatedTimeMinutes(): int
                        {
                            return 60;
                        }

                        public function getCreatedAt(): \DateTimeInterface
                        {
                            return new \DateTimeImmutable();
                        }

                        public function getScheduledDate(): ?\DateTimeInterface
                        {
                            return null;
                        }
                    },
                ];
            }

            public function getTickets(): iterable
            {
                return $this->tickets;
            }

            public function getTotal(): int
            {
                return \count($this->tickets);
            }
        };

        $this->ticketBacklogService
            ->expects(self::once())
            ->method('getWorkerBacklog')
            ->with($workerId, self::isInstanceOf(WorkerBacklogFilters::class))
            ->willReturn($backlogResult);

        $this->authenticationService
            ->method('getWorkerById')
            ->with($workerId)
            ->willReturn($worker);

        $this->ticketService
            ->expects(self::once())
            ->method('calculateWorkerEfficiency')
            ->with($worker, $category)
            ->willReturn(1.0);

        $saved = 0;
        $this->repository
            ->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(static function () use (&$saved): void {
                ++$saved;
            });

        $assignments = [...$this->service->autoAssignTicketsForWorker($workerId, $weekStart)];

        self::assertCount(2, $assignments);
        self::assertSame(2, $saved);
        self::assertSame('ticket-1', $assignments[0]->getTicket()->getId());
        self::assertSame('ticket-2', $assignments[1]->getTicket()->getId());
    }

    public function testGetPredictionsForWeekCalculatesForecast(): void
    {
        $workerId = 'worker-forecast';
        $weekStart = (new \DateTimeImmutable('+28 days'))->setTime(0, 0);
        $category = $this->createCategory('cat-forecast', 30);
        $worker = $this->createWorker($workerId);

        $slots = [
            $this->createAvailabilitySlot($workerId, $weekStart, 300),
            $this->createAvailabilitySlot($workerId, $weekStart->add(new \DateInterval('P1D')), 240),
        ];

        $this->workerAvailabilityService
            ->method('getWorkerAvailabilityForWeek')
            ->with($workerId, $weekStart)
            ->willReturn($slots);

        $this->authorizationService
            ->method('getAssignedCategoryIds')
            ->with($workerId)
            ->willReturn(['cat-forecast']);

        $this->ticketCategoryService
            ->method('getCategoriesByIds')
            ->with(['cat-forecast'])
            ->willReturn([$category]);

        $this->authenticationService
            ->method('getWorkerById')
            ->with($workerId)
            ->willReturn($worker);

        $this->ticketService
            ->method('calculateWorkerEfficiency')
            ->with($worker, $category)
            ->willReturn(0.85);

        $predictions = [...$this->service->getPredictionsForWeek($workerId, $weekStart)];

        self::assertCount(7, $predictions);
        self::assertContainsOnlyInstancesOf(WorkerSchedulePredictionInterface::class, $predictions);

        $first = $predictions[0];
        self::assertSame($weekStart->format('Y-m-d'), $first->getDate()->format('Y-m-d'));
        self::assertSame(0.85, $first->getEfficiency());
        self::assertSame(300, $first->getAvailableTimeMinutes());
        self::assertSame(8, $first->getPredictedTicketCount());
    }

    private function createTicket(
        string $ticketId,
        TicketCategoryInterface $category,
        string $status,
    ): TicketInterface {
        $ticket = $this->createMock(TicketInterface::class);
        $ticket
            ->method('getId')
            ->willReturn($ticketId);
        $ticket
            ->method('getCategory')
            ->willReturn($category);
        $ticket
            ->method('getStatus')
            ->willReturn($status);

        return $ticket;
    }

    private function createCategory(string $categoryId, int $defaultResolution): TicketCategoryInterface
    {
        $category = $this->createMock(TicketCategoryInterface::class);
        $category
            ->method('getId')
            ->willReturn($categoryId);
        $category
            ->method('getDefaultResolutionTimeMinutes')
            ->willReturn($defaultResolution);

        return $category;
    }

    private function createWorker(string $workerId): WorkerInterface
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker
            ->method('getId')
            ->willReturn($workerId);

        return $worker;
    }

    private function createAvailabilitySlot(
        string $workerId,
        \DateTimeImmutable $date,
        int $durationMinutes,
    ): WorkerAvailabilityInterface {
        $slot = $this->createMock(WorkerAvailabilityInterface::class);
        $slot
            ->method('getWorkerId')
            ->willReturn($workerId);
        $slot
            ->method('getDate')
            ->willReturn($date);
        $slot
            ->method('getDurationMinutes')
            ->willReturn($durationMinutes);

        return $slot;
    }
}
