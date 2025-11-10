<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Interface\Persistence\Doctrine;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authentication\Domain\WorkerRepositoryInterface;
use App\Modules\Authentication\Interface\Persistence\Doctrine\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;

final class WorkerRepository implements WorkerRepositoryInterface
{
    private ObjectRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(Worker::class);
    }

    public function findById(string $id): ?WorkerInterface
    {
        /** @var WorkerInterface|null $worker */
        $worker = $this->repository->find($id);

        return $worker;
    }

    public function findByLogin(string $login): ?WorkerInterface
    {
        /** @var WorkerInterface|null $worker */
        $worker = $this->repository->findOneBy(['login' => $login]);

        return $worker;
    }

    public function save(WorkerInterface $worker): void
    {
        $entity = $this->assertSupportedWorker($worker);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(WorkerInterface $worker): void
    {
        $this->assertSupportedWorker($worker);

        $this->entityManager->flush();
    }

    private function assertSupportedWorker(WorkerInterface $worker): Worker
    {
        if (!$worker instanceof Worker) {
            throw new InvalidArgumentException(sprintf(
                'WorkerRepository supports instances of %s, %s given.',
                Worker::class,
                $worker::class,
            ));
        }

        return $worker;
    }
}

