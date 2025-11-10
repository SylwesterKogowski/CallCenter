<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Domain\ClientInterface;

interface ClientServiceInterface
{
    public function getClientById(string $id): ?ClientInterface;

    public function findClientByEmail(string $email): ?ClientInterface;

    public function findClientByPhone(string $phone): ?ClientInterface;

    public function createClient(
        string $id,
        ?string $email = null,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ClientInterface;
}

