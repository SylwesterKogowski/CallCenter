<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Infrastructure\Persistence\Doctrine;

use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityRepositoryInterface;
use App\Modules\WorkerAvailability\Infrastructure\Persistence\Doctrine\Entity\WorkerAvailability;
use Doctrine\ORM\EntityManagerInterface;

final class WorkerAvailabilityRepository implements WorkerAvailabilityRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(string $id): ?WorkerAvailabilityInterface
    {
        $availability = $this->entityManager->find(WorkerAvailability::class, $id);

        if (!$availability instanceof WorkerAvailability) {
            return null;
        }

        return $availability;
    }

    public function findForDate(string $workerId, \DateTimeImmutable $date): iterable
    {
        $rangeStart = $this->createDayStart($date);
        $rangeEnd = $rangeStart->modify('+1 day');

        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('availability')
            ->from(WorkerAvailability::class, 'availability')
            ->where('availability.workerId = :workerId')
            ->andWhere('availability.startDatetime >= :start')
            ->andWhere('availability.startDatetime < :end')
            ->setParameter('workerId', $workerId)
            ->setParameter('start', $rangeStart)
            ->setParameter('end', $rangeEnd)
            ->orderBy('availability.startDatetime', 'ASC');

        /** @var WorkerAvailabilityInterface[] $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    public function findForPeriod(
        string $workerId,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
    ): iterable {
        $normalizedStart = $this->createDayStart($rangeStart);
        $normalizedEnd = $this->createDayStart($rangeEnd)->modify('+1 day');

        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('availability')
            ->from(WorkerAvailability::class, 'availability')
            ->where('availability.workerId = :workerId')
            ->andWhere('availability.startDatetime < :end')
            ->andWhere('availability.endDatetime >= :start')
            ->setParameter('workerId', $workerId)
            ->setParameter('start', $normalizedStart)
            ->setParameter('end', $normalizedEnd)
            ->orderBy('availability.startDatetime', 'ASC');

        /** @var WorkerAvailabilityInterface[] $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    public function save(WorkerAvailabilityInterface $availability): void
    {
        $entity = $this->assertEntity($availability);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function remove(WorkerAvailabilityInterface $availability): void
    {
        $entity = $this->assertEntity($availability);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function removeAllForDate(string $workerId, \DateTimeImmutable $date): void
    {
        $slots = $this->findForDate($workerId, $date);

        foreach ($slots as $slot) {
            $entity = $this->assertEntity($slot);
            $this->entityManager->remove($entity);
        }

        $this->entityManager->flush();
    }

    private function assertEntity(WorkerAvailabilityInterface $availability): WorkerAvailability
    {
        if (!$availability instanceof WorkerAvailability) {
            throw new \InvalidArgumentException(sprintf('Expected instance of %s, got %s instead.', WorkerAvailability::class, $availability::class));
        }

        return $availability;
    }

    private function createDayStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }
}
