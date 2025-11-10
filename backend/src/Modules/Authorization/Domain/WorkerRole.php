<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Domain;

use Symfony\Component\Uid\Uuid;

final class WorkerRole
{
    private function __construct(
        private string $id,
        private string $workerId,
        private bool $isManager,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('Role id cannot be empty.');
        }

        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }
    }

    public static function create(string $workerId, bool $isManager = false): self
    {
        return new self(
            Uuid::v7()->toRfc4122(),
            $workerId,
            $isManager,
            null,
        );
    }

    public static function reconstitute(
        string $id,
        string $workerId,
        bool $isManager,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $workerId, $isManager, $updatedAt);
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

    public function promoteToManager(): void
    {
        if ($this->isManager) {
            return;
        }

        $this->isManager = true;
        $this->touch();
    }

    public function demoteFromManager(): void
    {
        if (!$this->isManager) {
            return;
        }

        $this->isManager = false;
        $this->touch();
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
