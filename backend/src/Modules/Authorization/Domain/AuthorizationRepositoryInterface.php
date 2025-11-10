<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Domain;

interface AuthorizationRepositoryInterface
{
    public function loadPermissions(string $workerId): WorkerPermissions;

    public function savePermissions(WorkerPermissions $permissions): void;
}
