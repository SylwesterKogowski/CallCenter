<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\Clients\Application\ClientServiceInterface;
use App\Modules\Clients\Domain\ClientInterface;

final class ClientService implements ClientServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function getClientById(string $id): ?ClientInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function findClientByEmail(string $email): ?ClientInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function findClientByPhone(string $phone): ?ClientInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function createClient(
        string $id,
        ?string $email = null,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ClientInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function updateClient(
        ClientInterface $client,
        ?string $email = null,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ClientInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function identifyClient(
        ClientInterface $client,
        string $email,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ClientInterface {
        return $this->notImplemented(__METHOD__);
    }

    public function isClientAnonymous(ClientInterface $client): bool
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getClientTickets(ClientInterface $client): array
    {
        return $this->notImplemented(__METHOD__);
    }
}
