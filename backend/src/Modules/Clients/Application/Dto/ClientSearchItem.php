<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Dto;

use App\Modules\Clients\Domain\ClientInterface;

final class ClientSearchItem implements ClientSearchItemInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly ?float $matchScore,
    ) {
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function getMatchScore(): ?float
    {
        return $this->matchScore;
    }
}
