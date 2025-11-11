<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Event;

final class TicketRemovedEvent extends AbstractTicketEvent
{
    public function getType(): string
    {
        return 'ticket_removed';
    }
}
