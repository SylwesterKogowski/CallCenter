<?php

declare(strict_types=1);

namespace App\Modules\WorkerSchedule\Infrastructure\Persistence\Doctrine;

use App\Modules\WorkerSchedule\Domain\WorkerScheduleInterface;
use App\Modules\WorkerSchedule\Domain\WorkerScheduleRepositoryInterface;
use App\Modules\WorkerSchedule\Infrastructure\Persistence\Doctrine\Entity\WorkerSchedule;
use Doctrine\ORM\EntityManagerInterface;

final class WorkerScheduleRepository implements WorkerScheduleRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(string $id): ?WorkerScheduleInterface
    {
        $schedule = $this->entityManager->find(WorkerSchedule::class, $id);

        if (!$schedule instanceof WorkerSchedule) {
            return null;
        }

        return $schedule;
    }

    public function findByWorkerAndDate(string $workerId, \DateTimeImmutable $date): iterable
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('schedule')
            ->from(WorkerSchedule::class, 'schedule')
            ->where('schedule.workerId = :workerId')
            ->andWhere('schedule.scheduledDate = :date')
            ->setParameter('workerId', $workerId)
            ->setParameter('date', $this->normalizeDate($date))
            ->orderBy('schedule.priority', 'DESC')
            ->addOrderBy('schedule.assignedAt', 'ASC');

        /** @var WorkerScheduleInterface[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByWorkerAndPeriod(
        string $workerId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): iterable {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('schedule')
            ->from(WorkerSchedule::class, 'schedule')
            ->where('schedule.workerId = :workerId')
            ->andWhere('schedule.scheduledDate >= :start')
            ->andWhere('schedule.scheduledDate <= :end')
            ->setParameter('workerId', $workerId)
            ->setParameter('start', $this->normalizeDate($startDate))
            ->setParameter('end', $this->normalizeDate($endDate))
            ->orderBy('schedule.scheduledDate', 'ASC')
            ->addOrderBy('schedule.priority', 'DESC')
            ->addOrderBy('schedule.assignedAt', 'ASC');

        /** @var WorkerScheduleInterface[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByTicketAndDate(string $ticketId, \DateTimeImmutable $date): iterable
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('schedule')
            ->from(WorkerSchedule::class, 'schedule')
            ->where('schedule.ticketId = :ticketId')
            ->andWhere('schedule.scheduledDate = :date')
            ->setParameter('ticketId', $ticketId)
            ->setParameter('date', $this->normalizeDate($date))
            ->orderBy('schedule.assignedAt', 'ASC');

        /** @var WorkerScheduleInterface[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findOneByWorkerTicketAndDate(
        string $ticketId,
        string $workerId,
        \DateTimeImmutable $date,
    ): ?WorkerScheduleInterface {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('schedule')
            ->from(WorkerSchedule::class, 'schedule')
            ->where('schedule.ticketId = :ticketId')
            ->andWhere('schedule.workerId = :workerId')
            ->andWhere('schedule.scheduledDate = :date')
            ->setParameter('ticketId', $ticketId)
            ->setParameter('workerId', $workerId)
            ->setParameter('date', $this->normalizeDate($date))
            ->setMaxResults(1);

        $schedule = $qb->getQuery()->getOneOrNullResult();

        if (!$schedule instanceof WorkerSchedule) {
            return null;
        }

        return $schedule;
    }

    public function save(WorkerScheduleInterface $assignment): void
    {
        $entity = $this->assertEntity($assignment);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function remove(WorkerScheduleInterface $assignment): void
    {
        $entity = $this->assertEntity($assignment);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    private function assertEntity(WorkerScheduleInterface $assignment): WorkerSchedule
    {
        if (!$assignment instanceof WorkerSchedule) {
            throw new \InvalidArgumentException(sprintf('Expected instance of %s, got %s instead.', WorkerSchedule::class, $assignment::class));
        }

        return $assignment;
    }

    private function normalizeDate(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }
}
