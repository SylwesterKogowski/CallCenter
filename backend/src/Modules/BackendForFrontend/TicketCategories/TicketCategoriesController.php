<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\TicketCategories;

use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/api/ticket-categories', name: 'backend_for_frontend_ticket_categories_')]
class TicketCategoriesController extends AbstractJsonController
{
    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private TicketCategoryServiceInterface $ticketCategoryService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function list(): JsonResponse
    {
        return $this->execute(function () {
            $categories = $this->ticketCategoryService->getAllCategories();

            return [
                'categories' => array_map(
                    static fn ($category): array => [
                        'id' => $category->getId(),
                        'name' => $category->getName(),
                        'description' => $category->getDescription(),
                        'defaultResolutionTimeMinutes' => $category->getDefaultResolutionTimeMinutes(),
                    ],
                    $categories,
                ),
            ];
        });
    }
}

