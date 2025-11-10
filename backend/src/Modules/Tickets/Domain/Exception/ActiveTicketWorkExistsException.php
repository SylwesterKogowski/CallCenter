<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Exception;

final class ActiveTicketWorkExistsException extends TicketDomainException
{
    public static function forWorker(string $ticketId, string $workerId): self
    {
        return new self(sprintf('Worker "%s" already has an active work session for ticket "%s".', $workerId, $ticketId));
    }
}
