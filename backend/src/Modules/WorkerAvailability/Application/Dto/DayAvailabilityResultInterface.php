<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Application\Dto;

use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use DateTimeImmutable;

interface DayAvailabilityResultInterface
{
    public function getDate(): DateTimeImmutable;

    /**
     * @return iterable<WorkerAvailabilityInterface>
     */
    public function getTimeSlots(): iterable;

    public function getUpdatedAt(): DateTimeImmutable;
}


