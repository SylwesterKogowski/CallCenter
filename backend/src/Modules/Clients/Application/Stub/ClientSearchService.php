<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\Clients\Application\ClientSearchServiceInterface;
use App\Modules\Clients\Application\Dto\ClientSearchResultInterface;

final class ClientSearchService implements ClientSearchServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function searchClients(string $query, int $limit): ClientSearchResultInterface
    {
        return $this->notImplemented(__METHOD__);
    }
}
