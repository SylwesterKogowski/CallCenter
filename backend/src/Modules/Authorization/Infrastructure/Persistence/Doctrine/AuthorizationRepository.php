<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Infrastructure\Persistence\Doctrine;

use App\Modules\Authorization\Domain\AuthorizationRepositoryInterface;
use App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity\WorkerCategoryAssignment;
use App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity\WorkerRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Uid\Uuid;

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

    public function assignCategoriesToWorker(string $workerId, array $categoryIds, ?string $assignedById = null): void
    {
        $normalizedIds = $this->normalizeCategoryIds($categoryIds);

        /** @var WorkerCategoryAssignment[] $existingAssignments */
        $existingAssignments = $this->categoryAssignmentRepository->findBy(['workerId' => $workerId]);

        $existingByCategoryId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByCategoryId[$assignment->getCategoryId()] = $assignment;
        }

        foreach ($normalizedIds as $categoryId) {
            if (isset($existingByCategoryId[$categoryId])) {
                unset($existingByCategoryId[$categoryId]);
                continue;
            }

            $assignment = new WorkerCategoryAssignment(
                $workerId,
                $categoryId,
                new \DateTimeImmutable(),
                $assignedById,
            );

            $this->entityManager->persist($assignment);
        }

        foreach ($existingByCategoryId as $assignment) {
            $this->entityManager->remove($assignment);
        }

        $this->entityManager->flush();
    }

    public function getAssignedCategoryIds(string $workerId): array
    {
        /** @var WorkerCategoryAssignment[] $assignments */
        $assignments = $this->categoryAssignmentRepository->findBy(['workerId' => $workerId]);

        $categoryIds = [];
        foreach ($assignments as $assignment) {
            $categoryIds[] = $assignment->getCategoryId();
        }

        return $categoryIds;
    }

    public function setManagerRole(string $workerId, bool $isManager = true): void
    {
        $role = $this->workerRoleRepository->findOneBy(['workerId' => $workerId]);

        if (!$role instanceof WorkerRole) {
            if (!$isManager) {
                return;
            }

            $role = new WorkerRole(Uuid::v7()->toRfc4122(), $workerId, true);
            $this->entityManager->persist($role);
            $this->entityManager->flush();

            return;
        }

        $role->setIsManager($isManager);

        $this->entityManager->flush();
    }

    public function isManager(string $workerId): bool
    {
        $role = $this->workerRoleRepository->findOneBy(['workerId' => $workerId]);

        if (!$role instanceof WorkerRole) {
            return false;
        }

        return $role->isManager();
    }

    /**
     * @param string[] $categoryIds
     *
     * @return string[]
     */
    private function normalizeCategoryIds(array $categoryIds): array
    {
        $filtered = array_filter(
            array_map('strval', $categoryIds),
            static fn (string $value): bool => '' !== trim($value),
        );

        return array_values(array_unique($filtered));
    }
}
