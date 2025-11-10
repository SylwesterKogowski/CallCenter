<?php

declare(strict_types=1);

namespace App\Modules\TicketCategories\Application;

use App\Modules\TicketCategories\Domain\TicketCategory;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;

final class TicketCategoryService implements TicketCategoryServiceInterface
{
    /**
     * @var array<string, TicketCategoryInterface>
     */
    private array $categoriesById;

    public function __construct()
    {
        $this->categoriesById = [];

        foreach (self::getCategoryDefinitions() as $definition) {
            $category = new TicketCategory(
                $definition['id'],
                $definition['name'],
                $definition['description'],
                $definition['defaultResolutionTimeMinutes'],
            );

            $this->categoriesById[$category->getId()] = $category;
        }
    }

    /**
     * @return TicketCategoryInterface[]
     */
    public function getAllCategories(): array
    {
        return array_values($this->categoriesById);
    }

    /**
     * @param string[] $categoryIds
     *
     * @return TicketCategoryInterface[]
     */
    public function getCategoriesByIds(array $categoryIds): array
    {
        $selected = [];

        foreach ($categoryIds as $categoryId) {
            if (!isset($this->categoriesById[$categoryId])) {
                continue;
            }

            if (!isset($selected[$categoryId])) {
                $selected[$categoryId] = $this->categoriesById[$categoryId];
            }
        }

        return array_values($selected);
    }

    /**
     * @return array<int, array{id: string, name: string, description: ?string, defaultResolutionTimeMinutes: int}>
     */
    private static function getCategoryDefinitions(): array
    {
        return [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440001',
                'name' => 'Sprzedaż',
                'description' => 'Kategoria dla ticketów związanych ze sprzedażą produktów i usług',
                'defaultResolutionTimeMinutes' => 30,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440002',
                'name' => 'Wsparcie techniczne',
                'description' => 'Kategoria dla ticketów związanych z problemami technicznymi i wsparciem',
                'defaultResolutionTimeMinutes' => 45,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440003',
                'name' => 'Reklamacje',
                'description' => 'Kategoria dla ticketów związanych z reklamacjami klientów',
                'defaultResolutionTimeMinutes' => 60,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440004',
                'name' => 'Faktury i płatności',
                'description' => 'Kategoria dla ticketów związanych z fakturami i płatnościami',
                'defaultResolutionTimeMinutes' => 20,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440005',
                'name' => 'Instalacje i serwis',
                'description' => 'Kategoria dla ticketów związanych z instalacjami i serwisem',
                'defaultResolutionTimeMinutes' => 90,
            ],
        ];
    }
}
