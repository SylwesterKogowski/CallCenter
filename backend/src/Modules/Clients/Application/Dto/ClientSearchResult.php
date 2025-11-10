<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Dto;

final class ClientSearchResult implements ClientSearchResultInterface
{
    /**
     * @param ClientSearchItemInterface[] $clients
     */
    public function __construct(
        private readonly array $clients,
        private readonly int $total,
    ) {
    }

    public function getClients(): iterable
    {
        return $this->clients;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
