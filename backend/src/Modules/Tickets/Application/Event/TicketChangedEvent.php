<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Event;

final class TicketChangedEvent extends AbstractTicketEvent
{
    public function getType(): string
    {
        return 'status_changed';
    }
}
