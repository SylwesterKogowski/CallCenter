<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Domain;

/**
 * Aggregate encapsulating worker's authorization state (category assignments and role).
 */
final class WorkerPermissions
{
    /**
     * @var array<string, WorkerCategoryAssignment>
     */
    private array $assignments = [];

    private ?WorkerRole $role;

    private function __construct(
        private readonly string $workerId,
        ?WorkerRole $role,
    ) {
        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        $this->role = $role;
    }

    /**
     * @param WorkerCategoryAssignment[] $assignments
     */
    public static function reconstitute(string $workerId, array $assignments = [], ?WorkerRole $role = null): self
    {
        $permissions = new self($workerId, $role);

        foreach ($assignments as $assignment) {
            $permissions->addAssignment($assignment);
        }

        return $permissions;
    }

    public static function createEmpty(string $workerId): self
    {
        return new self($workerId, null);
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    /**
     * @return WorkerCategoryAssignment[]
     */
    public function getAssignments(): array
    {
        return array_values($this->assignments);
    }

    /**
     * @return string[]
     */
    public function getCategoryIds(): array
    {
        return array_keys($this->assignments);
    }

    public function getRole(): ?WorkerRole
    {
        return $this->role;
    }

    public function isManager(): bool
    {
        return null !== $this->role && $this->role->isManager();
    }

    /**
     * @param string[] $categoryIds
     */
    public function synchronizeCategories(
        array $categoryIds,
        ?string $assignedById = null,
        ?\DateTimeImmutable $assignedAt = null,
    ): void {
        $normalizedIds = $this->normalizeCategoryIds($categoryIds);
        $newAssignments = [];

        foreach ($normalizedIds as $categoryId) {
            if (isset($this->assignments[$categoryId])) {
                $assignment = $this->assignments[$categoryId];
                $assignment->reactivate();
                $newAssignments[$categoryId] = $assignment;
                continue;
            }

            $newAssignments[$categoryId] = WorkerCategoryAssignment::assign(
                $this->workerId,
                $categoryId,
                $assignedById,
                $assignedAt,
            );
        }

        foreach ($this->assignments as $categoryId => $assignment) {
            if (isset($newAssignments[$categoryId])) {
                continue;
            }

            $assignment->revoke();
        }

        $this->assignments = $newAssignments;
    }

    public function assignCategory(
        string $categoryId,
        ?string $assignedById = null,
        ?\DateTimeImmutable $assignedAt = null,
    ): void {
        $normalizedId = $this->normalizeCategoryId($categoryId);

        if (null === $normalizedId) {
            return;
        }

        if (isset($this->assignments[$normalizedId])) {
            $this->assignments[$normalizedId]->reactivate();

            return;
        }

        $this->assignments[$normalizedId] = WorkerCategoryAssignment::assign(
            $this->workerId,
            $normalizedId,
            $assignedById,
            $assignedAt,
        );
    }

    public function removeCategory(string $categoryId): void
    {
        $normalizedId = $this->normalizeCategoryId($categoryId);

        if (null === $normalizedId) {
            return;
        }

        if (!isset($this->assignments[$normalizedId])) {
            return;
        }

        $this->assignments[$normalizedId]->revoke();
        unset($this->assignments[$normalizedId]);
    }

    public function setManagerRole(bool $isManager): void
    {
        if ($isManager) {
            if (null === $this->role) {
                $this->role = WorkerRole::create($this->workerId, true);

                return;
            }

            $this->role->setManager(true);

            return;
        }

        if (null === $this->role) {
            return;
        }

        $this->role->setManager(false);
    }

    private function addAssignment(WorkerCategoryAssignment $assignment): void
    {
        if ($assignment->getWorkerId() !== $this->workerId) {
            throw new \InvalidArgumentException('Assignment belongs to different worker.');
        }

        $this->assignments[$assignment->getCategoryId()] = $assignment;
    }

    /**
     * @param string[] $categoryIds
     *
     * @return string[]
     */
    private function normalizeCategoryIds(array $categoryIds): array
    {
        $normalized = [];

        foreach ($categoryIds as $categoryId) {
            $normalizedId = $this->normalizeCategoryId((string) $categoryId);

            if (null === $normalizedId) {
                continue;
            }

            $normalized[$normalizedId] = $normalizedId;
        }

        return array_values($normalized);
    }

    private function normalizeCategoryId(string $categoryId): ?string
    {
        $categoryId = trim($categoryId);

        if ('' === $categoryId) {
            return null;
        }

        return $categoryId;
    }
}
