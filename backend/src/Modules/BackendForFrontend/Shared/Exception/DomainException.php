<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Exception;

abstract class DomainException extends \RuntimeException implements HttpAwareExceptionInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        protected array $context = [],
        ?int $code = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code ?? 0, $previous);
    }

    abstract public function getStatusCode(): int;

    public function getPublicMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
