<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Exception;

final class InvalidTicketStatusException extends TicketDomainException
{
    public static function forStatus(string $status): self
    {
        return new self(sprintf('Status "%s" is not allowed for ticket.', $status));
    }
}
