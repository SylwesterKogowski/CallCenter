<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Infrastructure\Persistence\Doctrine;

use App\Modules\Authorization\Domain\AuthorizationRepositoryInterface;
use App\Modules\Authorization\Domain\WorkerCategoryAssignment as DomainWorkerCategoryAssignment;
use App\Modules\Authorization\Domain\WorkerPermissions;
use App\Modules\Authorization\Domain\WorkerRole as DomainWorkerRole;
use App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity\WorkerCategoryAssignment;
use App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity\WorkerRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

/**
 * Persists worker authorization data (category assignments and roles) using Doctrine.
 */
final class AuthorizationRepository implements AuthorizationRepositoryInterface
{
    /** @var ObjectRepository<WorkerCategoryAssignment> */
    private ObjectRepository $categoryAssignmentRepository;

    /** @var ObjectRepository<WorkerRole> */
    private ObjectRepository $workerRoleRepository;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->categoryAssignmentRepository = $this->entityManager->getRepository(WorkerCategoryAssignment::class);
        $this->workerRoleRepository = $this->entityManager->getRepository(WorkerRole::class);
    }

    public function loadPermissions(string $workerId): WorkerPermissions
    {
        /** @var WorkerCategoryAssignment[] $assignmentEntities */
        $assignmentEntities = $this->categoryAssignmentRepository->findBy(['workerId' => $workerId]);

        $assignments = array_map(
            fn (WorkerCategoryAssignment $entity): DomainWorkerCategoryAssignment => $this->mapToDomainAssignment($entity),
            $assignmentEntities,
        );

        $roleEntity = $this->workerRoleRepository->findOneBy(['workerId' => $workerId]);
        $role = $roleEntity instanceof WorkerRole ? $this->mapToDomainRole($roleEntity) : null;

        return WorkerPermissions::reconstitute($workerId, $assignments, $role);
    }

    public function savePermissions(WorkerPermissions $permissions): void
    {
        $this->synchronizeAssignments($permissions);
        $this->synchronizeRole($permissions);
        $this->entityManager->flush();
    }

    private function mapToDomainAssignment(WorkerCategoryAssignment $entity): DomainWorkerCategoryAssignment
    {
        return DomainWorkerCategoryAssignment::reconstitute(
            $entity->getWorkerId(),
            $entity->getCategoryId(),
            $entity->getAssignedAt(),
            $entity->getAssignedById(),
        );
    }

    private function mapToDomainRole(WorkerRole $entity): DomainWorkerRole
    {
        $updatedAt = $entity->getUpdatedAt();
        $updatedAtImmutable = null;

        if ($updatedAt instanceof \DateTimeImmutable) {
            $updatedAtImmutable = $updatedAt;
        } elseif ($updatedAt instanceof \DateTimeInterface) {
            $updatedAtImmutable = \DateTimeImmutable::createFromInterface($updatedAt);
        }

        return DomainWorkerRole::reconstitute(
            $entity->getId(),
            $entity->getWorkerId(),
            $entity->isManager(),
            $updatedAtImmutable,
        );
    }

    private function synchronizeAssignments(WorkerPermissions $permissions): void
    {
        $workerId = $permissions->getWorkerId();

        /** @var WorkerCategoryAssignment[] $existingEntities */
        $existingEntities = $this->categoryAssignmentRepository->findBy(['workerId' => $workerId]);
        $existingByCategory = [];

        foreach ($existingEntities as $entity) {
            $existingByCategory[$entity->getCategoryId()] = $entity;
        }

        foreach ($permissions->getAssignments() as $assignment) {
            $categoryId = $assignment->getCategoryId();

            if (isset($existingByCategory[$categoryId])) {
                $entity = $existingByCategory[$categoryId];
                $entity->setAssignedAt($assignment->getAssignedAt());
                $entity->setAssignedById($assignment->getAssignedById());
                unset($existingByCategory[$categoryId]);
                continue;
            }

            $entity = new WorkerCategoryAssignment(
                $assignment->getWorkerId(),
                $categoryId,
                $assignment->getAssignedAt(),
                $assignment->getAssignedById(),
            );

            $this->entityManager->persist($entity);
        }

        foreach ($existingByCategory as $entity) {
            $this->entityManager->remove($entity);
        }
    }

    private function synchronizeRole(WorkerPermissions $permissions): void
    {
        $workerId = $permissions->getWorkerId();
        $domainRole = $permissions->getRole();

        $roleEntity = $this->workerRoleRepository->findOneBy(['workerId' => $workerId]);

        if (null === $domainRole) {
            if ($roleEntity instanceof WorkerRole) {
                $this->entityManager->remove($roleEntity);
            }

            return;
        }

        if (!$roleEntity instanceof WorkerRole) {
            $roleEntity = new WorkerRole(
                $domainRole->getId(),
                $domainRole->getWorkerId(),
                $domainRole->isManager(),
            );

            if (null !== $domainRole->getUpdatedAt()) {
                $roleEntity->setUpdatedAt($domainRole->getUpdatedAt());
            }

            $this->entityManager->persist($roleEntity);

            return;
        }

        $roleEntity->setIsManager($domainRole->isManager());
        $roleEntity->setUpdatedAt($domainRole->getUpdatedAt());
    }
}
