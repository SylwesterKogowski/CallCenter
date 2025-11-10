<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAutoAssignmentSettingsInput
{
    public function __construct(
        #[Assert\NotNull(message: 'Pole "considerEfficiency" jest wymagane')]
        #[Assert\Type('bool', message: 'Pole "considerEfficiency" musi być typu bool')]
        public readonly bool $considerEfficiency,
        #[Assert\NotNull(message: 'Pole "considerAvailability" jest wymagane')]
        #[Assert\Type('bool', message: 'Pole "considerAvailability" musi być typu bool')]
        public readonly bool $considerAvailability,
        #[Assert\NotNull(message: 'Pole "maxTicketsPerWorker" jest wymagane')]
        #[Assert\Type('int', message: 'Pole "maxTicketsPerWorker" musi być liczbą całkowitą')]
        #[Assert\Positive(message: 'Pole "maxTicketsPerWorker" musi być liczbą dodatnią')]
        public readonly int $maxTicketsPerWorker,
    ) {
    }
}


