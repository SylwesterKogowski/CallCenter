<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Exception;

class AccessDeniedException extends DomainException
{
    public function __construct(
        string $message = 'Brak uprawnień',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, null, $previous);
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}

