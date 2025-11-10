<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Application\Stub;

use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;

final class AuthorizationService implements AuthorizationServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function assignCategoriesToWorker(
        string $workerId,
        array $categoryIds,
        ?string $assignedById = null,
        ?\DateTimeImmutable $assignedAt = null,
    ): void {
        $this->notImplemented(__METHOD__);
    }

    public function getAssignedCategoryIds(string $workerId): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getWorkerPermissions(string $workerId): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function removeCategoryFromWorker(string $workerId, string $categoryId): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function setManagerRole(string $workerId, bool $isManager = true): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function isManager(string $workerId): bool
    {
        return $this->notImplemented(__METHOD__);
    }

    public function canWorkerAccessCategory(string $workerId, string $categoryId): bool
    {
        return $this->notImplemented(__METHOD__);
    }
}
