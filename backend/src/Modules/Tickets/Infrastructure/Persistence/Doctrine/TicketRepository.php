<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine;

use App\Modules\Authorization\Infrastructure\Persistence\Doctrine\Entity\WorkerCategoryAssignment;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;
use App\Modules\Tickets\Domain\TicketRegisteredTimeInterface;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\Ticket;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketMessage;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketNote;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketRegisteredTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class TicketRepository implements TicketRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(string $id): ?TicketInterface
    {
        $ticket = $this->entityManager->find(Ticket::class, $id);

        if (!$ticket instanceof Ticket) {
            return null;
        }

        return $ticket;
    }

    public function save(TicketInterface $ticket): void
    {
        $entity = $this->assertTicketEntity($ticket);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(TicketInterface $ticket): void
    {
        $this->assertTicketEntity($ticket);
        $this->entityManager->flush();
    }

    public function findTicketMessages(string $ticketId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('m')
            ->from(TicketMessage::class, 'm')
            ->where('IDENTITY(m.ticket) = :ticketId')
            ->setParameter('ticketId', $ticketId)
            ->orderBy('m.createdAt', 'ASC');

        /** @var TicketMessageInterface[] $messages */
        $messages = $qb->getQuery()->getResult();

        return $messages;
    }

    public function addMessage(TicketMessageInterface $message): void
    {
        $entity = $this->assertMessageEntity($message);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findTicketNotes(string $ticketId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('n')
            ->from(TicketNote::class, 'n')
            ->where('IDENTITY(n.ticket) = :ticketId')
            ->setParameter('ticketId', $ticketId)
            ->orderBy('n.createdAt', 'ASC');

        /** @var TicketNoteInterface[] $notes */
        $notes = $qb->getQuery()->getResult();

        return $notes;
    }

    public function addNote(TicketNoteInterface $note): void
    {
        $entity = $this->assertNoteEntity($note);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findTicketRegisteredTimes(string $ticketId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('rt')
            ->from(TicketRegisteredTime::class, 'rt')
            ->where('IDENTITY(rt.ticket) = :ticketId')
            ->setParameter('ticketId', $ticketId)
            ->orderBy('rt.startedAt', 'ASC');

        /** @var TicketRegisteredTimeInterface[] $times */
        $times = $qb->getQuery()->getResult();

        return $times;
    }

    public function addRegisteredTime(TicketRegisteredTimeInterface $registeredTime): void
    {
        $entity = $this->assertRegisteredTimeEntity($registeredTime);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function updateRegisteredTime(TicketRegisteredTimeInterface $registeredTime): void
    {
        $this->assertRegisteredTimeEntity($registeredTime);
        $this->entityManager->flush();
    }

    public function findActiveRegisteredTime(string $ticketId, string $workerId): ?TicketRegisteredTimeInterface
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('rt')
            ->from(TicketRegisteredTime::class, 'rt')
            ->where('IDENTITY(rt.ticket) = :ticketId')
            ->andWhere('rt.workerId = :workerId')
            ->andWhere('rt.endedAt IS NULL')
            ->setParameter('ticketId', $ticketId)
            ->setParameter('workerId', $workerId)
            ->setMaxResults(1);

        /** @var TicketRegisteredTimeInterface|null $result */
        $result = $qb->getQuery()->getOneOrNullResult();

        return $result;
    }

    public function getWorkerTimeSpentOnTicket(string $ticketId, string $workerId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('COALESCE(SUM(rt.durationMinutes), 0)')
            ->from(TicketRegisteredTime::class, 'rt')
            ->where('IDENTITY(rt.ticket) = :ticketId')
            ->andWhere('rt.workerId = :workerId')
            ->setParameter('ticketId', $ticketId)
            ->setParameter('workerId', $workerId);

        try {
            $total = (int) $qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException) {
            $total = 0;
        }

        return $total;
    }

    public function searchWorkerTickets(string $workerId, array $filters, int $limit, int $offset): array
    {
        $baseQb = $this->entityManager->createQueryBuilder();
        $baseQb
            ->from(Ticket::class, 't')
            ->leftJoin('t.registeredTimes', 'rt')
            ->leftJoin(
                WorkerCategoryAssignment::class,
                'wca',
                'WITH',
                'wca.categoryId = t.categoryId AND wca.workerId = :workerId',
            )
            ->andWhere(
                $baseQb->expr()->orX(
                    'rt.workerId = :workerId',
                    'wca.workerId IS NOT NULL',
                ),
            )
            ->setParameter('workerId', $workerId);

        $this->applySearchFilters($baseQb, $filters);

        $countQb = clone $baseQb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $baseQb
            ->select('DISTINCT t')
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        /** @var TicketInterface[] $tickets */
        $tickets = $baseQb->getQuery()->getResult();

        return [
            'tickets' => $tickets,
            'total' => $total,
        ];
    }

    public function getWorkerBacklog(string $workerId, array $filters): array
    {
        $statuses = $filters['statuses'] ?? ['awaiting_response', 'awaiting_customer', 'in_progress'];

        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('DISTINCT t')
            ->from(Ticket::class, 't')
            ->innerJoin(
                WorkerCategoryAssignment::class,
                'wca',
                'WITH',
                'wca.categoryId = t.categoryId AND wca.workerId = :workerId',
            )
            ->andWhere('t.status IN (:statuses)')
            ->orderBy('t.createdAt', 'ASC')
            ->setParameter('workerId', $workerId)
            ->setParameter('statuses', $statuses);

        if (!empty($filters['category_ids'])) {
            $qb
                ->andWhere('t.categoryId IN (:categoryIds)')
                ->setParameter('categoryIds', $filters['category_ids']);
        }

        /** @var TicketInterface[] $tickets */
        $tickets = $qb->getQuery()->getResult();

        return $tickets;
    }

    /**
     * @param array{
     *     status?: string|null,
     *     category_id?: string|null,
     *     query?: string|null
     * } $filters
     */
    private function applySearchFilters(\Doctrine\ORM\QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['status'])) {
            $qb
                ->andWhere('t.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['category_id'])) {
            $qb
                ->andWhere('t.categoryId = :categoryId')
                ->setParameter('categoryId', $filters['category_id']);
        }

        if (!empty($filters['query'])) {
            $qb
                ->andWhere(
                    $qb->expr()->orX(
                        'LOWER(t.title) LIKE :query',
                        'LOWER(t.description) LIKE :query',
                    ),
                )
                ->setParameter('query', '%'.mb_strtolower($filters['query']).'%');
        }
    }

    private function assertTicketEntity(TicketInterface $ticket): Ticket
    {
        if (!$ticket instanceof Ticket) {
            throw new \InvalidArgumentException(sprintf('Ticket must be instance of %s, got %s.', Ticket::class, $ticket::class));
        }

        return $ticket;
    }

    private function assertMessageEntity(TicketMessageInterface $message): TicketMessage
    {
        if (!$message instanceof TicketMessage) {
            throw new \InvalidArgumentException(sprintf('Ticket message must be instance of %s, got %s.', TicketMessage::class, $message::class));
        }

        return $message;
    }

    private function assertNoteEntity(TicketNoteInterface $note): TicketNote
    {
        if (!$note instanceof TicketNote) {
            throw new \InvalidArgumentException(sprintf('Ticket note must be instance of %s, got %s.', TicketNote::class, $note::class));
        }

        return $note;
    }

    private function assertRegisteredTimeEntity(TicketRegisteredTimeInterface $registeredTime): TicketRegisteredTime
    {
        if (!$registeredTime instanceof TicketRegisteredTime) {
            throw new \InvalidArgumentException(sprintf('Ticket registered time must be instance of %s, got %s.', TicketRegisteredTime::class, $registeredTime::class));
        }

        return $registeredTime;
    }
}
