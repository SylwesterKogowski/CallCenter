<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\Clients\Domain\ClientInterface;

/**
 * Lightweight immutable client representation stored together with the ticket.
 */
final class TicketClientSnapshot implements ClientInterface
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
        private readonly ?string $firstName = null,
        private readonly ?string $lastName = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }
}
