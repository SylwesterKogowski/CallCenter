<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Phone\Service;

interface WorkerPhoneServiceInterface
{
    /**
     * Rozpoczyna kontekst rozmowy telefonicznej dla zalogowanego pracownika.
     *
     * Oczekuje zakończenia wszystkich aktywnych wpisów czasu pracy (TicketRegisteredTime),
     * ustawienia ich statusów na "waiting" oraz przygotowania nowego kontekstu połączenia.
     *
     * @return array{
     *     callId: string,
     *     startTime: \DateTimeImmutable,
     *     pausedTickets: list<array{
     *         ticketId: string,
     *         previousStatus: string,
     *         newStatus: string
     *     }>
     * }
     */
    public function startCall(string $workerId): array;

    /**
     * Kończy kontekst rozmowy telefonicznej i zwraca zarejestrowane dane.
     *
     * Implementacja powinna:
     * - zarejestrować czas rozmowy dla wskazanego ticketa (jeśli został wybrany),
     * - dodać ticket do grafika bieżącego dnia wraz z ustawieniem statusu "in_progress",
     * - przywrócić poprzedni aktywny ticket w razie braku nowego ticketa,
     * - zapisać notatki przekazane w trakcie rozmowy.
     *
     * @return array{
     *     call: array{
     *         id: string,
     *         ticketId: string|null,
     *         duration: int,
     *         startTime: \DateTimeImmutable,
     *         endTime: \DateTimeImmutable
     *     },
     *     ticket?: array{
     *         id: string,
     *         status: string,
     *         timeSpent: int,
     *         scheduledDate?: \DateTimeImmutable,
     *         updatedAt: \DateTimeImmutable
     *     },
     *     previousTicket?: array{
     *         id: string,
     *         status: string,
     *         updatedAt: \DateTimeImmutable
     *     }
     * }
     */
    public function endCall(
        string $workerId,
        string $callId,
        ?string $ticketId,
        int $duration,
        ?string $notes,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
    ): array;
}
