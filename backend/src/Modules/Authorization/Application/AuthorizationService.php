<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Application;

use App\Modules\Authorization\Domain\AuthorizationRepositoryInterface;

final class AuthorizationService implements AuthorizationServiceInterface
{
    public function __construct(
        private readonly AuthorizationRepositoryInterface $repository,
    ) {
    }

    public function assignCategoriesToWorker(
        string $workerId,
        array $categoryIds,
        ?string $assignedById = null,
        ?\DateTimeImmutable $assignedAt = null,
    ): void {
        $normalizedWorkerId = $this->normalizeId($workerId, 'Worker id cannot be empty.');

        $permissions = $this->repository->loadPermissions($normalizedWorkerId);
        $permissions->synchronizeCategories(
            array_map('strval', $categoryIds),
            $this->normalizeNullableId($assignedById),
            $assignedAt,
        );

        $this->repository->savePermissions($permissions);
    }

    public function getAssignedCategoryIds(string $workerId): array
    {
        $permissions = $this->repository->loadPermissions(
            $this->normalizeId($workerId, 'Worker id cannot be empty.'),
        );

        return $permissions->getCategoryIds();
    }

    public function getWorkerPermissions(string $workerId): array
    {
        $permissions = $this->repository->loadPermissions(
            $this->normalizeId($workerId, 'Worker id cannot be empty.'),
        );

        return [
            'workerId' => $permissions->getWorkerId(),
            'categoryIds' => $permissions->getCategoryIds(),
            'isManager' => $permissions->isManager(),
        ];
    }

    public function removeCategoryFromWorker(string $workerId, string $categoryId): void
    {
        $permissions = $this->repository->loadPermissions(
            $this->normalizeId($workerId, 'Worker id cannot be empty.'),
        );

        $permissions->removeCategory($categoryId);

        $this->repository->savePermissions($permissions);
    }

    public function setManagerRole(string $workerId, bool $isManager = true): void
    {
        $permissions = $this->repository->loadPermissions(
            $this->normalizeId($workerId, 'Worker id cannot be empty.'),
        );

        $permissions->setManagerRole($isManager);

        $this->repository->savePermissions($permissions);
    }

    public function isManager(string $workerId): bool
    {
        $permissions = $this->repository->loadPermissions(
            $this->normalizeId($workerId, 'Worker id cannot be empty.'),
        );

        return $permissions->isManager();
    }

    public function canWorkerAccessCategory(string $workerId, string $categoryId): bool
    {
        $permissions = $this->repository->loadPermissions(
            $this->normalizeId($workerId, 'Worker id cannot be empty.'),
        );

        if ($permissions->isManager()) {
            return true;
        }

        $normalizedCategoryId = trim($categoryId);

        if ('' === $normalizedCategoryId) {
            return false;
        }

        return in_array($normalizedCategoryId, $permissions->getCategoryIds(), true);
    }

    private function normalizeId(string $value, string $errorMessage): string
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $normalized;
    }

    private function normalizeNullableId(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }
}
