<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Dto;

interface ClientSearchResultInterface
{
    /**
     * @return iterable<ClientSearchItemInterface>
     */
    public function getClients(): iterable;

    public function getTotal(): int;
}
