<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

interface ClientInterface
{
    public function getId(): string;

    public function getEmail(): ?string;

    public function getPhone(): ?string;

    public function getFirstName(): ?string;

    public function getLastName(): ?string;
}

