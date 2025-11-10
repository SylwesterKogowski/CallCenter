<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Interface\Persistence\Doctrine;

use App\Modules\Authentication\Domain\Worker;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authentication\Domain\WorkerRepositoryInterface;
use App\Modules\Authentication\Interface\Persistence\Doctrine\Entity\Worker as WorkerEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

/**
 * Tests at are written in {@see \Tests\Integration\Modules\Authentication\Infrastructure\Persistence\WorkerRepositoryTest}.
 */
final class WorkerRepository implements WorkerRepositoryInterface
{
    /** @var ObjectRepository<WorkerEntity> */
    private ObjectRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(WorkerEntity::class);
    }

    public function findById(string $id): ?WorkerInterface
    {
        $worker = $this->repository->find($id);

        if (!$worker instanceof WorkerEntity) {
            return null;
        }

        return $this->mapEntityToDomain($worker);
    }

    public function findByLogin(string $login): ?WorkerInterface
    {
        $worker = $this->repository->findOneBy(['login' => $login]);

        if (!$worker instanceof WorkerEntity) {
            return null;
        }

        return $this->mapEntityToDomain($worker);
    }

    public function save(WorkerInterface $worker): void
    {
        $entity = $this->createEntityFromDomain($worker);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(WorkerInterface $worker): void
    {
        $entity = $this->entityManager->find(WorkerEntity::class, $worker->getId());

        if (!$entity instanceof WorkerEntity) {
            throw new \InvalidArgumentException(sprintf('Cannot update worker with id "%s" because it does not exist.', $worker->getId()));
        }

        $this->applyDomainState($worker, $entity);

        $this->entityManager->flush();
    }

    private function createEntityFromDomain(WorkerInterface $worker): WorkerEntity
    {
        if ($worker instanceof WorkerEntity) {
            return $worker;
        }

        $entity = new WorkerEntity(
            $worker->getId(),
            $worker->getLogin(),
            $worker->getPasswordHash(),
            $worker->isManager(),
            $worker->getCreatedAt(),
        );
        $entity->setUpdatedAt($worker->getUpdatedAt());

        return $entity;
    }

    private function applyDomainState(WorkerInterface $worker, WorkerEntity $entity): void
    {
        $entity->setLogin($worker->getLogin());
        $entity->setPasswordHash($worker->getPasswordHash());
        $entity->setIsManager($worker->isManager());
        $entity->setCreatedAt($worker->getCreatedAt());
        $entity->setUpdatedAt($worker->getUpdatedAt());
    }

    private function mapEntityToDomain(WorkerEntity $entity): WorkerInterface
    {
        return Worker::reconstitute(
            $entity->getId(),
            $entity->getLogin(),
            $entity->getPasswordHash(),
            $entity->isManager(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
        );
    }
}
