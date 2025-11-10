<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Exception;

class ValidationException extends DomainException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        string $message = 'Błędne dane wejściowe',
        array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, ['errors' => $errors], null, $previous);
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->context['errors'] ?? [];
    }
}
