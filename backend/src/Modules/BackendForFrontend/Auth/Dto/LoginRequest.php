<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Auth\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public readonly string $login,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 255)]
        public readonly string $password,
    ) {
    }
}
