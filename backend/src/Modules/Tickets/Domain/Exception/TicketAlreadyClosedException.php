<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Exception;

final class TicketAlreadyClosedException extends TicketDomainException
{
    public static function create(): self
    {
        return new self('Ticket is already closed.');
    }
}
