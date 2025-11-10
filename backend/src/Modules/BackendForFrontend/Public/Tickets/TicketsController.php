<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Public\Tickets;

use App\Modules\BackendForFrontend\Public\Tickets\Dto\CreateTicketClientDto;
use App\Modules\BackendForFrontend\Public\Tickets\Dto\CreateTicketRequest;
use App\Modules\BackendForFrontend\Public\Tickets\Dto\SendTicketMessageRequest;
use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\ResourceNotFoundException;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\Clients\Application\ClientServiceInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/api/tickets', name: 'backend_for_frontend_public_tickets_')]
class TicketsController extends AbstractJsonController
{
    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private ClientServiceInterface $clientService,
        private TicketCategoryServiceInterface $ticketCategoryService,
        private TicketServiceInterface $ticketService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    public function create(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateCreateTicketRequest($payload);
            $this->validateDto($dto);

            if (!$dto->client->hasContactData()) {
                throw new ValidationException('Dane kontaktowe klienta są wymagane', ['client.email' => ['Podaj email lub numer telefonu'], 'client.phone' => ['Podaj email lub numer telefonu']]);
            }

            $category = $this->ticketCategoryService->getCategoriesByIds([$dto->categoryId])[0] ?? null;

            if (null === $category) {
                throw new ResourceNotFoundException('Kategoria nie została znaleziona');
            }

            $client = $this->resolveClient($dto->client);

            $ticket = $this->ticketService->createTicket(
                Uuid::v4()->toRfc4122(),
                $client,
                $category,
                $dto->title,
                $dto->description,
            );

            return [
                'ticket' => $this->formatCreatedTicket($ticket),
            ];
        }, Response::HTTP_CREATED);
    }

    #[Route(path: '/{ticketId}', name: 'details', methods: [Request::METHOD_GET])]
    public function details(string $ticketId): JsonResponse
    {
        return $this->execute(function () use ($ticketId) {
            $ticket = $this->ticketService->getTicketById($ticketId);

            if (null === $ticket) {
                throw new ResourceNotFoundException('Ticket nie został znaleziony');
            }

            $messages = $this->ticketService->getTicketMessages($ticket);

            return [
                'ticket' => $this->formatTicketDetails($ticket),
                'messages' => array_map(
                    fn (TicketMessageInterface $message): array => $this->formatMessage($message),
                    $messages,
                ),
            ];
        });
    }

    #[Route(path: '/{ticketId}/messages', name: 'send_message', methods: [Request::METHOD_POST])]
    public function sendMessage(string $ticketId, Request $request): JsonResponse
    {
        return $this->execute(function () use ($ticketId, $request) {
            $ticket = $this->ticketService->getTicketById($ticketId);

            if (null === $ticket) {
                throw new ResourceNotFoundException('Ticket nie został znaleziony');
            }

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateSendTicketMessageRequest($payload);
            $this->validateDto($dto);

            $message = $this->ticketService->addMessageToTicket(
                $ticket,
                $dto->content,
                'client',
                $ticket->getClient()->getId(),
                $this->buildClientDisplayName($ticket->getClient()),
            );

            return [
                'message' => $this->formatMessage($message),
            ];
        }, Response::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateCreateTicketRequest(array $payload): CreateTicketRequest
    {
        $clientPayload = [];

        if (isset($payload['client']) && is_array($payload['client'])) {
            $clientPayload = $payload['client'];
        }

        $clientDto = new CreateTicketClientDto(
            email: isset($clientPayload['email']) ? (string) $clientPayload['email'] : null,
            phone: isset($clientPayload['phone']) ? (string) $clientPayload['phone'] : null,
            firstName: isset($clientPayload['firstName']) ? (string) $clientPayload['firstName'] : null,
            lastName: isset($clientPayload['lastName']) ? (string) $clientPayload['lastName'] : null,
        );

        return new CreateTicketRequest(
            categoryId: (string) ($payload['categoryId'] ?? ''),
            client: $clientDto,
            title: isset($payload['title']) ? (string) $payload['title'] : null,
            description: isset($payload['description']) ? (string) $payload['description'] : null,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateSendTicketMessageRequest(array $payload): SendTicketMessageRequest
    {
        return new SendTicketMessageRequest(
            content: (string) ($payload['content'] ?? ''),
        );
    }

    private function resolveClient(CreateTicketClientDto $clientDto): ClientInterface
    {
        $client = null;

        if ($clientDto->email) {
            $client = $this->clientService->findClientByEmail($clientDto->email);
        }

        if (null === $client && $clientDto->phone) {
            $client = $this->clientService->findClientByPhone($clientDto->phone);
        }

        if (null === $client) {
            $client = $this->clientService->createClient(
                Uuid::v4()->toRfc4122(),
                $clientDto->email,
                $clientDto->phone,
                $clientDto->firstName,
                $clientDto->lastName,
            );
        }

        return $client;
    }

    /**
     * @return array{
     *     id: string,
     *     clientId: string,
     *     categoryId: string,
     *     title: ?string,
     *     description: ?string,
     *     status: string,
     *     createdAt: string
     * }
     */
    private function formatCreatedTicket(TicketInterface $ticket): array
    {
        return [
            'id' => $ticket->getId(),
            'clientId' => $ticket->getClient()->getId(),
            'categoryId' => $ticket->getCategory()->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus(),
            'createdAt' => $ticket->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     clientId: string,
     *     categoryId: string,
     *     categoryName: string,
     *     title: ?string,
     *     description: ?string,
     *     status: string,
     *     createdAt: string,
     *     updatedAt: ?string
     * }
     */
    private function formatTicketDetails(TicketInterface $ticket): array
    {
        $category = $ticket->getCategory();

        return [
            'id' => $ticket->getId(),
            'clientId' => $ticket->getClient()->getId(),
            'categoryId' => $category->getId(),
            'categoryName' => $category->getName(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus(),
            'createdAt' => $ticket->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $ticket->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     ticketId: string,
     *     senderType: string,
     *     senderId: ?string,
     *     senderName: ?string,
     *     content: string,
     *     createdAt: string,
     *     status: ?string
     * }
     */
    private function formatMessage(TicketMessageInterface $message): array
    {
        return [
            'id' => $message->getId(),
            'ticketId' => $message->getTicketId(),
            'senderType' => $message->getSenderType(),
            'senderId' => $message->getSenderId(),
            'senderName' => $message->getSenderName(),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
            'status' => $message->getStatus(),
        ];
    }

    private function buildClientDisplayName(ClientInterface $client): ?string
    {
        $parts = array_filter([$client->getFirstName(), $client->getLastName()]);

        if (!empty($parts)) {
            return implode(' ', $parts);
        }

        return $client->getEmail() ?? $client->getPhone();
    }
}
