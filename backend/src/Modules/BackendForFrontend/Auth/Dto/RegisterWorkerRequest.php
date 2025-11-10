<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Auth\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterWorkerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        #[Assert\Regex(pattern: '/^[a-zA-Z0-9._]+$/', message: 'Login może zawierać litery, cyfry, kropki i podkreślenia')]
        public readonly string $login,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 255)]
        public readonly string $password,
        /**
         * @var string[]
         */
        #[Assert\NotNull]
        #[Assert\Count(min: 1, minMessage: 'Wybierz co najmniej jedną kategorię')]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Length(min: 1),
        ])]
        public readonly array $categoryIds,
        #[Assert\Type('bool')]
        public readonly bool $isManager = false,
    ) {
    }
}
