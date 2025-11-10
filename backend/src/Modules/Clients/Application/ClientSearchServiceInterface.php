<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Application\Dto\ClientSearchResultInterface;

interface ClientSearchServiceInterface
{
    public function searchClients(string $query, int $limit): ClientSearchResultInterface;
}


