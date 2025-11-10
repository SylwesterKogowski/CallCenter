<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Domain;

interface WorkerAvailabilityInterface
{
    public function getId(): string;

    public function getWorkerId(): string;

    public function getStartDatetime(): \DateTimeImmutable;

    public function getEndDatetime(): \DateTimeImmutable;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function updateAvailability(
        \DateTimeImmutable $startDatetime,
        \DateTimeImmutable $endDatetime,
    ): void;

    public function isAvailableAt(\DateTimeImmutable $datetime): bool;

    public function isAvailableInRange(
        \DateTimeImmutable $startDatetime,
        \DateTimeImmutable $endDatetime,
    ): bool;

    public function getDurationMinutes(): int;

    public function getDate(): \DateTimeImmutable;

    public function overlapsWith(WorkerAvailabilityInterface $other): bool;

    public function isOnSameDay(\DateTimeImmutable $date): bool;
}
