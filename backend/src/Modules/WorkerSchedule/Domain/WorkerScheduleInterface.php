<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Domain;

interface WorkerScheduleInterface
{
    public function getId(): string;

    public function getWorkerId(): string;

    public function getTicketId(): string;

    public function getScheduledDate(): \DateTimeImmutable;

    public function getAssignedAt(): \DateTimeImmutable;

    public function getAssignedById(): ?string;

    public function isAutoAssigned(): bool;

    public function getPriority(): ?int;

    public function isOnDate(\DateTimeImmutable $date): bool;

    public function reassign(
        string $workerId,
        \DateTimeImmutable $scheduledDate,
        ?string $assignedById = null,
        bool $isAutoAssigned = false,
    ): void;

    public function setPriority(?int $priority): void;

    public function markAsAutoAssigned(): void;

    public function markAsManual(?string $assignedById = null): void;
}
