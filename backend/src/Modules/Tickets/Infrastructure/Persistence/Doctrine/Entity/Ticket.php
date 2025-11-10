<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\Exception\InvalidTicketStatusException;
use App\Modules\Tickets\Domain\Exception\TicketAlreadyClosedException;
use App\Modules\Tickets\Domain\TicketInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tickets')]
#[ORM\Index(name: 'idx_ticket_client_id', columns: ['client_id'])]
#[ORM\Index(name: 'idx_ticket_category_id', columns: ['category_id'])]
#[ORM\Index(name: 'idx_ticket_status', columns: ['status'])]
#[ORM\Index(name: 'idx_ticket_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_ticket_closed_at', columns: ['closed_at'])]
class Ticket implements TicketInterface
{
    private const ALLOWED_STATUSES = [
        self::STATUS_CLOSED,
        self::STATUS_AWAITING_RESPONSE,
        self::STATUS_AWAITING_CUSTOMER,
        self::STATUS_IN_PROGRESS,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'guid', name: 'client_id')]
    private string $clientId;

    #[ORM\Column(type: 'string', length: 255, name: 'client_email', nullable: true)]
    private ?string $clientEmail = null;

    #[ORM\Column(type: 'string', length: 32, name: 'client_phone', nullable: true)]
    private ?string $clientPhone = null;

    #[ORM\Column(type: 'string', length: 255, name: 'client_first_name', nullable: true)]
    private ?string $clientFirstName = null;

    #[ORM\Column(type: 'string', length: 255, name: 'client_last_name', nullable: true)]
    private ?string $clientLastName = null;

    #[ORM\Column(type: 'string', length: 255, name: 'category_id')]
    private string $categoryId;

    #[ORM\Column(type: 'string', length: 255, name: 'category_name')]
    private string $categoryName;

    #[ORM\Column(type: 'text', name: 'category_description', nullable: true)]
    private ?string $categoryDescription = null;

    #[ORM\Column(type: 'integer', name: 'category_default_resolution_minutes')]
    private int $categoryDefaultResolutionMinutes;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'closed_at', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: 'guid', name: 'closed_by_id', nullable: true)]
    private ?string $closedById = null;

    /** @var Collection<int, TicketMessage> */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: TicketMessage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    /** @var Collection<int, TicketNote> */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: TicketNote::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $notes;

    /** @var Collection<int, TicketRegisteredTime> */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: TicketRegisteredTime::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startedAt' => 'ASC'])]
    private Collection $registeredTimes;

    public function __construct(
        string $id,
        ClientInterface $client,
        TicketCategoryInterface $category,
        string $status,
        ?string $title = null,
        ?string $description = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('Ticket id cannot be empty.');
        }

        $this->id = $id;
        $this->applyClientSnapshot($client);
        $this->applyCategorySnapshot($category);
        $this->assertValidStatus($status);
        $this->status = $status;
        $this->title = $title;
        $this->description = $description;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->registeredTimes = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClient(): ClientInterface
    {
        return new TicketClientSnapshot(
            $this->clientId,
            $this->clientEmail,
            $this->clientPhone,
            $this->clientFirstName,
            $this->clientLastName,
        );
    }

    public function getCategory(): TicketCategoryInterface
    {
        return new TicketCategorySnapshot(
            $this->categoryId,
            $this->categoryName,
            $this->categoryDescription,
            $this->categoryDefaultResolutionMinutes,
        );
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function changeStatus(string $status): void
    {
        $this->assertValidStatus($status);

        if ($this->isClosed() && self::STATUS_CLOSED !== $status) {
            throw TicketAlreadyClosedException::create();
        }

        if ($this->status === $status) {
            return;
        }

        if (self::STATUS_CLOSED === $status) {
            throw TicketAlreadyClosedException::create();
        }

        $this->status = $status;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getClosedByWorkerId(): ?string
    {
        return $this->closedById;
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(TicketMessage $message): void
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
        }
    }

    /**
     * @return Collection<int, TicketNote>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(TicketNote $note): void
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
        }
    }

    /**
     * @return Collection<int, TicketRegisteredTime>
     */
    public function getRegisteredTimes(): Collection
    {
        return $this->registeredTimes;
    }

    public function close(WorkerInterface $worker, ?\DateTimeImmutable $closedAt = null): void
    {
        if ($this->isClosed()) {
            throw TicketAlreadyClosedException::create();
        }

        $this->assertNoActiveRegisteredTimes();

        $this->status = self::STATUS_CLOSED;
        $this->closedAt = $closedAt ?? new \DateTimeImmutable();
        $this->closedById = $worker->getId();

        $this->touch();
    }

    public function isClosed(): bool
    {
        return self::STATUS_CLOSED === $this->status;
    }

    public function isInProgress(): bool
    {
        return self::STATUS_IN_PROGRESS === $this->status;
    }

    public function isAwaitingResponse(): bool
    {
        return self::STATUS_AWAITING_RESPONSE === $this->status;
    }

    public function isAwaitingCustomer(): bool
    {
        return self::STATUS_AWAITING_CUSTOMER === $this->status;
    }

    public function updateDescription(?string $description): void
    {
        if ($this->description === $description) {
            return;
        }

        $this->description = $description;
        $this->touch();
    }

    public function updateTitle(?string $title): void
    {
        if ($this->title === $title) {
            return;
        }

        $this->title = $title;
        $this->touch();
    }

    public function addRegisteredTime(TicketRegisteredTime $registeredTime): void
    {
        if (!$this->registeredTimes->contains($registeredTime)) {
            $this->registeredTimes->add($registeredTime);
        }
    }

    public function updateClient(ClientInterface $client): void
    {
        $this->applyClientSnapshot($client);
        $this->touch();
    }

    public function updateCategory(TicketCategoryInterface $category): void
    {
        $this->applyCategorySnapshot($category);
        $this->touch();
    }

    private function applyClientSnapshot(ClientInterface $client): void
    {
        $this->clientId = $client->getId();
        $this->clientEmail = $client->getEmail();
        $this->clientPhone = $client->getPhone();
        $this->clientFirstName = $client->getFirstName();
        $this->clientLastName = $client->getLastName();
    }

    private function applyCategorySnapshot(TicketCategoryInterface $category): void
    {
        $this->categoryId = $category->getId();
        $this->categoryName = $category->getName();
        $this->categoryDescription = $category->getDescription();
        $this->categoryDefaultResolutionMinutes = $category->getDefaultResolutionTimeMinutes();
    }

    private function assertValidStatus(string $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw InvalidTicketStatusException::forStatus($status);
        }
    }

    private function assertNoActiveRegisteredTimes(): void
    {
        foreach ($this->registeredTimes as $registeredTime) {
            if ($registeredTime->isActive()) {
                throw new \LogicException('Cannot close ticket with active registered time entries.');
            }
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
