<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class MonitoringQuery
{
    public function __construct(
        #[Assert\NotBlank(message: 'Parametr "date" jest wymagany')]
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}$/',
            message: 'Data musi być w formacie YYYY-MM-DD',
        )]
        public readonly string $date,
    ) {
    }
}


