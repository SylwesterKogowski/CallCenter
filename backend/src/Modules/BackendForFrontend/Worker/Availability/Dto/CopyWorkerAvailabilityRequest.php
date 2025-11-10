<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Availability\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CopyWorkerAvailabilityRequest
{
    /**
     * @param string[] $targetDates
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Data źródłowa jest wymagana')]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Data źródłowa musi być w formacie YYYY-MM-DD')]
        public readonly string $sourceDate,
        #[Assert\NotNull(message: 'Podaj co najmniej jedną datę docelową')]
        #[Assert\Type('array')]
        #[Assert\Count(min: 1, minMessage: 'Podaj co najmniej jedną datę docelową')]
        #[Assert\All(constraints: [
            new Assert\NotBlank(message: 'Data docelowa jest wymagana'),
            new Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Data docelowa musi być w formacie YYYY-MM-DD'),
        ])]
        public readonly array $targetDates,
        #[Assert\Type('bool')]
        public readonly bool $overwrite = false,
    ) {
    }
}


