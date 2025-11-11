<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Domain;

final class WorkerAvailability implements WorkerAvailabilityInterface
{
    private string $id;

    private string $workerId;

    private \DateTimeImmutable $startDatetime;

    private \DateTimeImmutable $endDatetime;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workerId,
        \DateTimeImmutable $startDatetime,
        \DateTimeImmutable $endDatetime,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $this->assertNonEmptyUuid($id, 'Availability id cannot be empty.');
        $this->workerId = $this->assertNonEmptyUuid($workerId, 'Worker id cannot be empty.');
        $this->assertDateRangeIsValid($startDatetime, $endDatetime);

        $this->startDatetime = $startDatetime;
        $this->endDatetime = $endDatetime;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function getStartDatetime(): \DateTimeImmutable
    {
        return $this->startDatetime;
    }

    public function getEndDatetime(): \DateTimeImmutable
    {
        return $this->endDatetime;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateAvailability(\DateTimeImmutable $startDatetime, \DateTimeImmutable $endDatetime): void
    {
        $this->assertDateRangeIsValid($startDatetime, $endDatetime);

        $this->startDatetime = $startDatetime;
        $this->endDatetime = $endDatetime;
        $this->touch();
    }

    public function isAvailableAt(\DateTimeImmutable $datetime): bool
    {
        return $datetime >= $this->startDatetime && $datetime <= $this->endDatetime;
    }

    public function isAvailableInRange(
        \DateTimeImmutable $startDatetime,
        \DateTimeImmutable $endDatetime,
    ): bool {
        if ($endDatetime < $startDatetime) {
            return false;
        }

        return $this->startDatetime <= $startDatetime && $this->endDatetime >= $endDatetime;
    }

    public function getDurationMinutes(): int
    {
        $seconds = $this->endDatetime->getTimestamp() - $this->startDatetime->getTimestamp();

        return (int) max(0, intdiv($seconds, 60));
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->startDatetime->setTime(0, 0);
    }

    public function overlapsWith(WorkerAvailabilityInterface $other): bool
    {
        if ($other->getWorkerId() !== $this->workerId) {
            return false;
        }

        if (!$this->isOnSameDay($other->getStartDatetime())) {
            return false;
        }

        return $this->startDatetime < $other->getEndDatetime()
            && $this->endDatetime > $other->getStartDatetime();
    }

    public function isOnSameDay(\DateTimeImmutable $date): bool
    {
        return $this->startDatetime->format('Y-m-d') === $date->format('Y-m-d');
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function assertNonEmptyUuid(string $value, string $message): string
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($message);
        }

        return $normalized;
    }

    private function assertDateRangeIsValid(
        \DateTimeImmutable $startDatetime,
        \DateTimeImmutable $endDatetime,
    ): void {
        if ($endDatetime < $startDatetime) {
            throw new \InvalidArgumentException('End datetime must be greater than or equal to start datetime.');
        }

        if ($startDatetime->format('Y-m-d') !== $endDatetime->format('Y-m-d')) {
            throw new \InvalidArgumentException('Availability must not span across multiple days.');
        }
    }
}
