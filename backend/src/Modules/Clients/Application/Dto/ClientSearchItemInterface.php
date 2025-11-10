<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Dto;

use App\Modules\Clients\Domain\ClientInterface;

interface ClientSearchItemInterface
{
    public function getClient(): ClientInterface;

    public function getMatchScore(): ?float;
}


