<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TicketCategories\Application;

use App\Modules\TicketCategories\Application\TicketCategoryService;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use PHPUnit\Framework\TestCase;

final class TicketCategoryServiceTest extends TestCase
{
    private TicketCategoryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TicketCategoryService();
    }

    public function testGetAllCategoriesReturnsConfiguredList(): void
    {
        $categories = $this->service->getAllCategories();

        self::assertCount(5, $categories);
        self::assertContainsOnlyInstancesOf(TicketCategoryInterface::class, $categories);

        $categoryMap = $this->indexCategoriesById($categories);

        self::assertSame('Sprzedaż', $categoryMap['550e8400-e29b-41d4-a716-446655440001']->getName());
        self::assertSame('Wsparcie techniczne', $categoryMap['550e8400-e29b-41d4-a716-446655440002']->getName());
        self::assertSame('Reklamacje', $categoryMap['550e8400-e29b-41d4-a716-446655440003']->getName());
        self::assertSame('Faktury i płatności', $categoryMap['550e8400-e29b-41d4-a716-446655440004']->getName());
        self::assertSame('Instalacje i serwis', $categoryMap['550e8400-e29b-41d4-a716-446655440005']->getName());

        self::assertSame(30, $categoryMap['550e8400-e29b-41d4-a716-446655440001']->getDefaultResolutionTimeMinutes());
        self::assertSame(45, $categoryMap['550e8400-e29b-41d4-a716-446655440002']->getDefaultResolutionTimeMinutes());
        self::assertSame(60, $categoryMap['550e8400-e29b-41d4-a716-446655440003']->getDefaultResolutionTimeMinutes());
        self::assertSame(20, $categoryMap['550e8400-e29b-41d4-a716-446655440004']->getDefaultResolutionTimeMinutes());
        self::assertSame(90, $categoryMap['550e8400-e29b-41d4-a716-446655440005']->getDefaultResolutionTimeMinutes());
    }

    public function testGetCategoriesByIdsReturnsMatchingCategoriesInOrder(): void
    {
        $ids = [
            '550e8400-e29b-41d4-a716-446655440005',
            '550e8400-e29b-41d4-a716-446655440001',
            'unknown-id',
            '550e8400-e29b-41d4-a716-446655440001', // duplicate should be ignored
            '550e8400-e29b-41d4-a716-446655440004',
        ];

        $categories = $this->service->getCategoriesByIds($ids);

        self::assertCount(3, $categories);
        self::assertSame([
            '550e8400-e29b-41d4-a716-446655440005',
            '550e8400-e29b-41d4-a716-446655440001',
            '550e8400-e29b-41d4-a716-446655440004',
        ], array_map(static fn (TicketCategoryInterface $category): string => $category->getId(), $categories));
    }

    /**
     * @param TicketCategoryInterface[] $categories
     *
     * @return array<string, TicketCategoryInterface>
     */
    private function indexCategoriesById(array $categories): array
    {
        $indexed = [];

        foreach ($categories as $category) {
            $indexed[$category->getId()] = $category;
        }

        return $indexed;
    }
}
