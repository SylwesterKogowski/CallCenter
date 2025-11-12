<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Event;

final class TicketMessageEvent extends AbstractTicketEvent
{
    public function getType(): string
    {
        return 'message';
    }
}


