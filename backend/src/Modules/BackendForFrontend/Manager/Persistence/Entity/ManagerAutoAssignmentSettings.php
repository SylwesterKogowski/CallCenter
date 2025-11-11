<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Persistence\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'manager_auto_assignment_settings')]
class ManagerAutoAssignmentSettings
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', name: 'manager_id')]
    private string $managerId;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;

    #[ORM\Column(type: 'boolean', name: 'consider_efficiency')]
    private bool $considerEfficiency = true;

    #[ORM\Column(type: 'boolean', name: 'consider_availability')]
    private bool $considerAvailability = true;

    #[ORM\Column(type: 'integer', name: 'max_tickets_per_worker')]
    private int $maxTicketsPerWorker = 10;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'last_run', nullable: true)]
    private ?\DateTimeImmutable $lastRun = null;

    #[ORM\Column(type: 'integer', name: 'tickets_assigned')]
    private int $ticketsAssigned = 0;

    public function __construct(string $managerId)
    {
        $normalized = trim($managerId);

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Manager id cannot be empty.');
        }

        $this->managerId = $normalized;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getManagerId(): string
    {
        return $this->managerId;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->touch();
    }

    public function shouldConsiderEfficiency(): bool
    {
        return $this->considerEfficiency;
    }

    public function setConsiderEfficiency(bool $considerEfficiency): void
    {
        $this->considerEfficiency = $considerEfficiency;
        $this->touch();
    }

    public function shouldConsiderAvailability(): bool
    {
        return $this->considerAvailability;
    }

    public function setConsiderAvailability(bool $considerAvailability): void
    {
        $this->considerAvailability = $considerAvailability;
        $this->touch();
    }

    public function getMaxTicketsPerWorker(): int
    {
        return $this->maxTicketsPerWorker;
    }

    public function setMaxTicketsPerWorker(int $maxTicketsPerWorker): void
    {
        if ($maxTicketsPerWorker <= 0) {
            throw new \InvalidArgumentException('Max tickets per worker must be greater than zero.');
        }

        $this->maxTicketsPerWorker = $maxTicketsPerWorker;
        $this->touch();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastRun(): ?\DateTimeImmutable
    {
        return $this->lastRun;
    }

    public function setLastRun(?\DateTimeImmutable $lastRun, int $ticketsAssigned): void
    {
        $this->lastRun = $lastRun;
        $this->ticketsAssigned = max(0, $ticketsAssigned);
        $this->touch();
    }

    public function getTicketsAssigned(): int
    {
        return $this->ticketsAssigned;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     lastRun: \DateTimeImmutable|null,
     *     ticketsAssigned: int,
     *     settings: array{
     *         considerEfficiency: bool,
     *         considerAvailability: bool,
     *         maxTicketsPerWorker: int
     *     },
     *     updatedAt: \DateTimeImmutable
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'lastRun' => $this->lastRun,
            'ticketsAssigned' => $this->ticketsAssigned,
            'settings' => [
                'considerEfficiency' => $this->considerEfficiency,
                'considerAvailability' => $this->considerAvailability,
                'maxTicketsPerWorker' => $this->maxTicketsPerWorker,
            ],
            'updatedAt' => $this->updatedAt,
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
