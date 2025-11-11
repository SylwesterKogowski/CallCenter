<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Phone\Service;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\Exception\ActiveTicketWorkExistsException;
use App\Modules\Tickets\Domain\Exception\TicketWorkNotFoundException;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;
use Symfony\Component\Uid\Uuid;

final class WorkerPhoneService implements WorkerPhoneServiceInterface
{
    private const FRONT_STATUS_WAITING = 'waiting';
    private const FRONT_STATUS_AWAITING_CLIENT = 'awaiting_client';

    /**
     * @var callable():string
     */
    private $callIdFactory;

    /**
     * @var callable():\DateTimeImmutable
     */
    private $nowFactory;

    /**
     * @var array<string, array{
     *     workerId: string,
     *     startTime: \DateTimeImmutable,
     *     pausedTickets: list<array{
     *         ticketId: string,
     *         previousStatus: string,
     *         newStatus: string
     *     }>,
     *     primaryTicketId: ?string
     * }>
     */
    private array $activeCalls = [];

    public function __construct(
        private readonly AuthenticationServiceInterface $authenticationService,
        private readonly TicketServiceInterface $ticketService,
        private readonly WorkerScheduleServiceInterface $workerScheduleService,
        ?callable $callIdFactory = null,
        ?callable $nowFactory = null,
    ) {
        $this->callIdFactory = $callIdFactory ?? static fn (): string => Uuid::v7()->toRfc4122();
        $this->nowFactory = $nowFactory ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable();
    }

    public function startCall(string $workerId): array
    {
        $worker = $this->getWorker($workerId);
        $ticketsInProgress = $this->ticketService->getTicketsInProgress($worker);

        $pausedTickets = [];

        foreach ($ticketsInProgress as $ticket) {
            $pausedTickets[] = $this->pauseTicket($ticket, $worker);
        }

        $callId = $this->nextCallId();
        $startTime = $this->now();

        $this->activeCalls[$callId] = [
            'workerId' => $workerId,
            'startTime' => $startTime,
            'pausedTickets' => $pausedTickets,
            'primaryTicketId' => $pausedTickets[0]['ticketId'] ?? null,
        ];

        return [
            'callId' => $callId,
            'startTime' => $startTime,
            'pausedTickets' => $pausedTickets,
        ];
    }

    public function endCall(
        string $workerId,
        string $callId,
        ?string $ticketId,
        int $duration,
        ?string $notes,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
    ): array {
        $context = $this->getCallContext($callId);

        if ($context && $context['workerId'] !== $workerId) {
            throw new \RuntimeException(sprintf('Call "%s" does not belong to worker "%s".', $callId, $workerId));
        }

        $worker = $this->getWorker($workerId);

        try {
            $result = [
                'call' => [
                    'id' => $callId,
                    'ticketId' => $ticketId,
                    'duration' => $duration,
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                ],
            ];

            if (null !== $ticketId) {
                $result += $this->finalizeCallWithTicket(
                    $worker,
                    $ticketId,
                    $duration,
                    $notes,
                    $startTime,
                    $endTime,
                );
            } elseif ($context) {
                $previousTicketPayload = $this->resumePreviousTicket(
                    $context['primaryTicketId'],
                    $worker,
                    $notes,
                );

                if (null !== $previousTicketPayload) {
                    $result['previousTicket'] = $previousTicketPayload;
                }
            }

            return $result;
        } finally {
            unset($this->activeCalls[$callId]);
        }
    }

    /**
     * @return array{ticketId: string, previousStatus: string, newStatus: string}
     */
    private function pauseTicket(TicketInterface $ticket, WorkerInterface $worker): array
    {
        $previousStatus = $ticket->getStatus();

        try {
            $this->ticketService->stopTicketWork($ticket, $worker);
        } catch (TicketWorkNotFoundException) {
            // No active work session to stop – continue with status update.
        }

        $updatedTicket = $this->ticketService->updateTicketStatus(
            $ticket,
            TicketInterface::STATUS_AWAITING_RESPONSE,
        );

        return [
            'ticketId' => $ticket->getId(),
            'previousStatus' => $this->normalizeStatusForResponse($previousStatus),
            'newStatus' => $this->normalizeStatusForResponse($updatedTicket->getStatus()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finalizeCallWithTicket(
        WorkerInterface $worker,
        string $ticketId,
        int $duration,
        ?string $notes,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
    ): array {
        $ticket = $this->requireTicket($ticketId);
        $minutes = $this->calculateDurationMinutes($startTime, $endTime, $duration);

        $this->ticketService->registerManualTimeEntry(
            $ticket,
            $worker,
            $minutes,
            true,
        );

        $updatedTicket = $this->ticketService->updateTicketStatus(
            $ticket,
            TicketInterface::STATUS_IN_PROGRESS,
        );

        try {
            $this->ticketService->startTicketWork($updatedTicket, $worker);
        } catch (ActiveTicketWorkExistsException) {
            // The ticket is already in progress – ignore.
        }

        $refreshedTicket = $this->ticketService->getTicketById($updatedTicket->getId()) ?? $updatedTicket;

        if (null !== $notes && '' !== trim($notes)) {
            $this->ticketService->addTicketNote($refreshedTicket, $worker, $notes);
        }

        $scheduledDate = $endTime->setTime(0, 0);
        $assignment = $this->ensureTicketScheduled(
            $refreshedTicket,
            $worker->getId(),
            $scheduledDate,
        );

        $scheduledPayloadDate = $assignment?->getScheduledDate() ?? $scheduledDate;
        $timeSpent = $this->ticketService->getWorkerTimeSpentOnTicket($refreshedTicket, $worker);
        $updatedAt = $this->toImmutableDate($refreshedTicket->getUpdatedAt()) ?? $endTime;

        return [
            'ticket' => [
                'id' => $refreshedTicket->getId(),
                'status' => $this->normalizeStatusForResponse($refreshedTicket->getStatus()),
                'timeSpent' => $timeSpent,
                'scheduledDate' => $scheduledPayloadDate,
                'updatedAt' => $updatedAt,
            ],
        ];
    }

    /**
     * @return array{id: string, status: string, updatedAt: \DateTimeImmutable}|null
     */
    private function resumePreviousTicket(
        ?string $ticketId,
        WorkerInterface $worker,
        ?string $notes,
    ): ?array {
        if (null === $ticketId || '' === $ticketId) {
            return null;
        }

        $ticket = $this->ticketService->getTicketById($ticketId);

        if (null === $ticket) {
            return null;
        }

        $updatedTicket = $this->ticketService->updateTicketStatus(
            $ticket,
            TicketInterface::STATUS_IN_PROGRESS,
        );

        try {
            $this->ticketService->startTicketWork($updatedTicket, $worker);
        } catch (ActiveTicketWorkExistsException) {
            // Active session already exists – nothing to do.
        }

        $refreshedTicket = $this->ticketService->getTicketById($updatedTicket->getId()) ?? $updatedTicket;

        if (null !== $notes && '' !== trim($notes)) {
            $this->ticketService->addTicketNote($refreshedTicket, $worker, $notes);
        }

        $updatedAt = $this->toImmutableDate($refreshedTicket->getUpdatedAt()) ?? $this->now();

        return [
            'id' => $refreshedTicket->getId(),
            'status' => $this->normalizeStatusForResponse($refreshedTicket->getStatus()),
            'updatedAt' => $updatedAt,
        ];
    }

    private function ensureTicketScheduled(
        TicketInterface $ticket,
        string $workerId,
        \DateTimeImmutable $scheduledDate,
    ): ?WorkerScheduleAssignmentInterface {
        try {
            return $this->workerScheduleService->assignTicketToWorker(
                $ticket->getId(),
                $workerId,
                $scheduledDate,
                $workerId,
            );
        } catch (\Throwable) {
            foreach ($this->workerScheduleService->getWorkerScheduleForPeriod(
                $workerId,
                $scheduledDate,
                $scheduledDate,
            ) as $assignment) {
                if ($assignment->getTicket()->getId() === $ticket->getId()) {
                    return $assignment;
                }
            }
        }

        return null;
    }

    private function getWorker(string $workerId): WorkerInterface
    {
        $normalizedId = trim($workerId);

        if ('' === $normalizedId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        $worker = $this->authenticationService->getWorkerById($normalizedId);

        if (null === $worker) {
            throw new \RuntimeException(sprintf('Worker "%s" was not found.', $normalizedId));
        }

        return $worker;
    }

    private function requireTicket(string $ticketId): TicketInterface
    {
        $normalizedId = trim($ticketId);

        if ('' === $normalizedId) {
            throw new \InvalidArgumentException('Ticket id cannot be empty.');
        }

        $ticket = $this->ticketService->getTicketById($normalizedId);

        if (null === $ticket) {
            throw new \RuntimeException(sprintf('Ticket "%s" was not found.', $normalizedId));
        }

        return $ticket;
    }

    /**
     * @return array{
     *     workerId: string,
     *     startTime: \DateTimeImmutable,
     *     pausedTickets: list<array{
     *         ticketId: string,
     *         previousStatus: string,
     *         newStatus: string
     *     }>,
     *     primaryTicketId: ?string
     * }|null
     */
    private function getCallContext(string $callId): ?array
    {
        if (!isset($this->activeCalls[$callId])) {
            return null;
        }

        return $this->activeCalls[$callId];
    }

    private function nextCallId(): string
    {
        return ($this->callIdFactory)();
    }

    private function now(): \DateTimeImmutable
    {
        return ($this->nowFactory)();
    }

    private function calculateDurationMinutes(
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        int $durationSeconds,
    ): int {
        $diffSeconds = max(0, $endTime->getTimestamp() - $startTime->getTimestamp());
        $seconds = max($durationSeconds, $diffSeconds);

        return max(1, (int) round($seconds / 60));
    }

    private function normalizeStatusForResponse(string $status): string
    {
        return match ($status) {
            TicketInterface::STATUS_AWAITING_RESPONSE => self::FRONT_STATUS_WAITING,
            TicketInterface::STATUS_AWAITING_CUSTOMER => self::FRONT_STATUS_AWAITING_CLIENT,
            default => $status,
        };
    }

    private function toImmutableDate(?\DateTimeInterface $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        return \DateTimeImmutable::createFromInterface($value);
    }
}
