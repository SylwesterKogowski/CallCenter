<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Domain;

final class WorkerCategoryAssignment
{
    private bool $revoked;

    private ?\DateTimeImmutable $revokedAt;

    private function __construct(
        private readonly string $workerId,
        private readonly string $categoryId,
        private \DateTimeImmutable $assignedAt,
        private ?string $assignedById,
        bool $revoked,
        ?\DateTimeImmutable $revokedAt,
    ) {
        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        if ('' === $categoryId) {
            throw new \InvalidArgumentException('Category id cannot be empty.');
        }

        $this->revoked = $revoked;
        $this->revokedAt = $revokedAt;
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
            false,
            null,
        );
    }

    public static function reconstitute(
        string $workerId,
        string $categoryId,
        \DateTimeImmutable $assignedAt,
        ?string $assignedById = null,
        bool $revoked = false,
        ?\DateTimeImmutable $revokedAt = null,
    ): self {
        return new self($workerId, $categoryId, $assignedAt, $assignedById, $revoked, $revokedAt);
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

    public function reactivate(): void
    {
        $this->revoked = false;
        $this->revokedAt = null;
    }

    public function withAssignedAt(\DateTimeImmutable $assignedAt): void
    {
        $this->assignedAt = $assignedAt;
    }

    public function withAssignedById(?string $assignedById): void
    {
        $this->assignedById = $assignedById;
    }
}
