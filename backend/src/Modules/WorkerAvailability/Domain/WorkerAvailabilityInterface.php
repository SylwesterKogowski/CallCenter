<?php

declare(strict_types=1);

namespace App\Modules\WorkerAvailability\Domain;

use DateTimeImmutable;

interface WorkerAvailabilityInterface
{
    public function getId(): string;

    public function getWorkerId(): string;

    public function getStartDatetime(): DateTimeImmutable;

    public function getEndDatetime(): DateTimeImmutable;

    public function getCreatedAt(): DateTimeImmutable;

    public function getUpdatedAt(): ?DateTimeImmutable;
}


