<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Phone;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\BackendForFrontend\Worker\Phone\Service\WorkerPhoneService;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WorkerPhoneServiceTest extends TestCase
{
    private AuthenticationServiceInterface&MockObject $authenticationService;

    private TicketServiceInterface&MockObject $ticketService;

    private WorkerScheduleServiceInterface&MockObject $workerScheduleService;

    private WorkerInterface&MockObject $worker;

    private WorkerPhoneService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationService = $this->createMock(AuthenticationServiceInterface::class);
        $this->ticketService = $this->createMock(TicketServiceInterface::class);
        $this->workerScheduleService = $this->createMock(WorkerScheduleServiceInterface::class);
        $this->worker = $this->createMock(WorkerInterface::class);
        $this->worker
            ->method('getId')
            ->willReturn('worker-1');

        $this->authenticationService
            ->method('getWorkerById')
            ->with('worker-1')
            ->willReturn($this->worker);

        $this->service = new WorkerPhoneService(
            $this->authenticationService,
            $this->ticketService,
            $this->workerScheduleService,
            static fn (): string => 'call-123',
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        );
    }

    public function testStartCallStopsActiveTicketsAndReturnsPausedSummary(): void
    {
        $activeTicket = $this->createMock(TicketInterface::class);
        $activeTicket
            ->method('getId')
            ->willReturn('ticket-active');
        $activeTicket
            ->method('getStatus')
            ->willReturn('in_progress');

        $updatedTicket = $this->createMock(TicketInterface::class);
        $updatedTicket
            ->method('getStatus')
            ->willReturn('awaiting_response');

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketsInProgress')
            ->with($this->worker)
            ->willReturn([$activeTicket]);

        $this->ticketService
            ->expects(self::once())
            ->method('stopTicketWork')
            ->with($activeTicket, $this->worker);

        $this->ticketService
            ->expects(self::once())
            ->method('updateTicketStatus')
            ->with($activeTicket, TicketInterface::STATUS_AWAITING_RESPONSE)
            ->willReturn($updatedTicket);

        $result = $this->service->startCall('worker-1');

        self::assertSame('call-123', $result['callId'] ?? null);
        self::assertInstanceOf(\DateTimeImmutable::class, $result['startTime'] ?? null);
        self::assertSame('2024-01-01T10:00:00+00:00', $result['startTime']->format(DATE_ATOM));

        $paused = $result['pausedTickets'] ?? null;
        self::assertIsArray($paused);
        self::assertCount(1, $paused);
        self::assertSame([
            'ticketId' => 'ticket-active',
            'previousStatus' => 'in_progress',
            'newStatus' => 'waiting',
        ], $paused[0]);
    }

    public function testEndCallWithTicketRegistersTimeAddsNoteAndSchedulesTicket(): void
    {
        $this->ticketService
            ->method('getTicketsInProgress')
            ->willReturn([]);

        $this->service->startCall('worker-1');

        $ticket = $this->createMock(TicketInterface::class);
        $ticket
            ->method('getId')
            ->willReturn('ticket-new');

        $updatedTicket = $this->createMock(TicketInterface::class);
        $updatedTicket
            ->method('getId')
            ->willReturn('ticket-new');
        $updatedTicket
            ->method('getStatus')
            ->willReturn('in_progress');
        $updatedTicket
            ->method('getUpdatedAt')
            ->willReturn(new \DateTimeImmutable('2024-01-01T10:10:00+00:00'));

        $this->ticketService
            ->expects(self::exactly(2))
            ->method('getTicketById')
            ->with('ticket-new')
            ->willReturnOnConsecutiveCalls($ticket, $updatedTicket);

        $this->ticketService
            ->expects(self::once())
            ->method('registerManualTimeEntry')
            ->with($ticket, $this->worker, 10, true);

        $this->ticketService
            ->expects(self::once())
            ->method('updateTicketStatus')
            ->with($ticket, TicketInterface::STATUS_IN_PROGRESS)
            ->willReturn($updatedTicket);

        $this->ticketService
            ->expects(self::once())
            ->method('startTicketWork')
            ->with($updatedTicket, $this->worker);

        $this->ticketService
            ->expects(self::once())
            ->method('addTicketNote')
            ->with($updatedTicket, $this->worker, 'Conversation summary');

        $this->ticketService
            ->expects(self::once())
            ->method('getWorkerTimeSpentOnTicket')
            ->with($updatedTicket, $this->worker)
            ->willReturn(42);

        $assignment = $this->createMock(WorkerScheduleAssignmentInterface::class);
        $assignment
            ->method('getScheduledDate')
            ->willReturn(new \DateTimeImmutable('2024-01-01'));
        $assignment
            ->method('getTicket')
            ->willReturn($updatedTicket);

        $this->workerScheduleService
            ->expects(self::once())
            ->method('assignTicketToWorker')
            ->with('ticket-new', 'worker-1', new \DateTimeImmutable('2024-01-01'), 'worker-1')
            ->willReturn($assignment);

        $this->workerScheduleService
            ->expects(self::never())
            ->method('getWorkerScheduleForPeriod');

        $endResult = $this->service->endCall(
            'worker-1',
            'call-123',
            'ticket-new',
            600,
            'Conversation summary',
            new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2024-01-01T10:10:00+00:00'),
        );

        $callPayload = $endResult['call'] ?? null;
        self::assertIsArray($callPayload);
        self::assertSame('call-123', $callPayload['id'] ?? null);
        self::assertSame('ticket-new', $callPayload['ticketId'] ?? null);
        self::assertSame(600, $callPayload['duration'] ?? null);
        self::assertEquals(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'), $callPayload['startTime'] ?? null);
        self::assertEquals(new \DateTimeImmutable('2024-01-01T10:10:00+00:00'), $callPayload['endTime'] ?? null);

        $ticketPayload = $endResult['ticket'] ?? null;
        self::assertIsArray($ticketPayload);
        self::assertSame('ticket-new', $ticketPayload['id'] ?? null);
        self::assertSame('in_progress', $ticketPayload['status'] ?? null);
        self::assertSame(42, $ticketPayload['timeSpent'] ?? null);
        self::assertInstanceOf(\DateTimeImmutable::class, $ticketPayload['scheduledDate'] ?? null);
        self::assertSame('2024-01-01', $ticketPayload['scheduledDate']->format('Y-m-d'));
        self::assertInstanceOf(\DateTimeImmutable::class, $ticketPayload['updatedAt'] ?? null);
    }

    public function testEndCallWithoutTicketRestoresPreviousTicketAndAddsNote(): void
    {
        $previousTicket = $this->createMock(TicketInterface::class);
        $previousTicket
            ->method('getId')
            ->willReturn('ticket-prev');
        $previousTicket
            ->method('getStatus')
            ->willReturn('in_progress');

        $pausedTicket = $this->createMock(TicketInterface::class);
        $pausedTicket
            ->method('getId')
            ->willReturn('ticket-prev');
        $pausedTicket
            ->method('getStatus')
            ->willReturnOnConsecutiveCalls('awaiting_response', 'in_progress');
        $pausedTicket
            ->method('getUpdatedAt')
            ->willReturn(new \DateTimeImmutable('2024-01-01T10:05:00+00:00'));

        $this->ticketService
            ->method('getTicketsInProgress')
            ->willReturn([$previousTicket]);

        $expectedStatusSequence = [
            [$previousTicket, TicketInterface::STATUS_AWAITING_RESPONSE],
            [$pausedTicket, TicketInterface::STATUS_IN_PROGRESS],
        ];

        $this->ticketService
            ->expects(self::exactly(2))
            ->method('updateTicketStatus')
            ->willReturnCallback(function (TicketInterface $ticketArg, string $statusArg) use (&$expectedStatusSequence, $pausedTicket): TicketInterface {
                $expected = array_shift($expectedStatusSequence);
                TestCase::assertNotNull($expected);
                TestCase::assertSame($expected[0], $ticketArg);
                TestCase::assertSame($expected[1], $statusArg);

                return $pausedTicket;
            });

        $this->ticketService
            ->expects(self::once())
            ->method('stopTicketWork')
            ->with($previousTicket, $this->worker);

        $this->service->startCall('worker-1');

        $this->ticketService
            ->expects(self::exactly(2))
            ->method('getTicketById')
            ->with('ticket-prev')
            ->willReturn($pausedTicket);

        $this->ticketService
            ->expects(self::once())
            ->method('startTicketWork')
            ->with($pausedTicket, $this->worker);

        $this->ticketService
            ->expects(self::once())
            ->method('addTicketNote')
            ->with($pausedTicket, $this->worker, 'Follow up');

        $this->workerScheduleService
            ->expects(self::never())
            ->method('assignTicketToWorker');

        $result = $this->service->endCall(
            'worker-1',
            'call-123',
            null,
            0,
            'Follow up',
            new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2024-01-01T10:05:00+00:00'),
        );

        self::assertArrayNotHasKey('ticket', $result);

        $previousPayload = $result['previousTicket'] ?? null;
        self::assertIsArray($previousPayload);
        self::assertSame('ticket-prev', $previousPayload['id'] ?? null);
        self::assertSame('in_progress', $previousPayload['status'] ?? null);
        self::assertInstanceOf(\DateTimeImmutable::class, $previousPayload['updatedAt'] ?? null);
    }
}
