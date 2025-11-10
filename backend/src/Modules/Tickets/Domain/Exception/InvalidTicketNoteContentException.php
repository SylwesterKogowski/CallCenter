<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Exception;

final class InvalidTicketNoteContentException extends TicketDomainException
{
    public static function create(): self
    {
        return new self('Ticket note content must not be empty.');
    }
}
