<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Application\Stub;

use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;

final class AuthorizationService implements AuthorizationServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function assignCategoriesToWorker(string $workerId, array $categoryIds): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function getAssignedCategoryIds(string $workerId): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function setManagerRole(string $workerId): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function isManager(string $workerId): bool
    {
        return $this->notImplemented(__METHOD__);
    }
}
