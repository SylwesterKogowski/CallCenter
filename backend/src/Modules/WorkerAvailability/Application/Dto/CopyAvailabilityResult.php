<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Application\Dto;

final class CopyAvailabilityResult implements CopyAvailabilityResultInterface
{
    /**
     * @param iterable<DayAvailabilityResultInterface> $copied
     * @param iterable<\DateTimeImmutable>             $skippedDates
     */
    public function __construct(
        private readonly iterable $copied,
        private readonly iterable $skippedDates,
    ) {
    }

    public function getCopied(): iterable
    {
        return $this->copied;
    }

    public function getSkippedDates(): iterable
    {
        return $this->skippedDates;
    }
}
