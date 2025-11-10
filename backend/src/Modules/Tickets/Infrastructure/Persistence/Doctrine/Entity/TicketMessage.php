<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\Tickets\Domain\TicketMessageInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_messages')]
#[ORM\Index(name: 'idx_ticket_message_ticket_id', columns: ['ticket_id'])]
#[ORM\Index(name: 'idx_ticket_message_created_at', columns: ['created_at'])]
class TicketMessage implements TicketMessageInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Ticket::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'ticket_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(type: 'string', length: 50, name: 'sender_type')]
    private string $senderType;

    #[ORM\Column(type: 'guid', name: 'sender_id', nullable: true)]
    private ?string $senderId = null;

    #[ORM\Column(type: 'string', length: 255, name: 'sender_name', nullable: true)]
    private ?string $senderName = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $status = null;

    public function __construct(
        string $id,
        Ticket $ticket,
        string $content,
        string $senderType,
        ?string $senderId = null,
        ?string $senderName = null,
        ?string $status = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('Ticket message id cannot be empty.');
        }

        $this->id = $id;
        $this->ticket = $ticket;
        $this->content = $content;
        $this->senderType = $senderType;
        $this->senderId = $senderId;
        $this->senderName = $senderName;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $ticket->addMessage($this);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getTicketId(): string
    {
        return $this->ticket->getId();
    }

    public function getSenderType(): string
    {
        return $this->senderType;
    }

    public function getSenderId(): ?string
    {
        return $this->senderId;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
