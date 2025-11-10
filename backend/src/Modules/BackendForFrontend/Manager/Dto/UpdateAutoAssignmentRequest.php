<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAutoAssignmentRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Pole "enabled" jest wymagane')]
        #[Assert\Type('bool', message: 'Pole "enabled" musi być typu bool')]
        public readonly bool $enabled,
        #[Assert\NotNull(message: 'Pole "settings" jest wymagane')]
        #[Assert\Valid]
        public readonly UpdateAutoAssignmentSettingsInput $settings,
    ) {
    }
}
