<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'worker_category_assignments')]
#[ORM\Index(name: 'idx_worker_id', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_category_id', columns: ['category_id'])]
class WorkerCategoryAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', name: 'worker_id')]
    private string $workerId;

    #[ORM\Id]
    #[ORM\Column(type: 'guid', name: 'category_id')]
    private string $categoryId;

    #[ORM\Column(type: 'datetime_immutable', name: 'assigned_at')]
    private \DateTimeImmutable $assignedAt;

    #[ORM\Column(type: 'guid', name: 'assigned_by_id', nullable: true)]
    private ?string $assignedById;

    public function __construct(
        string $workerId,
        string $categoryId,
        ?\DateTimeImmutable $assignedAt = null,
        ?string $assignedById = null,
    ) {
        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        if ('' === $categoryId) {
            throw new \InvalidArgumentException('Category id cannot be empty.');
        }

        $this->workerId = $workerId;
        $this->categoryId = $categoryId;
        $this->assignedAt = $assignedAt ?? new \DateTimeImmutable();
        $this->assignedById = $assignedById;
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function getAssignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(\DateTimeImmutable $assignedAt): void
    {
        $this->assignedAt = $assignedAt;
    }

    public function getAssignedById(): ?string
    {
        return $this->assignedById;
    }

    public function setAssignedById(?string $assignedById): void
    {
        $this->assignedById = $assignedById;
    }
}
