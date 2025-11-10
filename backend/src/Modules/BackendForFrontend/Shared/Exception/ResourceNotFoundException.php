<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Exception;

class ResourceNotFoundException extends DomainException
{
    public function __construct(
        string $message = 'Nie znaleziono zasobu',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, null, $previous);
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}
