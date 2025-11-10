<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\TicketCategories;

use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class TicketCategoriesControllerTest extends BackendForFrontendTestCase
{
    public function testListReturnsTransformedCategories(): void
    {
        $client = $this->createClientWithMocks();

        $categoryOne = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-1',
            'getName' => 'Support',
            'getDescription' => 'Wsparcie techniczne',
            'getDefaultResolutionTimeMinutes' => 45,
        ]);

        $categoryTwo = $this->createConfiguredMock(TicketCategoryInterface::class, [
            'getId' => 'cat-2',
            'getName' => 'Billing',
            'getDescription' => null,
            'getDefaultResolutionTimeMinutes' => 60,
        ]);

        $this->ticketCategoryService
            ->expects(self::once())
            ->method('getAllCategories')
            ->willReturn([$categoryOne, $categoryTwo]);

        $client->request('GET', '/api/ticket-categories');

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'categories' => [
                [
                    'id' => 'cat-1',
                    'name' => 'Support',
                    'description' => 'Wsparcie techniczne',
                    'defaultResolutionTimeMinutes' => 45,
                ],
                [
                    'id' => 'cat-2',
                    'name' => 'Billing',
                    'description' => null,
                    'defaultResolutionTimeMinutes' => 60,
                ],
            ],
        ], $data);
    }

    public function testListReturnsEmptyCategories(): void
    {
        $client = $this->createClientWithMocks();

        $this->ticketCategoryService
            ->expects(self::once())
            ->method('getAllCategories')
            ->willReturn([]);

        $client->request('GET', '/api/ticket-categories');

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['categories' => []], $data);
    }

    public function testListReturnsInternalServerErrorWhenServiceFails(): void
    {
        $client = $this->createClientWithMocks();

        $this->ticketCategoryService
            ->expects(self::once())
            ->method('getAllCategories')
            ->willThrowException(new \RuntimeException('Failed to fetch categories'));

        $client->request('GET', '/api/ticket-categories');

        $response = $client->getResponse();
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('Wewnętrzny błąd serwera', $data['message'] ?? null);
    }
}
