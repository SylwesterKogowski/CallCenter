<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Public\Tickets;

use App\Modules\BackendForFrontend\Public\Tickets\TicketsController;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class TicketsControllerTest extends BackendForFrontendTestCase
{
    public function testCreateTicketValidatesClientContactData(): void
    {
        $this->createClientWithMocks();

        /** @var TicketsController $controller */
        $controller = static::getContainer()->get(TicketsController::class);

        $this->ticketCategoryService
            ->expects(self::never())
            ->method('getCategoriesByIds');

        $this->clientService
            ->expects(self::never())
            ->method('findClientByEmail');

        $request = $this->createJsonRequest(
            'POST',
            '/api/tickets',
            [
                'categoryId' => 'category-1',
                'client' => [
                    'email' => '',
                    'phone' => '',
                ],
                'title' => 'Problem z usługą',
                'description' => 'Opis problemu',
            ],
        );

        $response = $controller->create($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Dane kontaktowe klienta są wymagane', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('client.email', $data['errors']);
        self::assertArrayHasKey('client.phone', $data['errors']);
        self::assertContains('Podaj email lub numer telefonu', $data['errors']['client.email']);
        self::assertContains('Podaj email lub numer telefonu', $data['errors']['client.phone']);
    }

    public function testCreateTicketPersistsTicketAndReturnsResponse(): void
    {
        $this->createClientWithMocks();

        /** @var TicketsController $controller */
        $controller = static::getContainer()->get(TicketsController::class);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-1',
            'getName' => 'Wsparcie',
        ]);

        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-1',
            'getEmail' => 'alice@example.com',
            'getPhone' => null,
            'getFirstName' => 'Alice',
            'getLastName' => 'Nowak',
        ]);

        $createdAt = new \DateTimeImmutable('2024-05-05T10:15:30+00:00');

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-1',
            'getClient' => $client,
            'getCategory' => $category,
            'getTitle' => 'Problem z usługą',
            'getDescription' => 'Opis problemu',
            'getStatus' => 'NEW',
            'getCreatedAt' => $createdAt,
        ]);

        $this->ticketCategoryService
            ->expects(self::once())
            ->method('getCategoriesByIds')
            ->with(['cat-1'])
            ->willReturn([$category]);

        $this->clientService
            ->expects(self::once())
            ->method('findClientByEmail')
            ->with('alice@example.com')
            ->willReturn($client);

        $this->clientService
            ->expects(self::never())
            ->method('findClientByPhone');

        $this->clientService
            ->expects(self::never())
            ->method('createClient');

        $this->ticketService
            ->expects(self::once())
            ->method('createTicket')
            ->with(
                self::callback(static fn (string $ticketId): bool => Uuid::isValid($ticketId)),
                self::identicalTo($client),
                self::identicalTo($category),
                'Problem z usługą',
                'Opis problemu',
            )
            ->willReturn($ticket);

        $request = $this->createJsonRequest(
            'POST',
            '/api/tickets',
            [
                'categoryId' => 'cat-1',
                'client' => [
                    'email' => 'alice@example.com',
                ],
                'title' => 'Problem z usługą',
                'description' => 'Opis problemu',
            ],
        );

        $response = $controller->create($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            'ticket' => [
                'id' => 'ticket-1',
                'clientId' => 'client-1',
                'categoryId' => 'cat-1',
                'title' => 'Problem z usługą',
                'description' => 'Opis problemu',
                'status' => 'NEW',
                'createdAt' => '2024-05-05T10:15:30+00:00',
            ],
        ], $data);
    }

    public function testDetailsReturnsTicketWithMessages(): void
    {
        $this->createClientWithMocks();

        /** @var TicketsController $controller */
        $controller = static::getContainer()->get(TicketsController::class);

        $category = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-9',
            'getName' => 'Billing',
        ]);

        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-42',
        ]);

        $createdAt = new \DateTimeImmutable('2024-05-06T12:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2024-05-07T08:30:00+00:00');

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-42',
            'getClient' => $client,
            'getCategory' => $category,
            'getTitle' => 'Brak faktury',
            'getDescription' => 'Nie otrzymałem faktury za kwiecień',
            'getStatus' => 'OPEN',
            'getCreatedAt' => $createdAt,
            'getUpdatedAt' => $updatedAt,
        ]);

        $messageCreatedAt = new \DateTimeImmutable('2024-05-06T13:00:00+00:00');

        $message = $this->createConfiguredMock(TicketMessageInterface::class, [
            'getId' => 'message-1',
            'getTicketId' => 'ticket-42',
            'getSenderType' => 'client',
            'getSenderId' => 'client-42',
            'getSenderName' => 'Jan Kowalski',
            'getContent' => 'Proszę o wystawienie faktury',
            'getCreatedAt' => $messageCreatedAt,
            'getStatus' => 'DELIVERED',
        ]);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-42')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketMessages')
            ->with(self::identicalTo($ticket))
            ->willReturn([$message]);

        $response = $controller->details('ticket-42');
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'ticket' => [
                'id' => 'ticket-42',
                'clientId' => 'client-42',
                'categoryId' => 'cat-9',
                'categoryName' => 'Billing',
                'title' => 'Brak faktury',
                'description' => 'Nie otrzymałem faktury za kwiecień',
                'status' => 'OPEN',
                'createdAt' => '2024-05-06T12:00:00+00:00',
                'updatedAt' => '2024-05-07T08:30:00+00:00',
            ],
            'messages' => [
                [
                    'id' => 'message-1',
                    'ticketId' => 'ticket-42',
                    'senderType' => 'client',
                    'senderId' => 'client-42',
                    'senderName' => 'Jan Kowalski',
                    'content' => 'Proszę o wystawienie faktury',
                    'createdAt' => '2024-05-06T13:00:00+00:00',
                    'status' => 'DELIVERED',
                ],
            ],
        ], $data);
    }

    public function testDetailsReturnsNotFoundWhenTicketMissing(): void
    {
        $this->createClientWithMocks();

        /** @var TicketsController $controller */
        $controller = static::getContainer()->get(TicketsController::class);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('missing-ticket')
            ->willReturn(null);

        $this->ticketService
            ->expects(self::never())
            ->method('getTicketMessages');

        $response = $controller->details('missing-ticket');
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('Ticket nie został znaleziony', $data['message'] ?? null);
    }

    public function testSendMessageValidatesPayload(): void
    {
        $this->createClientWithMocks();

        /** @var TicketsController $controller */
        $controller = static::getContainer()->get(TicketsController::class);

        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-1',
            'getFirstName' => 'Anna',
            'getLastName' => 'Nowak',
            'getEmail' => 'anna@example.com',
            'getPhone' => null,
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-55',
            'getClient' => $client,
        ]);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-55')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::never())
            ->method('addMessageToTicket');

        $request = $this->createJsonRequest(
            'POST',
            '/api/tickets/ticket-55/messages',
            [
                'content' => '',
            ],
        );

        $response = $controller->sendMessage('ticket-55', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('content', $data['errors']);
        self::assertContains('Treść wiadomości jest wymagana', $data['errors']['content']);
    }

    public function testSendMessageReturnsCreatedPayload(): void
    {
        $this->createClientWithMocks();

        /** @var TicketsController $controller */
        $controller = static::getContainer()->get(TicketsController::class);

        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getId' => 'client-9',
            'getFirstName' => 'Katarzyna',
            'getLastName' => 'Wiśniewska',
            'getEmail' => 'katarzyna@example.com',
            'getPhone' => null,
        ]);

        $ticket = $this->createConfiguredMock(TicketInterface::class, [
            'getId' => 'ticket-99',
            'getClient' => $client,
        ]);

        $messageCreatedAt = new \DateTimeImmutable('2024-05-08T09:45:00+00:00');

        $message = $this->createConfiguredMock(TicketMessageInterface::class, [
            'getId' => 'message-9',
            'getTicketId' => 'ticket-99',
            'getSenderType' => 'client',
            'getSenderId' => 'client-9',
            'getSenderName' => 'Katarzyna Wiśniewska',
            'getContent' => 'Czy są nowe informacje?',
            'getCreatedAt' => $messageCreatedAt,
            'getStatus' => 'PENDING',
        ]);

        $this->ticketService
            ->expects(self::once())
            ->method('getTicketById')
            ->with('ticket-99')
            ->willReturn($ticket);

        $this->ticketService
            ->expects(self::once())
            ->method('addMessageToTicket')
            ->with(
                self::identicalTo($ticket),
                'Czy są nowe informacje?',
                'client',
                'client-9',
                'Katarzyna Wiśniewska',
            )
            ->willReturn($message);

        $request = $this->createJsonRequest(
            'POST',
            '/api/tickets/ticket-99/messages',
            [
                'content' => 'Czy są nowe informacje?',
            ],
        );

        $response = $controller->sendMessage('ticket-99', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame([
            'message' => [
                'id' => 'message-9',
                'ticketId' => 'ticket-99',
                'senderType' => 'client',
                'senderId' => 'client-9',
                'senderName' => 'Katarzyna Wiśniewska',
                'content' => 'Czy są nowe informacje?',
                'createdAt' => '2024-05-08T09:45:00+00:00',
                'status' => 'PENDING',
            ],
        ], $data);
    }
}
