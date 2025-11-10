<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Application\Dto;

use App\Modules\Tickets\Domain\TicketInterface;

interface WorkerScheduleAssignmentInterface
{
    public function getId(): string;

    public function getWorkerId(): string;

    public function getTicket(): TicketInterface;

    public function getScheduledDate(): \DateTimeImmutable;

    public function getAssignedAt(): \DateTimeImmutable;

    public function getEstimatedTimeMinutes(): int;

    public function getPriority(): ?string;

    public function isAutoAssigned(): bool;
}


