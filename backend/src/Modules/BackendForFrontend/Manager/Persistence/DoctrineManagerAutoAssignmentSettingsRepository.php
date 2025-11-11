<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Persistence;

use App\Modules\BackendForFrontend\Manager\Persistence\Entity\ManagerAutoAssignmentSettings;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineManagerAutoAssignmentSettingsRepository implements ManagerAutoAssignmentSettingsRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(string $managerId): ?ManagerAutoAssignmentSettings
    {
        return $this->entityManager->find(ManagerAutoAssignmentSettings::class, $managerId);
    }

    public function save(ManagerAutoAssignmentSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
