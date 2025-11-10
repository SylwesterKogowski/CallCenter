<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Public\Tickets\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTicketRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Identyfikator kategorii jest wymagany')]
        public string $categoryId,
        #[Assert\Valid]
        public CreateTicketClientDto $client,
        #[Assert\Length(max: 255)]
        public ?string $title = null,
        #[Assert\Length(max: 2000)]
        public ?string $description = null,
    ) {
        $this->categoryId = trim($categoryId);
        $this->title = null !== $title ? trim($title) : null;
        $this->description = null !== $description ? trim($description) : null;
    }
}
