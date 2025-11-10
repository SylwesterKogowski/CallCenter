<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WorkerAvailability\Domain;

use App\Modules\WorkerAvailability\Domain\WorkerAvailability;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class WorkerAvailabilityTest extends TestCase
{
    public function testCreationSetsProperties(): void
    {
        $start = new \DateTimeImmutable('2030-01-10 09:00:00');
        $end = new \DateTimeImmutable('2030-01-10 17:00:00');

        $availability = new WorkerAvailability(
            $id = Uuid::v7()->toRfc4122(),
            $workerId = Uuid::v7()->toRfc4122(),
            $start,
            $end,
            $createdAt = new \DateTimeImmutable('2030-01-01 12:00:00'),
        );

        self::assertSame($id, $availability->getId());
        self::assertSame($workerId, $availability->getWorkerId());
        self::assertSame($start, $availability->getStartDatetime());
        self::assertSame($end, $availability->getEndDatetime());
        self::assertSame($createdAt, $availability->getCreatedAt());
        self::assertNull($availability->getUpdatedAt());
        self::assertSame('2030-01-10 00:00:00', $availability->getDate()->format('Y-m-d H:i:s'));
        self::assertSame(480, $availability->getDurationMinutes());
    }

    public function testUpdateAvailabilityChangesDatesAndSetsUpdatedAt(): void
    {
        $availability = $this->createAvailability(
            new \DateTimeImmutable('2030-02-01 08:00:00'),
            new \DateTimeImmutable('2030-02-01 12:00:00'),
        );

        $start = new \DateTimeImmutable('2030-02-01 10:00:00');
        $end = new \DateTimeImmutable('2030-02-01 15:00:00');

        $availability->updateAvailability($start, $end);

        self::assertSame($start, $availability->getStartDatetime());
        self::assertSame($end, $availability->getEndDatetime());
        self::assertInstanceOf(\DateTimeImmutable::class, $availability->getUpdatedAt());
    }

    public function testOverlapsWithDetectsOverlappingSlots(): void
    {
        $workerId = Uuid::v7()->toRfc4122();

        $morning = new WorkerAvailability(
            Uuid::v7()->toRfc4122(),
            $workerId,
            new \DateTimeImmutable('2030-03-15 09:00:00'),
            new \DateTimeImmutable('2030-03-15 12:00:00'),
        );

        $afternoon = new WorkerAvailability(
            Uuid::v7()->toRfc4122(),
            $workerId,
            new \DateTimeImmutable('2030-03-15 11:30:00'),
            new \DateTimeImmutable('2030-03-15 16:00:00'),
        );

        self::assertTrue($morning->overlapsWith($afternoon));
        self::assertTrue($afternoon->overlapsWith($morning));
    }

    public function testNonOverlappingSlotsReturnFalse(): void
    {
        $workerId = Uuid::v7()->toRfc4122();

        $morning = new WorkerAvailability(
            Uuid::v7()->toRfc4122(),
            $workerId,
            new \DateTimeImmutable('2030-04-01 09:00:00'),
            new \DateTimeImmutable('2030-04-01 12:00:00'),
        );

        $afternoon = new WorkerAvailability(
            Uuid::v7()->toRfc4122(),
            $workerId,
            new \DateTimeImmutable('2030-04-01 12:00:00'),
            new \DateTimeImmutable('2030-04-01 16:00:00'),
        );

        self::assertFalse($morning->overlapsWith($afternoon));
    }

    public function testIsAvailableInRange(): void
    {
        $availability = $this->createAvailability(
            new \DateTimeImmutable('2030-05-10 08:00:00'),
            new \DateTimeImmutable('2030-05-10 16:00:00'),
        );

        self::assertTrue($availability->isAvailableInRange(
            new \DateTimeImmutable('2030-05-10 09:00:00'),
            new \DateTimeImmutable('2030-05-10 12:00:00'),
        ));

        self::assertFalse($availability->isAvailableInRange(
            new \DateTimeImmutable('2030-05-10 07:59:59'),
            new \DateTimeImmutable('2030-05-10 12:00:00'),
        ));
    }

    public function testConstructorThrowsForInvalidDateRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createAvailability(
            new \DateTimeImmutable('2030-06-01 12:00:00'),
            new \DateTimeImmutable('2030-06-01 11:59:59'),
        );
    }

    public function testConstructorThrowsWhenCrossingMidnight(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createAvailability(
            new \DateTimeImmutable('2030-07-01 22:00:00'),
            new \DateTimeImmutable('2030-07-02 02:00:00'),
        );
    }

    private function createAvailability(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): WorkerAvailability {
        return new WorkerAvailability(
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            $start,
            $end,
            new \DateTimeImmutable('2030-01-01 00:00:00'),
        );
    }
}
