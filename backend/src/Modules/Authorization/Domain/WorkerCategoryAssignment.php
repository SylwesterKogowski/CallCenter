<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Domain;

final class WorkerCategoryAssignment
{
    private bool $revoked = false;

    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        private string $workerId,
        private string $categoryId,
        private \DateTimeImmutable $assignedAt,
        private ?string $assignedById = null,
    ) {
        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        if ('' === $categoryId) {
            throw new \InvalidArgumentException('Category id cannot be empty.');
        }
    }

    public static function assign(
        string $workerId,
        string $categoryId,
        ?string $assignedById = null,
        ?\DateTimeImmutable $assignedAt = null,
    ): self {
        return new self(
            $workerId,
            $categoryId,
            $assignedAt ?? new \DateTimeImmutable(),
            $assignedById,
        );
    }

    public static function reconstitute(
        string $workerId,
        string $categoryId,
        \DateTimeImmutable $assignedAt,
        ?string $assignedById = null,
    ): self {
        return new self($workerId, $categoryId, $assignedAt, $assignedById);
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

    public function getAssignedById(): ?string
    {
        return $this->assignedById;
    }

    public function revoke(): void
    {
        if ($this->revoked) {
            return;
        }

        $this->revoked = true;
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
