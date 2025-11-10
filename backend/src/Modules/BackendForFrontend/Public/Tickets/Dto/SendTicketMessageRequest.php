<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Public\Tickets\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SendTicketMessageRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Treść wiadomości jest wymagana')]
        #[Assert\Length(max: 2000)]
        public string $content,
    ) {
        $this->content = trim($content);
    }
}
