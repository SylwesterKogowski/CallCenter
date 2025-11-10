<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Exception;

final class InvalidTicketTimeEntryException extends TicketDomainException
{
    public static function minutesMustBePositive(int $minutes): self
    {
        return new self(sprintf('Minutes must be a positive integer, got %d.', $minutes));
    }

    public static function missingStart(): self
    {
        return new self('Ticket work entry must contain start time.');
    }
}
