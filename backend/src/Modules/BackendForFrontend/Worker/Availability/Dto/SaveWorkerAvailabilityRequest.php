<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Availability\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SaveWorkerAvailabilityRequest
{
    /**
     * @param TimeSlotPayload[] $timeSlots
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('array')]
        #[Assert\All(constraints: [
            new Assert\Type(type: TimeSlotPayload::class),
        ])]
        public readonly array $timeSlots = [],
    ) {
    }
}


