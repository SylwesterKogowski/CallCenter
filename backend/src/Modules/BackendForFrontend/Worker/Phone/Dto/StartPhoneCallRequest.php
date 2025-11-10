<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Phone\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class StartPhoneCallRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Identyfikator pracownika jest wymagany')]
        #[Assert\Length(max: 255, maxMessage: 'Identyfikator pracownika jest zbyt długi')]
        public readonly string $workerId,
    ) {
    }
}


