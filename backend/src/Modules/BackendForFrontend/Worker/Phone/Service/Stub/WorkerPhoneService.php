<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Phone\Service\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\BackendForFrontend\Worker\Phone\Service\WorkerPhoneServiceInterface;

final class WorkerPhoneService implements WorkerPhoneServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function startCall(string $workerId): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function endCall(
        string $workerId,
        string $callId,
        ?string $ticketId,
        int $duration,
        ?string $notes,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
    ): array {
        return $this->notImplemented(__METHOD__);
    }
}
