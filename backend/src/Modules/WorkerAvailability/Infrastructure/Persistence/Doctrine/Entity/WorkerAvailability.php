<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'worker_availability')]
#[ORM\Index(name: 'idx_worker_id', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_start_datetime', columns: ['start_datetime'])]
#[ORM\Index(name: 'idx_end_datetime', columns: ['end_datetime'])]
#[ORM\Index(name: 'idx_worker_datetime_range', columns: ['worker_id', 'start_datetime', 'end_datetime'])]
#[ORM\Index(name: 'idx_date_range', columns: ['start_datetime', 'end_datetime'])]
class WorkerAvailability implements WorkerAvailabilityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'guid', name: 'worker_id')]
    private string $workerId;

    #[ORM\Column(type: 'datetime_immutable', name: 'start_datetime')]
    private \DateTimeImmutable $startDatetime;

    #[ORM\Column(type: 'datetime_immutable', name: 'end_datetime')]
    private \DateTimeImmutable $endDatetime;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        string $id,
        string $workerId,
        \DateTimeImmutable $startDatetime,
        \DateTimeImmutable $endDatetime,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->assertNonEmptyUuid($id, 'Availability id cannot be empty.');
        $this->assertNonEmptyUuid($workerId, 'Worker id cannot be empty.');
        $this->assertDateRangeIsValid($startDatetime, $endDatetime);

        $this->id = $id;
        $this->workerId = $workerId;
        $this->startDatetime = $startDatetime;
        $this->endDatetime = $endDatetime;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
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

        return $this->startDatetime < $other->getEndDatetime() && $this->endDatetime > $other->getStartDatetime();
    }

    public function isOnSameDay(\DateTimeImmutable $date): bool
    {
        return $this->startDatetime->format('Y-m-d') === $date->format('Y-m-d');
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    private function assertNonEmptyUuid(string $value, string $message): void
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException($message);
        }
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

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
