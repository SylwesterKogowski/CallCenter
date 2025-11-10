<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\WorkerSchedule\Domain\WorkerScheduleInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'worker_schedule')]
#[ORM\UniqueConstraint(name: 'unique_worker_ticket_date', columns: ['worker_id', 'ticket_id', 'scheduled_date'])]
#[ORM\Index(name: 'idx_worker_id', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_ticket_id', columns: ['ticket_id'])]
#[ORM\Index(name: 'idx_scheduled_date', columns: ['scheduled_date'])]
#[ORM\Index(name: 'idx_worker_date', columns: ['worker_id', 'scheduled_date'])]
#[ORM\Index(name: 'idx_ticket_date', columns: ['ticket_id', 'scheduled_date'])]
#[ORM\Index(name: 'idx_auto_assigned', columns: ['is_auto_assigned'])]
#[ORM\Index(name: 'idx_priority', columns: ['priority'])]
class WorkerSchedule implements WorkerScheduleInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'guid', name: 'worker_id')]
    private string $workerId;

    #[ORM\Column(type: 'guid', name: 'ticket_id')]
    private string $ticketId;

    #[ORM\Column(type: 'date_immutable', name: 'scheduled_date')]
    private \DateTimeImmutable $scheduledDate;

    #[ORM\Column(type: 'datetime_immutable', name: 'assigned_at')]
    private \DateTimeImmutable $assignedAt;

    #[ORM\Column(type: 'guid', name: 'assigned_by_id', nullable: true)]
    private ?string $assignedById;

    #[ORM\Column(type: 'boolean', name: 'is_auto_assigned', options: ['default' => false])]
    private bool $isAutoAssigned;

    #[ORM\Column(type: 'integer', name: 'priority', nullable: true)]
    private ?int $priority;

    public function __construct(
        string $id,
        string $workerId,
        string $ticketId,
        \DateTimeImmutable $scheduledDate,
        ?\DateTimeImmutable $assignedAt = null,
        ?string $assignedById = null,
        bool $isAutoAssigned = false,
        ?int $priority = null,
    ) {
        $this->id = $this->assertNonEmptyUuid($id, 'Schedule id cannot be empty.');
        $this->workerId = $this->assertNonEmptyUuid($workerId, 'Worker id cannot be empty.');
        $this->ticketId = $this->assertNonEmptyUuid($ticketId, 'Ticket id cannot be empty.');

        $this->scheduledDate = $this->normalizeDate($scheduledDate);
        $this->assertDateNotInPast($this->scheduledDate);

        $this->assignedAt = $assignedAt ?? new \DateTimeImmutable();
        $this->assignedById = $assignedById;
        $this->isAutoAssigned = $isAutoAssigned;
        $this->priority = $this->assertPriority($priority);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function getTicketId(): string
    {
        return $this->ticketId;
    }

    public function getScheduledDate(): \DateTimeImmutable
    {
        return $this->scheduledDate;
    }

    public function getAssignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function getAssignedById(): ?string
    {
        return $this->assignedById;
    }

    public function isAutoAssigned(): bool
    {
        return $this->isAutoAssigned;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function isOnDate(\DateTimeImmutable $date): bool
    {
        return $this->scheduledDate->format('Y-m-d') === $date->format('Y-m-d');
    }

    public function reassign(
        string $workerId,
        \DateTimeImmutable $scheduledDate,
        ?string $assignedById = null,
        bool $isAutoAssigned = false,
    ): void {
        $this->workerId = $this->assertNonEmptyUuid($workerId, 'Worker id cannot be empty.');
        $this->scheduledDate = $this->normalizeDate($scheduledDate);
        $this->assertDateNotInPast($this->scheduledDate);
        $this->assignedById = $assignedById;
        $this->isAutoAssigned = $isAutoAssigned;
        $this->assignedAt = new \DateTimeImmutable();
    }

    public function setPriority(?int $priority): void
    {
        $this->priority = $this->assertPriority($priority);
    }

    public function markAsAutoAssigned(): void
    {
        $this->isAutoAssigned = true;
        $this->assignedById = null;
    }

    public function markAsManual(?string $assignedById = null): void
    {
        $this->isAutoAssigned = false;
        $this->assignedById = $assignedById;
    }

    private function assertNonEmptyUuid(string $value, string $message): string
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($message);
        }

        return $normalized;
    }

    private function normalizeDate(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }

    private function assertDateNotInPast(\DateTimeImmutable $date): void
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        if ($date < $today) {
            throw new \InvalidArgumentException('Scheduled date cannot be in the past.');
        }
    }

    private function assertPriority(?int $priority): ?int
    {
        if (null === $priority) {
            return null;
        }

        if ($priority < 0) {
            throw new \InvalidArgumentException('Priority cannot be negative.');
        }

        return $priority;
    }
}
