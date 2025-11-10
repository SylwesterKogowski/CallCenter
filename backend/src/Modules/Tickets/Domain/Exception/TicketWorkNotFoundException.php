<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Exception;

final class TicketWorkNotFoundException extends TicketDomainException
{
    public static function forWorker(string $ticketId, string $workerId): self
    {
        return new self(sprintf('Active work session for worker "%s" and ticket "%s" not found.', $workerId, $ticketId));
    }
}
