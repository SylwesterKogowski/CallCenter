<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\Tickets\Domain\TicketRegisteredTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_registered_time')]
#[ORM\Index(name: 'idx_ticket_registered_time_ticket_id', columns: ['ticket_id'])]
#[ORM\Index(name: 'idx_ticket_registered_time_worker_id', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_ticket_registered_time_started_at', columns: ['started_at'])]
#[ORM\Index(name: 'idx_ticket_registered_time_ended_at', columns: ['ended_at'])]
#[ORM\Index(name: 'idx_ticket_registered_time_active', columns: ['ticket_id', 'worker_id', 'ended_at'])]
class TicketRegisteredTime implements TicketRegisteredTimeInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Ticket::class, inversedBy: 'registeredTimes')]
    #[ORM\JoinColumn(name: 'ticket_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(type: 'guid', name: 'worker_id')]
    private string $workerId;

    #[ORM\Column(type: 'datetime_immutable', name: 'started_at')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'ended_at', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: 'integer', name: 'duration_minutes', nullable: true)]
    private ?int $durationMinutes = null;

    #[ORM\Column(type: 'boolean', name: 'is_phone_call')]
    private bool $isPhoneCall;

    public function __construct(
        string $id,
        Ticket $ticket,
        string $workerId,
        \DateTimeImmutable $startedAt,
        ?bool $isPhoneCall = false,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('Ticket registered time id cannot be empty.');
        }

        if ('' === $workerId) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        $this->id = $id;
        $this->ticket = $ticket;
        $this->workerId = $workerId;
        $this->startedAt = $startedAt;
        $this->isPhoneCall = (bool) $isPhoneCall;
        $ticket->addRegisteredTime($this);
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

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function end(\DateTimeImmutable $endedAt, ?int $durationMinutes = null): void
    {
        if (null !== $this->endedAt) {
            throw new \LogicException('Work session already ended.');
        }

        if ($endedAt < $this->startedAt) {
            throw new \InvalidArgumentException('Ended at cannot be earlier than started at.');
        }

        $this->endedAt = $endedAt;
        $calculated = (int) round(($endedAt->getTimestamp() - $this->startedAt->getTimestamp()) / 60);

        $this->durationMinutes = $durationMinutes ?? max($calculated, 0);
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function isPhoneCall(): bool
    {
        return $this->isPhoneCall;
    }

    public function markAsPhoneCall(): void
    {
        $this->isPhoneCall = true;
    }

    public function isActive(): bool
    {
        return null === $this->endedAt;
    }
}
