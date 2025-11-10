<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Public\Tickets;

use App\Modules\BackendForFrontend\Public\Tickets\TicketsController;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class TicketsControllerTest extends BackendForFrontendTestCase
{
    // TODO: assert that create ticket endpoint validates anonymous client payload structure
    // TODO: assert that create ticket endpoint persists ticket via TicketService and returns response DTO
    // TODO: assert that get ticket endpoint returns full ticket details
    // TODO: assert that get ticket endpoint maps missing ticket to 404 error
    // TODO: assert that add message endpoint validates payload and delegates to TicketService
    // TODO: assert that add message endpoint returns created message payload
}
