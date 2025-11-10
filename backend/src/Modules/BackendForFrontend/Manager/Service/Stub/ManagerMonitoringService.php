<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Service\Stub;

use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentSettingsInput;
use App\Modules\BackendForFrontend\Manager\Service\ManagerMonitoringServiceInterface;
use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use DateTimeImmutable;

final class ManagerMonitoringService implements ManagerMonitoringServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function getMonitoringData(string $managerId, DateTimeImmutable $date): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function updateAutoAssignmentSettings(
        string $managerId,
        bool $enabled,
        UpdateAutoAssignmentSettingsInput $settings,
    ): array {
        return $this->notImplemented(__METHOD__);
    }

    public function triggerAutoAssignment(string $managerId, DateTimeImmutable $date): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function streamMonitoringEvents(
        string $managerId,
        DateTimeImmutable $date,
        callable $emit,
    ): void {
        $this->notImplemented(__METHOD__);
    }
}


