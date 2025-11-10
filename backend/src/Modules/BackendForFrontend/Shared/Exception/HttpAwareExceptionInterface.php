<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Exception;

interface HttpAwareExceptionInterface
{
    public function getStatusCode(): int;

    /**
     * Message that should be exposed to the HTTP client.
     */
    public function getPublicMessage(): string;

    /**
     * Additional payload (e.g. validation errors) to merge into the JSON response.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array;
}

