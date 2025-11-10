<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Application;

interface AuthorizationServiceInterface
{
    /**
     * @param string[] $categoryIds
     */
    public function assignCategoriesToWorker(
        string $workerId,
        array $categoryIds,
        ?string $assignedById = null,
        ?\DateTimeImmutable $assignedAt = null,
    ): void;

    /**
     * @return string[]
     */
    public function getAssignedCategoryIds(string $workerId): array;

    /**
     * @return array{
     *     workerId: string,
     *     categoryIds: string[],
     *     isManager: bool
     * }
     */
    public function getWorkerPermissions(string $workerId): array;

    public function removeCategoryFromWorker(string $workerId, string $categoryId): void;

    public function setManagerRole(string $workerId, bool $isManager = true): void;

    public function isManager(string $workerId): bool;

    public function canWorkerAccessCategory(string $workerId, string $categoryId): bool;
}
