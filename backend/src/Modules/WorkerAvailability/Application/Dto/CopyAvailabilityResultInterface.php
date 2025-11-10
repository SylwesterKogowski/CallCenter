<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Application\Dto;

interface CopyAvailabilityResultInterface
{
    /**
     * @return iterable<DayAvailabilityResultInterface>
     */
    public function getCopied(): iterable;

    /**
     * @return iterable<\DateTimeImmutable>
     */
    public function getSkippedDates(): iterable;
}


