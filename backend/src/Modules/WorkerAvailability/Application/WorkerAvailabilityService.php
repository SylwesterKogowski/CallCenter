<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Application;

use App\Modules\WorkerAvailability\Application\Dto\CopyAvailabilityResult;
use App\Modules\WorkerAvailability\Application\Dto\CopyAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Application\Dto\DayAvailabilityResult;
use App\Modules\WorkerAvailability\Application\Dto\DayAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityRepositoryInterface;
use App\Modules\WorkerAvailability\Infrastructure\Persistence\Doctrine\Entity\WorkerAvailability as WorkerAvailabilityEntity;
use Symfony\Component\Uid\Uuid;

/**
 * Testy w {@see \Tests\Unit\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceTest}.
 */
final class WorkerAvailabilityService implements WorkerAvailabilityServiceInterface
{
    /**
     * @var callable():string
     */
    private $uuidFactory;

    /**
     * @var callable():\DateTimeImmutable
     */
    private $nowFactory;

    public function __construct(
        private readonly WorkerAvailabilityRepositoryInterface $repository,
        ?callable $uuidFactory = null,
        ?callable $nowFactory = null,
    ) {
        $this->uuidFactory = $uuidFactory ?? static fn (): string => Uuid::v7()->toRfc4122();
        $this->nowFactory = $nowFactory ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable();
    }

    public function getWorkerAvailabilityForWeek(string $workerId, \DateTimeImmutable $weekStartDate): iterable
    {
        $normalizedWorkerId = $this->normalizeWorkerId($workerId);
        $startDate = $this->normalizeDate($weekStartDate);
        $endDate = $startDate->modify('+6 days');

        return $this->repository->findForPeriod($normalizedWorkerId, $startDate, $endDate);
    }

    public function replaceWorkerAvailabilityForDay(
        string $workerId,
        \DateTimeImmutable $date,
        iterable $timeSlots,
    ): DayAvailabilityResultInterface {
        $normalizedWorkerId = $this->normalizeWorkerId($workerId);
        $normalizedDate = $this->normalizeDate($date);
        $normalizedSlots = $this->normalizeTimeSlots($normalizedDate, $timeSlots);

        $this->repository->removeAllForDate($normalizedWorkerId, $normalizedDate);

        $savedSlots = [];

        foreach ($normalizedSlots as $slot) {
            $availability = $this->createAvailability(
                $normalizedWorkerId,
                $slot['start'],
                $slot['end'],
            );

            $this->repository->save($availability);
            $savedSlots[] = $availability;
        }

        return new DayAvailabilityResult($normalizedDate, $savedSlots, $this->now());
    }

    public function updateWorkerAvailabilitySlot(
        string $workerId,
        string $timeSlotId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): WorkerAvailabilityInterface {
        $normalizedWorkerId = $this->normalizeWorkerId($workerId);
        $normalizedSlotId = $this->normalizeId($timeSlotId, 'Time slot id cannot be empty.');
        $availability = $this->repository->findById($normalizedSlotId);

        if (!$availability instanceof WorkerAvailabilityInterface) {
            throw new \RuntimeException(sprintf('Worker availability "%s" not found.', $normalizedSlotId));
        }

        if ($availability->getWorkerId() !== $normalizedWorkerId) {
            throw new \InvalidArgumentException('Cannot update availability that belongs to another worker.');
        }

        $normalizedStart = $this->ensureSameDay($availability->getStartDatetime(), $start, 'start');
        $normalizedEnd = $this->ensureSameDay($availability->getEndDatetime(), $end, 'end');

        $this->assertFutureOrToday($normalizedStart);
        $this->assertValidRange($normalizedStart, $normalizedEnd);

        $availability->updateAvailability($normalizedStart, $normalizedEnd);
        $this->repository->save($availability);

        return $availability;
    }

    public function removeWorkerAvailabilitySlot(string $workerId, string $timeSlotId): \DateTimeImmutable
    {
        $normalizedWorkerId = $this->normalizeWorkerId($workerId);
        $normalizedSlotId = $this->normalizeId($timeSlotId, 'Time slot id cannot be empty.');
        $availability = $this->repository->findById($normalizedSlotId);

        if (!$availability instanceof WorkerAvailabilityInterface) {
            throw new \RuntimeException(sprintf('Worker availability "%s" not found.', $normalizedSlotId));
        }

        if ($availability->getWorkerId() !== $normalizedWorkerId) {
            throw new \InvalidArgumentException('Cannot remove availability that belongs to another worker.');
        }

        $date = $availability->getDate();

        $this->repository->remove($availability);

        return $date;
    }

    public function copyWorkerAvailability(
        string $workerId,
        \DateTimeImmutable $sourceDate,
        array $targetDates,
        bool $overwrite,
    ): CopyAvailabilityResultInterface {
        $normalizedWorkerId = $this->normalizeWorkerId($workerId);
        $normalizedSourceDate = $this->normalizeDate($sourceDate);

        $sourceSlots = [...$this->repository->findForDate($normalizedWorkerId, $normalizedSourceDate)];

        $normalizedTargetDates = $this->normalizeTargetDates($targetDates);

        $copied = [];
        $skipped = [];

        foreach ($normalizedTargetDates as $targetDate) {
            if ($targetDate->format('Y-m-d') === $normalizedSourceDate->format('Y-m-d')) {
                $skipped[] = $targetDate;
                continue;
            }

            if ($targetDate < $this->today()) {
                $skipped[] = $targetDate;
                continue;
            }

            $existingSlots = [...$this->repository->findForDate($normalizedWorkerId, $targetDate)];

            if (!$overwrite && [] !== $existingSlots) {
                $skipped[] = $targetDate;
                continue;
            }

            if ($overwrite && [] !== $existingSlots) {
                $this->repository->removeAllForDate($normalizedWorkerId, $targetDate);
            }

            $newSlots = [];

            foreach ($sourceSlots as $slot) {
                $start = $this->withDate($slot->getStartDatetime(), $targetDate);
                $end = $this->withDate($slot->getEndDatetime(), $targetDate);

                $availability = $this->createAvailability(
                    $normalizedWorkerId,
                    $start,
                    $end,
                );

                $this->repository->save($availability);
                $newSlots[] = $availability;
            }

            $copied[] = new DayAvailabilityResult($targetDate, $newSlots, $this->now());
        }

        return new CopyAvailabilityResult($copied, $skipped);
    }

    public function getAvailableTimeForDate(string $workerId, \DateTimeImmutable $date): int
    {
        $normalizedWorkerId = $this->normalizeWorkerId($workerId);
        $normalizedDate = $this->normalizeDate($date);

        $availabilities = $this->repository->findForDate($normalizedWorkerId, $normalizedDate);
        $totalMinutes = 0;

        foreach ($availabilities as $availability) {
            $totalMinutes += $availability->getDurationMinutes();
        }

        return $totalMinutes;
    }

    /**
     * @param iterable<mixed> $timeSlots
     *
     * @return array<int, array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function normalizeTimeSlots(
        \DateTimeImmutable $date,
        iterable $timeSlots,
    ): array {
        $normalized = [];

        foreach ($timeSlots as $index => $slot) {
            if (!\is_array($slot) || !isset($slot['start'], $slot['end'])) {
                throw new \InvalidArgumentException(sprintf('Invalid time slot at index %s.', (string) $index));
            }

            if (!$slot['start'] instanceof \DateTimeImmutable || !$slot['end'] instanceof \DateTimeImmutable) {
                throw new \InvalidArgumentException('Time slot start and end must be instances of DateTimeImmutable.');
            }

            $start = $slot['start'];
            $end = $slot['end'];

            $this->assertSameDay($date, $start, 'start');
            $this->assertSameDay($date, $end, 'end');
            $this->assertFutureOrToday($start);
            $this->assertValidRange($start, $end);

            $normalized[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => $left['start'] <=> $right['start'],
        );

        return $normalized;
    }

    /**
     * @param iterable<mixed> $dates
     *
     * @return \DateTimeImmutable[]
     */
    private function normalizeTargetDates(iterable $dates): array
    {
        $normalized = [];

        foreach ($dates as $index => $date) {
            if (!$date instanceof \DateTimeImmutable) {
                throw new \InvalidArgumentException(sprintf('Target date at index %s must be an instance of DateTimeImmutable.', (string) $index));
            }

            $normalized[] = $this->normalizeDate($date);
        }

        $unique = [];
        $seen = [];

        foreach ($normalized as $date) {
            $key = $date->format('Y-m-d');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $date;
        }

        usort(
            $unique,
            static fn (\DateTimeImmutable $left, \DateTimeImmutable $right): int => $left <=> $right,
        );

        return $unique;
    }

    private function createAvailability(
        string $workerId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): WorkerAvailabilityEntity {
        return new WorkerAvailabilityEntity(
            $this->nextUuid(),
            $workerId,
            $start,
            $end,
            $this->now(),
        );
    }

    private function normalizeWorkerId(string $workerId): string
    {
        return $this->normalizeId($workerId, 'Worker id cannot be empty.');
    }

    private function normalizeId(string $value, string $message): string
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException($message);
        }

        return $normalized;
    }

    private function normalizeDate(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }

    private function assertSameDay(
        \DateTimeImmutable $expectedDate,
        \DateTimeImmutable $actualDate,
        string $context,
    ): void {
        if ($expectedDate->format('Y-m-d') !== $actualDate->format('Y-m-d')) {
            throw new \InvalidArgumentException(sprintf('Time slot %s must be within the declared day.', $context));
        }
    }

    private function ensureSameDay(
        \DateTimeImmutable $expectedReference,
        \DateTimeImmutable $value,
        string $context,
    ): \DateTimeImmutable {
        $this->assertSameDay($expectedReference->setTime(0, 0), $value, $context);

        return $value;
    }

    private function assertValidRange(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): void {
        if ($end <= $start) {
            throw new \InvalidArgumentException('Availability end time must be later than start time.');
        }

        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            throw new \InvalidArgumentException('Availability cannot span across multiple days.');
        }
    }

    private function assertFutureOrToday(\DateTimeImmutable $datetime): void
    {
        if ($datetime < $this->today()) {
            throw new \InvalidArgumentException('Availability cannot be set in the past.');
        }
    }

    private function withDate(\DateTimeImmutable $source, \DateTimeImmutable $targetDate): \DateTimeImmutable
    {
        return $targetDate->setTime(
            (int) $source->format('H'),
            (int) $source->format('i'),
            (int) $source->format('s'),
        );
    }

    private function today(): \DateTimeImmutable
    {
        return $this->now()->setTime(0, 0);
    }

    private function now(): \DateTimeImmutable
    {
        return ($this->nowFactory)();
    }

    private function nextUuid(): string
    {
        return ($this->uuidFactory)();
    }
}
