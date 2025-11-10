<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Domain;

interface AuthorizationRepositoryInterface
{
    /**
     * @param string[] $categoryIds
     */
    public function assignCategoriesToWorker(string $workerId, array $categoryIds, ?string $assignedById = null): void;

    /**
     * @return string[]
     */
    public function getAssignedCategoryIds(string $workerId): array;

    public function setManagerRole(string $workerId, bool $isManager = true): void;

    public function isManager(string $workerId): bool;
}
