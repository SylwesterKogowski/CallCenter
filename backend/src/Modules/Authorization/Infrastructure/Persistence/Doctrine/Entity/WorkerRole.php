<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'worker_roles')]
#[ORM\UniqueConstraint(name: 'unique_worker_role', columns: ['worker_id'])]
class WorkerRole
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'guid', name: 'worker_id', unique: true)]
    private string $workerId;

    #[ORM\Column(type: 'boolean', name: 'is_manager')]
    private bool $isManager;

    #[ORM\Column(type: 'datetime', name: 'updated_at', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct(string $id, string $workerId, bool $isManager = false)
    {
        if ('' === $id) {
            throw new \InvalidArgumentException('Role id cannot be empty.');
        }

        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        $this->id = $id;
        $this->workerId = $workerId;
        $this->isManager = $isManager;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function isManager(): bool
    {
        return $this->isManager;
    }

    public function setIsManager(bool $isManager): void
    {
        if ($isManager === $this->isManager) {
            return;
        }

        $this->isManager = $isManager;
        $this->touch();
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
