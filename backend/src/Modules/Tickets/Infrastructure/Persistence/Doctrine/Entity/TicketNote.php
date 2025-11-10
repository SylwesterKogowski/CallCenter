<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\Tickets\Domain\TicketNoteInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_notes')]
#[ORM\Index(name: 'idx_ticket_note_ticket_id', columns: ['ticket_id'])]
#[ORM\Index(name: 'idx_ticket_note_worker_id', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_ticket_note_created_at', columns: ['created_at'])]
class TicketNote implements TicketNoteInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Ticket::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(name: 'ticket_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(type: 'guid', name: 'worker_id')]
    private string $workerId;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        Ticket $ticket,
        string $workerId,
        string $content,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('Ticket note id cannot be empty.');
        }

        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        $this->id = $id;
        $this->ticket = $ticket;
        $this->workerId = $workerId;
        $this->content = $content;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $ticket->addNote($this);
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

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
