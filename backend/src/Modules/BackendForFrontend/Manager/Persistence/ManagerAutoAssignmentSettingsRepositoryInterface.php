<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Persistence;

use App\Modules\BackendForFrontend\Manager\Persistence\Entity\ManagerAutoAssignmentSettings;

interface ManagerAutoAssignmentSettingsRepositoryInterface
{
    public function find(string $managerId): ?ManagerAutoAssignmentSettings;

    public function save(ManagerAutoAssignmentSettings $settings): void;
}
