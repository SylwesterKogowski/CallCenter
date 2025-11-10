# Moduł grafika pracownika

## Opis modułu

Moduł grafika pracownika jest **modułem serwisowym (fasadą)**, który odpowiada za zarządzanie planowanym przypisaniem ticketów do pracowników w określonych dniach. Zajmuje się:

1. **Planowaniem przypisań ticketów** - przypisywanie ticketów do pracowników na konkretne dni w grafiku
2. **Automatycznym przypisywaniem ticketów** - inteligentne przypisywanie ticketów do pracowników na podstawie ich efektywności, dostępności i domyślnego czasu rozwiązania
3. **Zarządzaniem grafikiem pracownika** - przeglądanie, dodawanie i usuwanie przypisań w grafiku pracownika
4. **Obliczaniem przewidywanej ilości ticketów** - kalkulacja, ile ticketów pracownik może obsłużyć danego dnia na podstawie dostępności i efektywności
5. **Walidacją przypisań** - sprawdzanie, czy pracownik jest dostępny w danym dniu przed przypisaniem mu ticketa
6. **Zarządzaniem priorytetami** - możliwość ręcznego przypisania ticketów z pominięciem automatycznego algorytmu

Grafik pracownika pokazuje zaplanowane przypisania ticketów na najbliższe dni (np. 7 dni). Pracownik widzi przypisane tickety i czas na nich spędzony dzisiaj. Moduł umożliwia zarówno ręczne, jak i automatyczne przypisywanie ticketów do pracowników na podstawie ich dostępności, efektywności i domyślnego czasu rozwiązania z kategorii.

**Uwaga:** Moduł WorkerSchedule nie zawiera endpointów API. Endpointy HTTP są zaimplementowane w module **BackendForFrontend**, który korzysta z serwisów udostępnianych przez ten moduł.

## Warstwa serwisowa (Fasada)

Moduł udostępnia następujące serwisy, które mogą być używane przez inne moduły (w szczególności przez BackendForFrontend):

### WorkerScheduleService
Główny serwis grafika pracownika, udostępniający metody:

- `assignTicketToWorker(Ticket $ticket, Worker $worker, Carbon $date): WorkerSchedule` - ręczne przypisanie ticketa do pracownika na konkretny dzień
- `getScheduleById(string $id): ?WorkerSchedule` - pobranie przypisania po ID
- `getWorkerScheduleForDay(Worker $worker, Carbon $date): array` - pobranie wszystkich przypisań pracownika na konkretny dzień (zwraca tablicę WorkerSchedule)
- `getWorkerScheduleForPeriod(Worker $worker, Carbon $startDate, Carbon $endDate): array` - pobranie wszystkich przypisań pracownika w określonym okresie (zwraca tablicę WorkerSchedule)
- `getWorkerScheduleForWeek(Worker $worker, Carbon $weekStartDate): array` - pobranie wszystkich przypisań pracownika na tydzień (7 dni od podanej daty)
- `removeScheduleAssignment(WorkerSchedule $schedule): void` - usunięcie przypisania ticketa z grafika
- `removeTicketFromSchedule(Ticket $ticket, Worker $worker, Carbon $date): void` - usunięcie konkretnego ticketa z grafika pracownika w danym dniu
- `getTicketsForWorkerDay(Worker $worker, Carbon $date): array` - pobranie listy ticketów przypisanych do pracownika w danym dniu
- `calculatePredictedTicketCount(Worker $worker, Carbon $date, TicketCategory $category): int` - obliczenie przewidywanej ilości ticketów, które pracownik może obsłużyć danego dnia w danej kategorii (na podstawie dostępności i efektywności)
- `autoAssignTicketsToWorker(Worker $worker, Carbon $date, array $tickets, ?TicketCategory $category = null): array` - automatyczne przypisanie listy ticketów do pracownika na dany dzień (zwraca tablicę WorkerSchedule)
- `autoAssignTicketsToAllWorkers(Carbon $startDate, Carbon $endDate, ?TicketCategory $category = null): array` - automatyczne przypisanie ticketów do wszystkich dostępnych pracowników w okresie (zwraca tablicę WorkerSchedule)
- `getAvailableTicketsForCategory(TicketCategory $category, ?string $status = null): array` - pobranie dostępnych ticketów z danej kategorii do przypisania (opcjonalnie filtrowane po statusie)
- `canWorkerHandleTicketOnDate(Worker $worker, Ticket $ticket, Carbon $date): bool` - sprawdzenie, czy pracownik może obsłużyć ticket w danym dniu (sprawdza dostępność i uprawnienia do kategorii)
- `getWorkerScheduleStatistics(Worker $worker, Carbon $date): array` - pobranie statystyk grafika pracownika na dany dzień (ilość przypisanych ticketów, przewidywany czas pracy, itp.)
- `reassignTicket(Ticket $ticket, Worker $fromWorker, Worker $toWorker, Carbon $date): WorkerSchedule` - przeniesienie ticketa z jednego pracownika na drugiego w danym dniu
- `getScheduleByTicketAndDate(Ticket $ticket, Carbon $date): ?WorkerSchedule` - pobranie przypisania ticketa w danym dniu (jeśli istnieje)

## Domenowa warstwa (Entities)

### WorkerSchedule (Grafik pracownika)
Główna encja reprezentująca przypisanie ticketa do pracownika w danym dniu.

**Pola:**
- `id` (uuid, primary key) - unikalny identyfikator przypisania
- `worker` (Worker, ManyToOne, not null) - pracownik, do którego przypisany jest ticket
- `ticket` (Ticket, ManyToOne, not null) - ticket przypisany do pracownika
- `scheduledDate` (Carbon, not null) - data, na którą zaplanowano przypisanie (tylko data, bez czasu)
- `assignedAt` (Carbon, not null) - data i czas utworzenia przypisania
- `assignedBy` (Worker, ManyToOne, nullable) - pracownik, który dokonał przypisania (opcjonalne, dla audytu, null jeśli automatyczne)
- `isAutoAssigned` (bool, not null, default: false) - flaga określająca, czy przypisanie było automatyczne
- `priority` (int, nullable, default: null) - priorytet przypisania (wyższa wartość = wyższy priorytet, null = domyślny)

**Metody domenowe:**
- `assign(Worker $worker, Ticket $ticket, Carbon $date, ?Worker $assignedBy = null, bool $isAutoAssigned = false): void` - przypisanie ticketa do pracownika
- `reassign(Worker $newWorker, Carbon $newDate): void` - przeniesienie przypisania do innego pracownika lub dnia
- `remove(): void` - usunięcie przypisania
- `isOnDate(Carbon $date): bool` - sprawdzenie, czy przypisanie jest w danym dniu
- `isAutoAssigned(): bool` - sprawdzenie, czy przypisanie było automatyczne
- `setPriority(?int $priority): void` - ustawienie priorytetu przypisania
- `getPriority(): ?int` - pobranie priorytetu przypisania

**Relacje:**
- ManyToOne z Worker (pracownik z modułu Authentication)
- ManyToOne z Ticket (ticket z modułu Tickets)
- ManyToOne z Worker (pracownik, który dokonał przypisania)

**Reguły biznesowe:**
- Pracownik musi być dostępny w danym dniu (sprawdzane przez moduł WorkerAvailability)
- Pracownik musi mieć uprawnienia do kategorii ticketa (sprawdzane przez moduł Authorization)
- Jeden ticket może być przypisany do wielu pracowników w różnych dniach (ale nie do tego samego pracownika w tym samym dniu)
- Jeden ticket może być przypisany do tego samego pracownika tylko raz w danym dniu
- Data przypisania nie może być w przeszłości (tylko dzisiaj lub przyszłe daty)
- Automatyczne przypisanie może być nadpisane ręcznym przypisaniem
- Priorytet przypisania wpływa na kolejność wyświetlania i przetwarzania

## Tabele bazy danych

### `worker_schedule`
Tabela przechowująca przypisania ticketów do pracowników w grafiku.

**Kolumny:**
- `id` UUID NOT NULL PRIMARY KEY
- `worker_id` UUID NOT NULL - identyfikator pracownika (FK do `workers.id`)
- `ticket_id` UUID NOT NULL - identyfikator ticketa (FK do `tickets.id`)
- `scheduled_date` DATE NOT NULL - data, na którą zaplanowano przypisanie (tylko data, bez czasu)
- `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP - data i czas utworzenia przypisania
- `assigned_by_id` UUID NULL - identyfikator pracownika, który dokonał przypisania (FK do `workers.id`, opcjonalne, null jeśli automatyczne)
- `is_auto_assigned` BOOLEAN NOT NULL DEFAULT FALSE - flaga określająca, czy przypisanie było automatyczne
- `priority` INT NULL - priorytet przypisania (wyższa wartość = wyższy priorytet, null = domyślny)

**Indeksy:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_worker_ticket_date` (`worker_id`, `ticket_id`, `scheduled_date`) - jeden ticket może być przypisany do tego samego pracownika tylko raz w danym dniu
- INDEX `idx_worker_id` (`worker_id`) - dla szybkiego wyszukiwania przypisań pracownika
- INDEX `idx_ticket_id` (`ticket_id`) - dla szybkiego wyszukiwania przypisań ticketa
- INDEX `idx_scheduled_date` (`scheduled_date`) - dla szybkiego wyszukiwania przypisań w danym dniu
- INDEX `idx_worker_date` (`worker_id`, `scheduled_date`) - dla szybkiego wyszukiwania grafika pracownika na dzień
- INDEX `idx_ticket_date` (`ticket_id`, `scheduled_date`) - dla szybkiego wyszukiwania przypisań ticketa w danym dniu
- INDEX `idx_auto_assigned` (`is_auto_assigned`) - dla filtrowania automatycznych przypisań
- INDEX `idx_priority` (`priority`) - dla sortowania po priorytecie
- FOREIGN KEY `fk_worker_schedule_worker` (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
- FOREIGN KEY `fk_worker_schedule_ticket` (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
- FOREIGN KEY `fk_worker_schedule_assigned_by` (`assigned_by_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL

**Przykładowe dane:**
```sql
-- Ręczne przypisanie ticketa do pracownika na poniedziałek
INSERT INTO worker_schedule (id, worker_id, ticket_id, scheduled_date, assigned_at, assigned_by_id, is_auto_assigned, priority) VALUES 
('550e8400-e29b-41d4-a716-446655440100', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '550e8400-e29b-41d4-a716-446655440010', '2024-01-15', NOW(), 'a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', FALSE, NULL);

-- Automatyczne przypisanie ticketa do pracownika na wtorek
INSERT INTO worker_schedule (id, worker_id, ticket_id, scheduled_date, assigned_at, is_auto_assigned, priority) VALUES 
('550e8400-e29b-41d4-a716-446655440101', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '550e8400-e29b-41d4-a716-446655440011', '2024-01-16', NOW(), TRUE, NULL);

-- Przypisanie z priorytetem
INSERT INTO worker_schedule (id, worker_id, ticket_id, scheduled_date, assigned_at, assigned_by_id, is_auto_assigned, priority) VALUES 
('550e8400-e29b-41d4-a716-446655440102', 'a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', '550e8400-e29b-41d4-a716-446655440012', '2024-01-17', NOW(), 'a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', FALSE, 10);

-- Inny pracownik, ten sam ticket, inny dzień
INSERT INTO worker_schedule (id, worker_id, ticket_id, scheduled_date, assigned_at, is_auto_assigned, priority) VALUES 
('550e8400-e29b-41d4-a716-446655440103', 'a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', '550e8400-e29b-41d4-a716-446655440010', '2024-01-18', NOW(), TRUE, NULL);
```

## Uwagi implementacyjne

1. **Relacja z modułem BackendForFrontend:**
   - Moduł WorkerSchedule udostępnia serwisy (fasadę), które są używane przez moduł BackendForFrontend
   - Wszystkie endpointy HTTP są zaimplementowane w module BackendForFrontend, który wywołuje metody serwisu WorkerScheduleService
   - Moduł BackendForFrontend odpowiada za walidację żądań, autentykację użytkownika i formatowanie odpowiedzi

2. **Relacja z modułem Authentication:**
   - Moduł WorkerSchedule korzysta z encji `Worker` z modułu Authentication
   - Worker musi istnieć w systemie przed przypisaniem mu ticketów w grafiku

3. **Relacja z modułem Tickets:**
   - Moduł WorkerSchedule korzysta z encji `Ticket` z modułu Tickets
   - Ticket musi istnieć w systemie przed przypisaniem go do grafika
   - Moduł WorkerSchedule używa efektywności pracownika z modułu Tickets do automatycznego przypisywania ticketów
   - Po zakończeniu połączenia telefonicznego, nowy/wybrany ticket jest automatycznie dodawany do grafika bieżącego dnia

4. **Relacja z modułem WorkerAvailability:**
   - Moduł WorkerSchedule sprawdza dostępność pracownika przed przypisaniem mu ticketa
   - Pracownik musi być dostępny w danym dniu, aby móc przypisać mu ticket
   - Zmiana dostępności pracownika może wymagać aktualizacji przypisań w grafiku
   - Moduł WorkerSchedule używa danych dostępności do obliczania przewidywanej ilości ticketów

5. **Relacja z modułem Authorization:**
   - Moduł WorkerSchedule sprawdza uprawnienia pracownika do kategorii przed przypisaniem mu ticketa
   - Pracownik może mieć przypisane tylko tickety z kategorii, do których ma dostęp
   - Managerzy mogą przypisywać tickety do wszystkich pracowników

6. **Relacja z modułem TicketCategories:**
   - Moduł WorkerSchedule używa domyślnego czasu rozwiązania z kategorii do obliczania przewidywanej ilości ticketów
   - Przewidywana ilość ticketów = (dostępny czas w minutach) / (domyślny czas rozwiązania * efektywność pracownika)

7. **Automatyczne przypisywanie ticketów:**
   - Algorytm automatycznego przypisywania uwzględnia:
     - Dostępność pracownika w danym dniu (moduł WorkerAvailability)
     - Efektywność pracownika na danej kategorii (moduł Tickets)
     - Domyślny czas rozwiązania z kategorii (moduł TicketCategories)
     - Uprawnienia pracownika do kategorii (moduł Authorization)
     - Obecne obciążenie pracownika (ilość już przypisanych ticketów)
   - Automatyczne przypisanie może być nadpisane ręcznym przypisaniem
   - Automatyczne przypisanie może być uruchomione dla wszystkich pracowników i kolejek (moduł monitoringu)

8. **Obliczanie przewidywanej ilości ticketów:**
   - Przewidywana ilość ticketów = (dostępny czas w minutach) / (domyślny czas rozwiązania * efektywność pracownika)
   - Dostępny czas = suma dostępności pracownika w danym dniu (z modułu WorkerAvailability)
   - Domyślny czas rozwiązania = wartość z kategorii (moduł TicketCategories)
   - Efektywność pracownika = stosunek czasu rzeczywistego do czasu domyślnego (moduł Tickets)
   - Jeśli efektywność > 1, pracownik pracuje szybciej (może obsłużyć więcej ticketów)
   - Jeśli efektywność < 1, pracownik pracuje wolniej (może obsłużyć mniej ticketów)

9. **Ręczne przypisywanie ticketów:**
   - Pracownicy i managerzy mogą ręcznie przypisywać tickety do pracowników na konkretne dni
   - Ręczne przypisanie ma wyższy priorytet niż automatyczne przypisanie
   - Ręczne przypisanie może nadpisać automatyczne przypisanie
   - Ręczne przypisanie może być wykonane z pominięciem walidacji dostępności (ale powinno być ostrzeżone)

10. **Status bar i ostrzeżenia:**
    - Moduł WorkerSchedule dostarcza dane do status bara w module grafika pracownika (frontend)
    - Status bar pokazuje ostrzeżenia, jeśli pracownik ma za mało lub za dużo pracy
    - Za mało pracy = przypisanych ticketów < 50% przewidywanej ilości
    - Za dużo pracy = przypisanych ticketów > 150% przewidywanej ilości
    - Ostrzeżenia są obliczane na podstawie przewidywanej ilości ticketów i aktualnych przypisań

11. **Rozproszony system:**
    - Przy projektowaniu należy uwzględnić możliwość przyszłego rozproszenia systemu
    - Przypisania w grafiku mogą być synchronizowane między serwisami przez eventy domenowe
    - Rozważyć cache'owanie grafika pracowników w Redis dla najbliższych 7 dni
    - Rozważyć użycie eventów domenowych przy zmianie przypisań dla synchronizacji z modułem Tickets

12. **Bezpieczeństwo:**
    - Pracownicy mogą przeglądać tylko swój własny grafik (chyba że są managerami)
    - Managerzy mogą przeglądać i modyfikować grafik wszystkich pracowników
    - Weryfikacja uprawnień powinna być wykonywana w module BackendForFrontend przed wywołaniem metod serwisu
    - Przed przypisaniem ticketa, system powinien sprawdzić uprawnienia pracownika do kategorii

13. **Integracja z modułem grafika pracownika (frontend):**
    - Moduł grafika pracownika pokazuje najbliższe 7 dni
    - Pokazuje przypisane tickety i czas na nich spędzony dzisiaj
    - Umożliwia dodanie czasu spędzonego na rozmowie telefonicznej (moduł Tickets)
    - Umożliwia zmianę statusu ticketa (moduł Tickets)
    - Posiada status bar z ostrzeżeniami o za mało/za dużo pracy
    - Posiada sekcję z ticketem 'w toku' i możliwością dodawania notatek (moduł Tickets)

14. **Integracja z modułem przypisania/planowania ticketów (frontend):**
    - Moduł przypisania/planowania ticketów pokazuje backlog ticketów spośród kategorii
    - Umożliwia ręczne przypisanie ticketów na poszczególne dostępne dni
    - Pokazuje przewidywaną ilość ticketów, którą pracownik może obsłużyć danego dnia
    - Umożliwia automatyczne dopisanie ticketów do wszystkich dni na podstawie przewidywanej ilości obsługiwanych ticketów

15. **Historia i audyt:**
    - Pole `assignedAt` przechowuje datę pierwszego utworzenia przypisania
    - Pole `assignedBy` przechowuje informację o pracowniku, który dokonał przypisania (null jeśli automatyczne)
    - Pole `isAutoAssigned` określa, czy przypisanie było automatyczne
    - Rozważyć rozszerzenie w przyszłości o osobne logi zmian przypisań (audit trail) dla śledzenia historii zmian

16. **Wydajność:**
    - Indeksy na `worker_id`, `ticket_id`, `scheduled_date` i kombinacjach dla szybkiego wyszukiwania
    - Rozważyć cache'owanie grafika pracowników w Redis dla najbliższych 7-14 dni
    - Zapytania o grafik w okresie powinny być zoptymalizowane (użycie indeksów, limitowanie wyników)
    - Przy obliczaniu przewidywanej ilości ticketów, rozważyć cache'owanie efektywności pracowników

17. **Walidacja biznesowa:**
    - Pracownik musi być dostępny w danym dniu (sprawdzane przez moduł WorkerAvailability)
    - Pracownik musi mieć uprawnienia do kategorii ticketa (sprawdzane przez moduł Authorization)
    - Jeden ticket może być przypisany do tego samego pracownika tylko raz w danym dniu (UNIQUE constraint)
    - Data przypisania nie może być w przeszłości (tylko dzisiaj lub przyszłe daty)
    - Ticket nie może być przypisany do pracownika, który nie ma dostępu do kategorii ticketa

18. **Przypadki brzegowe:**
    - Pracownik może nie mieć dostępności w danym dniu (nie można przypisać ticketa)
    - Pracownik może nie mieć uprawnień do kategorii ticketa (nie można przypisać ticketa)
    - Ticket może być już przypisany do innego pracownika w tym samym dniu (można przypisać do wielu pracowników w różnych dniach)
    - Automatyczne przypisanie może nie znaleźć odpowiedniego pracownika (brak dostępnych pracowników lub brak uprawnień)
    - Efektywność pracownika może być nieznana (użyj domyślnej wartości 1.0)
    - Pracownik może mieć wiele dostępności w jednym dniu (suma wszystkich dostępności)

19. **Automatyczne przypisywanie - szczegóły algorytmu:**
    - Dla każdego ticketu w backlogu:
      1. Pobierz kategorię ticketa
      2. Znajdź dostępnych pracowników z uprawnieniami do kategorii
      3. Dla każdego dostępnego pracownika:
         - Sprawdź dostępność w danym dniu
         - Oblicz przewidywaną ilość ticketów, które może obsłużyć
         - Sprawdź aktualne obciążenie (ilość już przypisanych ticketów)
         - Oblicz score = (przewidywana ilość - aktualne obciążenie) * efektywność
      4. Wybierz pracownika z najwyższym score
      5. Przypisz ticket do pracownika
    - Algorytm może być uruchomiony dla wszystkich pracowników i kolejek (moduł monitoringu)
    - Algorytm może być uruchomiony dla konkretnego pracownika i dnia
    - Automatyczne przypisanie może być uruchomione codziennie automatycznie (cron job)

20. **Priorytety przypisań:**
    - Przypisania z wyższym priorytetem są wyświetlane i przetwarzane jako pierwsze
    - Priorytet null = domyślny priorytet (średni)
    - Priorytet może być ustawiony przy ręcznym przypisaniu
    - Priorytet może być używany do sortowania ticketów w module grafika pracownika (frontend)

