<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain;

use DateTimeImmutable;

interface WorkerInterface
{
    public function getId(): string;

    public function getLogin(): string;

    public function isManager(): bool;

    public function getCreatedAt(): DateTimeImmutable;
}

