<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Security;

final class AuthenticatedWorker
{
    /**
     * @param string[] $categoryIds
     */
    public function __construct(
        private string $id,
        private string $login,
        private bool $isManager,
        private array $categoryIds,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function isManager(): bool
    {
        return $this->isManager;
    }

    /**
     * @return string[]
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }
}

