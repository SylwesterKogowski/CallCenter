<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Planning\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AutoAssignRequest
{
    /**
     * @param string[] $categories
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Data rozpoczęcia tygodnia jest wymagana')]
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}$/',
            message: 'Data musi być w formacie YYYY-MM-DD',
        )]
        public readonly string $weekStartDate,
        #[Assert\All(
            constraints: [
                new Assert\NotBlank(message: 'Identyfikator kategorii nie może być pusty'),
            ],
        )]
        #[Assert\Unique]
        public readonly array $categories = [],
    ) {
    }
}


