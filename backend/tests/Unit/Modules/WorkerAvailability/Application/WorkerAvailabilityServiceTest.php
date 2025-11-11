<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WorkerAvailability\Application;

use App\Modules\WorkerAvailability\Application\WorkerAvailabilityService;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityRepositoryInterface;
use App\Modules\WorkerAvailability\Infrastructure\Persistence\Doctrine\Entity\WorkerAvailability;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class WorkerAvailabilityServiceTest extends TestCase
{
    private const WORKER_ID = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

    /**
     * @var WorkerAvailabilityRepositoryInterface&MockObject
     */
    private WorkerAvailabilityRepositoryInterface $repository;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(WorkerAvailabilityRepositoryInterface::class);
        $this->now = new \DateTimeImmutable('2030-01-01 09:00:00');
    }

    public function testReplaceWorkerAvailabilityForDayReplacesExistingSlots(): void
    {
        $service = $this->createService();
        $date = new \DateTimeImmutable('2030-01-05 00:00:00');
        $timeSlots = [
            [
                'start' => new \DateTimeImmutable('2030-01-05 09:00:00'),
                'end' => new \DateTimeImmutable('2030-01-05 12:00:00'),
            ],
            [
                'start' => new \DateTimeImmutable('2030-01-05 14:00:00'),
                'end' => new \DateTimeImmutable('2030-01-05 17:00:00'),
            ],
        ];

        $saved = [];

        $this->repository
            ->expects(self::once())
            ->method('removeAllForDate')
            ->with(self::WORKER_ID, $date->setTime(0, 0));

        $this->repository
            ->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(static function (WorkerAvailabilityInterface $availability) use (&$saved): void {
                $saved[] = $availability;
            });

        $result = $service->replaceWorkerAvailabilityForDay(self::WORKER_ID, $date, $timeSlots);
        $timeSlotsResult = [...$result->getTimeSlots()];

        self::assertCount(2, $saved);
        self::assertSame($timeSlots[0]['start'], $saved[0]->getStartDatetime());
        self::assertSame($timeSlots[0]['end'], $saved[0]->getEndDatetime());
        self::assertSame($timeSlots[1]['start'], $saved[1]->getStartDatetime());
        self::assertSame($timeSlots[1]['end'], $saved[1]->getEndDatetime());

        self::assertSame($date->format('Y-m-d'), $result->getDate()->format('Y-m-d'));
        self::assertCount(2, $timeSlotsResult);
        self::assertSame($saved[0]->getId(), $timeSlotsResult[0]->getId());
        self::assertSame($saved[1]->getId(), $timeSlotsResult[1]->getId());
        self::assertSame($this->now, $result->getUpdatedAt());
    }

    public function testUpdateWorkerAvailabilitySlotUpdatesExistingSlot(): void
    {
        $service = $this->createService();
        $slotId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $existing = new WorkerAvailability(
            $slotId,
            self::WORKER_ID,
            new \DateTimeImmutable('2030-02-10 08:00:00'),
            new \DateTimeImmutable('2030-02-10 12:00:00'),
            $this->now,
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($slotId)
            ->willReturn($existing);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($existing);

        $updated = $service->updateWorkerAvailabilitySlot(
            self::WORKER_ID,
            $slotId,
            $start = new \DateTimeImmutable('2030-02-10 09:00:00'),
            $end = new \DateTimeImmutable('2030-02-10 15:00:00'),
        );

        self::assertSame($start, $updated->getStartDatetime());
        self::assertSame($end, $updated->getEndDatetime());
    }

    public function testUpdateWorkerAvailabilitySlotThrowsForDifferentWorker(): void
    {
        $service = $this->createService();
        $slotId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $existing = new WorkerAvailability(
            $slotId,
            'dddddddd-dddd-dddd-dddd-dddddddddddd',
            new \DateTimeImmutable('2030-03-01 08:00:00'),
            new \DateTimeImmutable('2030-03-01 12:00:00'),
            $this->now,
        );

        $this->repository
            ->method('findById')
            ->willReturn($existing);

        $this->expectException(\InvalidArgumentException::class);

        $service->updateWorkerAvailabilitySlot(
            self::WORKER_ID,
            $slotId,
            new \DateTimeImmutable('2030-03-01 09:00:00'),
            new \DateTimeImmutable('2030-03-01 10:00:00'),
        );
    }

    public function testRemoveWorkerAvailabilitySlotRemovesSlot(): void
    {
        $service = $this->createService();
        $slotId = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
        $existing = new WorkerAvailability(
            $slotId,
            self::WORKER_ID,
            new \DateTimeImmutable('2030-04-20 10:00:00'),
            new \DateTimeImmutable('2030-04-20 12:00:00'),
            $this->now,
        );

        $this->repository
            ->method('findById')
            ->with($slotId)
            ->willReturn($existing);

        $this->repository
            ->expects(self::once())
            ->method('remove')
            ->with($existing);

        $date = $service->removeWorkerAvailabilitySlot(self::WORKER_ID, $slotId);

        self::assertSame('2030-04-20 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testCopyWorkerAvailabilityCopiesToTargetDates(): void
    {
        $service = $this->createService();
        $sourceDate = new \DateTimeImmutable('2030-05-01 00:00:00');
        $targetOne = new \DateTimeImmutable('2030-05-02 00:00:00');
        $targetTwo = new \DateTimeImmutable('2030-05-03 00:00:00');

        $sourceSlots = [
            new WorkerAvailability(
                Uuid::v7()->toRfc4122(),
                self::WORKER_ID,
                new \DateTimeImmutable('2030-05-01 09:00:00'),
                new \DateTimeImmutable('2030-05-01 11:00:00'),
                $this->now,
            ),
            new WorkerAvailability(
                Uuid::v7()->toRfc4122(),
                self::WORKER_ID,
                new \DateTimeImmutable('2030-05-01 13:00:00'),
                new \DateTimeImmutable('2030-05-01 15:00:00'),
                $this->now,
            ),
        ];

        $expectedFindCalls = [
            [$sourceDate->setTime(0, 0), $sourceSlots],
            [$targetOne->setTime(0, 0), []],
            [$targetTwo->setTime(0, 0), []],
        ];
        $callIndex = 0;

        $this->repository
            ->expects(self::exactly(3))
            ->method('findForDate')
            ->willReturnCallback(function (string $workerId, \DateTimeImmutable $date) use (&$callIndex, $expectedFindCalls) {
                self::assertSame(self::WORKER_ID, $workerId);
                self::assertArrayHasKey($callIndex, $expectedFindCalls);

                [$expectedDate, $returnValue] = $expectedFindCalls[$callIndex];
                self::assertSame($expectedDate->format('Y-m-d H:i:s'), $date->format('Y-m-d H:i:s'));
                ++$callIndex;

                return $returnValue;
            });

        $this->repository
            ->expects(self::exactly(4))
            ->method('save')
            ->with(self::isInstanceOf(WorkerAvailabilityInterface::class));

        $result = $service->copyWorkerAvailability(
            self::WORKER_ID,
            $sourceDate,
            [$targetOne, $targetTwo],
            overwrite: true,
        );

        $copied = [...$result->getCopied()];

        self::assertCount(2, $copied);
        self::assertSame($targetOne->format('Y-m-d'), $copied[0]->getDate()->format('Y-m-d'));
        self::assertSame($targetTwo->format('Y-m-d'), $copied[1]->getDate()->format('Y-m-d'));
        $copiedSlotsFirst = [...$copied[0]->getTimeSlots()];
        $copiedSlotsSecond = [...$copied[1]->getTimeSlots()];
        self::assertCount(2, $copiedSlotsFirst);
        self::assertCount(2, $copiedSlotsSecond);
        self::assertSame('09:00:00', $copiedSlotsFirst[0]->getStartDatetime()->format('H:i:s'));
        self::assertSame('11:00:00', $copiedSlotsFirst[0]->getEndDatetime()->format('H:i:s'));
        self::assertSame('13:00:00', $copiedSlotsFirst[1]->getStartDatetime()->format('H:i:s'));
        self::assertSame('15:00:00', $copiedSlotsFirst[1]->getEndDatetime()->format('H:i:s'));
        self::assertSame('09:00:00', $copiedSlotsSecond[0]->getStartDatetime()->format('H:i:s'));
        self::assertSame('11:00:00', $copiedSlotsSecond[0]->getEndDatetime()->format('H:i:s'));
        self::assertSame('13:00:00', $copiedSlotsSecond[1]->getStartDatetime()->format('H:i:s'));
        self::assertSame('15:00:00', $copiedSlotsSecond[1]->getEndDatetime()->format('H:i:s'));
        self::assertSame([], [...$result->getSkippedDates()]);
    }

    public function testCopyWorkerAvailabilitySkipsExistingWhenNotOverwriting(): void
    {
        $service = $this->createService();
        $sourceDate = new \DateTimeImmutable('2030-06-01 00:00:00');
        $targetDate = new \DateTimeImmutable('2030-06-02 00:00:00');

        $sourceSlots = [
            new WorkerAvailability(
                Uuid::v7()->toRfc4122(),
                self::WORKER_ID,
                new \DateTimeImmutable('2030-06-01 08:00:00'),
                new \DateTimeImmutable('2030-06-01 10:00:00'),
                $this->now,
            ),
        ];

        $expectedFindCalls = [
            [$sourceDate->setTime(0, 0), $sourceSlots],
            [$targetDate->setTime(0, 0), $sourceSlots],
        ];
        $callIndex = 0;

        $this->repository
            ->expects(self::exactly(2))
            ->method('findForDate')
            ->willReturnCallback(function (string $workerId, \DateTimeImmutable $date) use (&$callIndex, $expectedFindCalls) {
                self::assertSame(self::WORKER_ID, $workerId);
                self::assertArrayHasKey($callIndex, $expectedFindCalls);

                [$expectedDate, $returnValue] = $expectedFindCalls[$callIndex];
                self::assertSame($expectedDate->format('Y-m-d H:i:s'), $date->format('Y-m-d H:i:s'));
                ++$callIndex;

                return $returnValue;
            });

        $this->repository
            ->expects(self::never())
            ->method('removeAllForDate');

        $this->repository
            ->expects(self::never())
            ->method('save');

        $result = $service->copyWorkerAvailability(
            self::WORKER_ID,
            $sourceDate,
            [$targetDate],
            overwrite: false,
        );

        self::assertSame([], [...$result->getCopied()]);
        $skipped = [...$result->getSkippedDates()];
        self::assertCount(1, $skipped);
        self::assertSame($targetDate->format('Y-m-d'), $skipped[0]->format('Y-m-d'));
    }

    private function createService(): WorkerAvailabilityService
    {
        $uuidSequence = 0;
        $uuidFactory = static function () use (&$uuidSequence): string {
            ++$uuidSequence;

            return sprintf('00000000-0000-0000-0000-%012d', $uuidSequence);
        };

        return new WorkerAvailabilityService(
            $this->repository,
            $uuidFactory,
            fn (): \DateTimeImmutable => $this->now,
        );
    }
}
