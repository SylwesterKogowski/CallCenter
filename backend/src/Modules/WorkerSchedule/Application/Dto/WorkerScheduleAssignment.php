<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Application\Dto;

use App\Modules\Tickets\Domain\TicketInterface;

final class WorkerScheduleAssignment implements WorkerScheduleAssignmentInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $workerId,
        private readonly TicketInterface $ticket,
        private readonly \DateTimeImmutable $scheduledDate,
        private readonly \DateTimeImmutable $assignedAt,
        private readonly int $estimatedTimeMinutes,
        private readonly ?int $priority,
        private readonly bool $isAutoAssigned,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
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
        return $this->estimatedTimeMinutes;
    }

    public function getPriority(): ?string
    {
        return $this->priorityToLabel($this->priority);
    }

    public function isAutoAssigned(): bool
    {
        return $this->isAutoAssigned;
    }

    private function priorityToLabel(?int $priority): ?string
    {
        if (null === $priority) {
            return null;
        }

        return match (true) {
            $priority >= 8 => 'high',
            $priority <= 3 => 'low',
            default => 'medium',
        };
    }
}
