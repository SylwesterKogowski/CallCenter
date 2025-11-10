<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Application;

interface AuthorizationServiceInterface
{
    /**
     * @param string[] $categoryIds
     */
    public function assignCategoriesToWorker(string $workerId, array $categoryIds): void;

    /**
     * @return string[]
     */
    public function getAssignedCategoryIds(string $workerId): array;

    public function setManagerRole(string $workerId): void;

    public function isManager(string $workerId): bool;
}

