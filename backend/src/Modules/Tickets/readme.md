# Moduł ticketów

## Opis modułu

Moduł ticketów jest **modułem serwisowym (fasadą)**, który odpowiada za zarządzanie ticketami i ich historią w systemie Call Center. Zajmuje się:

1. **Zarządzaniem ticketami** - tworzenie, aktualizacja, wyszukiwanie i usuwanie ticketów
2. **Śledzeniem historii ticketów** - przechowywanie faktycznego czasu spędzonego na danym tickecie przez pracowników (czas rozmowy telefonicznej)
3. **Zarządzaniem statusami ticketów** - określanie statusu ticketa (zamknięty, oczekuje na odpowiedź z naszej strony, oczekuje na odpowiedź ze strony klienta, w toku)
4. **Rejestrowaniem czasu pracy** - śledzenie czasu rozpoczęcia i zakończenia obsługi ticketa przez pracowników
5. **Obliczaniem efektywności pracowników** - kalkulacja efektywności pracownika na podstawie historii ticketów w danej kategorii
6. **Zarządzaniem notatkami** - przechowywanie notatek dodawanych do ticketów przez pracowników

Ticket jest przypisany do kategorii (z modułu TicketCategories) i klienta (z modułu Clients). Ticket może zawierać czas rozpoczęcia jego obsługi bez czasu zakończenia (czyli ticket jest w toku), co umożliwia śledzenie aktualnie obsługiwanych ticketów.

**Uwaga:** Moduł Tickets nie zawiera endpointów API. Endpointy HTTP są zaimplementowane w module **BackendForFrontend**, który korzysta z serwisów udostępnianych przez ten moduł.

## Warstwa serwisowa (Fasada)

Moduł udostępnia następujące serwisy, które mogą być używane przez inne moduły (w szczególności przez BackendForFrontend):

### TicketService
Główny serwis ticketów, udostępniający metody:

- `createTicket(string $id, Client $client, TicketCategory $category, ?string $title = null, ?string $description = null): Ticket` - utworzenie nowego ticketa
- `getTicketById(string $id): ?Ticket` - pobranie ticketa po ID
- `getTicketsByClient(Client $client): array` - pobranie wszystkich ticketów przypisanych do klienta
- `getTicketsByCategory(TicketCategory $category, ?string $status = null): array` - pobranie ticketów z danej kategorii (opcjonalnie filtrowane po statusie)
- `getTicketsByWorker(Worker $worker, ?string $status = null): array` - pobranie ticketów obsługiwanych przez pracownika (opcjonalnie filtrowane po statusie)
- `updateTicketStatus(Ticket $ticket, string $status): Ticket` - zmiana statusu ticketa
- `startTicketWork(Ticket $ticket, Worker $worker): TicketRegisteredTime` - rozpoczęcie obsługi ticketa przez pracownika (ustawienie statusu na 'w toku' i rozpoczęcie rejestracji czasu)
- `stopTicketWork(Ticket $ticket, Worker $worker): TicketRegisteredTime` - zakończenie obsługi ticketa przez pracownika (zakończenie rejestracji czasu)
- `registerManualTimeEntry(Ticket $ticket, Worker $worker, int $minutes, bool $isPhoneCall): void` - ręczne dopisanie czasu pracy lub rozmowy do ticketa (np. korekta połączenia telefonicznego)
- `addTicketNote(Ticket $ticket, Worker $worker, string $note): TicketNote` - dodanie notatki do ticketa
- `getTicketRegisteredTime(Ticket $ticket): array` - pobranie zarejestrowanego czasu pracy nad ticketem (wszystkie wpisy czasu pracy)
- `getTicketNotes(Ticket $ticket): array` - pobranie wszystkich notatek przypisanych do ticketa
- `closeTicket(Ticket $ticket, Worker $worker): Ticket` - zamknięcie ticketa
- `calculateWorkerEfficiency(Worker $worker, TicketCategory $category, ?Carbon $fromDate = null, ?Carbon $toDate = null): float` - obliczenie efektywności pracownika na danej kategorii (stosunek czasu rzeczywistego do czasu domyślnego)
- `getTicketsInProgress(Worker $worker): array` - pobranie wszystkich ticketów w toku dla danego pracownika
- `getTotalTimeSpentOnTicket(Ticket $ticket): int` - pobranie całkowitego czasu spędzonego na tickecie w minutach
- `getWorkerTimeSpentOnTicket(Ticket $ticket, Worker $worker): int` - pobranie czasu spędzonego przez konkretnego pracownika na tickecie w minutach

### TicketBacklogService

### TicketSearchService _(nowy interfejs na potrzeby BackendForFrontend)_

Interfejs `TicketSearchServiceInterface` służy do wyszukiwania ticketów w kontekście pracownika.
BFF wykorzystuje go w endpointzie `/api/worker/tickets/search`, oczekując, że implementacja:

- uwzględni uprawnienia pracownika (dostępne kategorie, rola managera),
- zwróci liczbę znalezionych ticketów wraz z informacją o czasie spędzonym przez pracownika (`timeSpent`),
- pozwoli filtrować po statusie, pojedynczej kategorii, frazie tekstowej i kontrolować limit wyników (domyślnie 20, maksymalnie 100),
- zwróci metadane `total` do paginacji po stronie frontendowej.

> TODO: dostarczyć implementację `TicketSearchServiceInterface`, aby warstwa BFF mogła opierać się na rzeczywistych danych wyszukiwania.

## Domenowa warstwa (Entities)

### Ticket (Zgłoszenie)
Główna encja reprezentująca ticket w systemie.

**Pola:**
- `id` (uuid, primary key) - unikalny identyfikator ticketa
- `client` (Client, ManyToOne, not null) - klient, który zgłosił ticket
- `category` (TicketCategory, ManyToOne, not null) - kategoria ticketa
- `title` (string, nullable) - tytuł ticketa (opcjonalny)
- `description` (string, nullable) - opis problemu/zgłoszenia (opcjonalny)
- `status` (string, not null, default: 'awaiting_response') - status ticketa (enum: 'closed', 'awaiting_response', 'awaiting_customer', 'in_progress')
- `createdAt` (Carbon, not null) - data i czas utworzenia ticketa
- `updatedAt` (Carbon, nullable) - data i czas ostatniej aktualizacji
- `closedAt` (Carbon, nullable) - data i czas zamknięcia ticketa (jeśli zamknięty)
- `closedBy` (Worker, ManyToOne, nullable) - pracownik, który zamknął ticket (opcjonalne)

**Metody domenowe:**
- `changeStatus(string $status): void` - zmiana statusu ticketa
- `close(Worker $worker): void` - zamknięcie ticketa
- `isClosed(): bool` - sprawdzenie, czy ticket jest zamknięty
- `isInProgress(): bool` - sprawdzenie, czy ticket jest w toku
- `isAwaitingResponse(): bool` - sprawdzenie, czy ticket oczekuje na odpowiedź z naszej strony
- `isAwaitingCustomer(): bool` - sprawdzenie, czy ticket oczekuje na odpowiedź ze strony klienta
- `updateDescription(?string $description): void` - aktualizacja opisu ticketa
- `updateTitle(?string $title): void` - aktualizacja tytułu ticketa

**Relacje:**
- ManyToOne z Client (klient z modułu Clients)
- ManyToOne z TicketCategory (kategoria z modułu TicketCategories)
- OneToMany z TicketRegisteredTime (zarejestrowany czas pracy nad ticketem)
- OneToMany z TicketNote (notatki do ticketa)
- ManyToOne z Worker (pracownik, który zamknął ticket)

**Reguły biznesowe:**
- Ticket musi być przypisany do klienta i kategorii
- Status ticketa może być: 'closed', 'awaiting_response', 'awaiting_customer', 'in_progress'
- Ticket w statusie 'in_progress' musi mieć aktywny wpis w TicketRegisteredTime (czas rozpoczęcia bez czasu zakończenia)
- Zamknięty ticket nie może być ponownie otwarty (można utworzyć nowy ticket)
- Ticket może mieć wiele wpisów zarejestrowanego czasu (różni pracownicy, różne sesje pracy)

### TicketRegisteredTime (Zarejestrowany czas pracy)
Encja reprezentująca pojedynczy wpis czasu pracy nad ticketem przez pracownika.

**Pola:**
- `id` (uuid, primary key) - unikalny identyfikator wpisu zarejestrowanego czasu
- `ticket` (Ticket, ManyToOne, not null) - ticket, którego dotyczy wpis
- `worker` (Worker, ManyToOne, not null) - pracownik, który pracował nad ticketem
- `startedAt` (Carbon, not null) - data i czas rozpoczęcia pracy nad ticketem
- `endedAt` (Carbon, nullable) - data i czas zakończenia pracy nad ticketem (null, jeśli praca trwa)
- `durationMinutes` (int, nullable) - czas trwania w minutach (obliczany po zakończeniu, null jeśli praca trwa)
- `isPhoneCall` (bool, not null, default: false) - flaga określająca, czy był to czas rozmowy telefonicznej

**Metody domenowe:**
- `start(Worker $worker): void` - rozpoczęcie pracy nad ticketem
- `end(): void` - zakończenie pracy nad ticketem (oblicza durationMinutes)
- `isActive(): bool` - sprawdzenie, czy praca nad ticketem trwa (endedAt jest null)
- `getDurationMinutes(): ?int` - pobranie czasu trwania w minutach (null, jeśli praca trwa)
- `markAsPhoneCall(): void` - oznaczenie wpisu jako rozmowy telefonicznej

**Relacje:**
- ManyToOne z Ticket (ticket)
- ManyToOne z Worker (pracownik z modułu Authentication)

**Reguły biznesowe:**
- Wpis zarejestrowanego czasu musi mieć czas rozpoczęcia
- Wpis zarejestrowanego czasu może nie mieć czasu zakończenia (praca w toku)
- Czas zakończenia nie może być wcześniejszy niż czas rozpoczęcia
- Jeden pracownik może mieć tylko jeden aktywny wpis zarejestrowanego czasu dla danego ticketa (endedAt = null)
- Po zakończeniu pracy, durationMinutes jest obliczany automatycznie

### TicketNote (Notatka)
Encja reprezentująca notatkę dodaną do ticketa przez pracownika.

**Pola:**
- `id` (uuid, primary key) - unikalny identyfikator notatki
- `ticket` (Ticket, ManyToOne, not null) - ticket, do którego przypisana jest notatka
- `worker` (Worker, ManyToOne, not null) - pracownik, który dodał notatkę
- `content` (string, not null) - treść notatki
- `createdAt` (Carbon, not null) - data i czas utworzenia notatki

**Metody domenowe:**
- `updateContent(string $content): void` - aktualizacja treści notatki
- `getFormattedCreatedAt(): string` - pobranie sformatowanej daty utworzenia

**Relacje:**
- ManyToOne z Ticket (ticket)
- ManyToOne z Worker (pracownik z modułu Authentication)

**Reguły biznesowe:**
- Notatka musi mieć treść (minimum 1 znak)
- Notatka jest przypisana do ticketa i pracownika, który ją dodał
- Notatki są sortowane chronologicznie (najstarsze pierwsze)

## Tabele bazy danych

### `tickets`
Tabela przechowująca dane ticketów.

**Kolumny:**
- `id` UUID NOT NULL PRIMARY KEY
- `client_id` UUID NOT NULL - identyfikator klienta (FK do `clients.id`)
- `category_id` VARCHAR(255) NOT NULL - identyfikator kategorii (FK do kategorii z modułu TicketCategories)
- `title` VARCHAR(255) NULL - tytuł ticketa (opcjonalny)
- `description` TEXT NULL - opis problemu/zgłoszenia (opcjonalny)
- `status` VARCHAR(50) NOT NULL DEFAULT 'awaiting_response' - status ticketa (enum: 'closed', 'awaiting_response', 'awaiting_customer', 'in_progress')
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP - data i czas utworzenia ticketa
- `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP - data i czas ostatniej aktualizacji
- `closed_at` DATETIME NULL - data i czas zamknięcia ticketa (jeśli zamknięty)
- `closed_by_id` UUID NULL - identyfikator pracownika, który zamknął ticket (FK do `workers.id`, opcjonalne)

**Indeksy:**
- PRIMARY KEY (`id`)
- INDEX `idx_client_id` (`client_id`)
- INDEX `idx_category_id` (`category_id`)
- INDEX `idx_status` (`status`)
- INDEX `idx_created_at` (`created_at`)
- INDEX `idx_closed_at` (`closed_at`)
- FOREIGN KEY `fk_ticket_client` (`client_id`) REFERENCES `clients` (`id`) ON DELETE RESTRICT
- FOREIGN KEY `fk_ticket_closed_by` (`closed_by_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL

**Przykładowe dane:**
```sql
INSERT INTO tickets (id, client_id, category_id, title, description, status, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440010', '550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440001', 'Problem z połączeniem', 'Nie mogę połączyć się z internetem', 'awaiting_response', NOW()),
('550e8400-e29b-41d4-a716-446655440011', '550e8400-e29b-41d4-a716-446655440002', '550e8400-e29b-41d4-a716-446655440002', 'Reklamacja faktury', 'Faktura jest nieprawidłowa', 'in_progress', NOW()),
('550e8400-e29b-41d4-a716-446655440012', '550e8400-e29b-41d4-a716-446655440003', '550e8400-e29b-41d4-a716-446655440001', 'Zapytanie o ofertę', NULL, 'awaiting_customer', NOW());
```

### `ticket_registered_time`
Tabela przechowująca zarejestrowany czas pracy nad ticketami przez pracowników.

**Kolumny:**
- `id` UUID NOT NULL PRIMARY KEY
- `ticket_id` UUID NOT NULL - identyfikator ticketa (FK do `tickets.id`)
- `worker_id` UUID NOT NULL - identyfikator pracownika (FK do `workers.id`)
- `started_at` DATETIME NOT NULL - data i czas rozpoczęcia pracy nad ticketem
- `ended_at` DATETIME NULL - data i czas zakończenia pracy nad ticketem (null, jeśli praca trwa)
- `duration_minutes` INT NULL - czas trwania w minutach (obliczany po zakończeniu, null jeśli praca trwa)
- `is_phone_call` BOOLEAN NOT NULL DEFAULT FALSE - flaga określająca, czy był to czas rozmowy telefonicznej

**Indeksy:**
- PRIMARY KEY (`id`)
- INDEX `idx_ticket_id` (`ticket_id`)
- INDEX `idx_worker_id` (`worker_id`)
- INDEX `idx_started_at` (`started_at`)
- INDEX `idx_ended_at` (`ended_at`)
- INDEX `idx_active_work` (`ticket_id`, `worker_id`, `ended_at`) - dla szybkiego wyszukiwania aktywnych sesji pracy
- FOREIGN KEY `fk_ticket_registered_time_ticket` (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
- FOREIGN KEY `fk_ticket_registered_time_worker` (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE

**Przykładowe dane:**
```sql
INSERT INTO ticket_registered_time (id, ticket_id, worker_id, started_at, ended_at, duration_minutes, is_phone_call) VALUES 
('550e8400-e29b-41d4-a716-446655440020', '550e8400-e29b-41d4-a716-446655440010', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '2024-01-15 10:00:00', '2024-01-15 10:25:00', 25, TRUE),
('550e8400-e29b-41d4-a716-446655440021', '550e8400-e29b-41d4-a716-446655440011', 'a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', '2024-01-15 11:00:00', NULL, NULL, FALSE),
('550e8400-e29b-41d4-a716-446655440022', '550e8400-e29b-41d4-a716-446655440010', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '2024-01-15 14:00:00', '2024-01-15 14:15:00', 15, FALSE);
```

### `ticket_notes`
Tabela przechowująca notatki dodawane do ticketów przez pracowników.

**Kolumny:**
- `id` UUID NOT NULL PRIMARY KEY
- `ticket_id` UUID NOT NULL - identyfikator ticketa (FK do `tickets.id`)
- `worker_id` UUID NOT NULL - identyfikator pracownika, który dodał notatkę (FK do `workers.id`)
- `content` TEXT NOT NULL - treść notatki
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP - data i czas utworzenia notatki

**Indeksy:**
- PRIMARY KEY (`id`)
- INDEX `idx_ticket_id` (`ticket_id`)
- INDEX `idx_worker_id` (`worker_id`)
- INDEX `idx_created_at` (`created_at`)
- FOREIGN KEY `fk_ticket_note_ticket` (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
- FOREIGN KEY `fk_ticket_note_worker` (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE

**Przykładowe dane:**
```sql
INSERT INTO ticket_notes (id, ticket_id, worker_id, content, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440030', '550e8400-e29b-41d4-a716-446655440010', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', 'Klient zgłosił problem z routerem. Sprawdziłem konfigurację.', NOW()),
('550e8400-e29b-41d4-a716-446655440031', '550e8400-e29b-41d4-a716-446655440011', 'a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', 'Wysłałem poprawioną fakturę na email klienta.', NOW());
```

## Uwagi implementacyjne

1. **Relacja z modułem BackendForFrontend:**
   - Moduł Tickets udostępnia serwisy (fasadę), które są używane przez moduł BackendForFrontend
   - Wszystkie endpointy HTTP są zaimplementowane w module BackendForFrontend, który wywołuje metody serwisu TicketService
   - Moduł BackendForFrontend odpowiada za walidację żądań, autoryzację (sprawdzenie uprawnień do kategorii) i formatowanie odpowiedzi

2. **Relacja z modułem Clients:**
   - Moduł Tickets korzysta z encji `Client` z modułu Clients
   - Każdy ticket musi być przypisany do klienta
   - Przy tworzeniu nowego ticketa, jeśli klient nie istnieje, należy go utworzyć (może być anonimowy)

3. **Relacja z modułem TicketCategories:**
   - Moduł Tickets korzysta z encji `TicketCategory` z modułu TicketCategories
   - Każdy ticket musi być przypisany do kategorii
   - Domyślny czas rozwiązania z kategorii jest używany do obliczania efektywności pracownika

4. **Relacja z modułem Authorization:**
   - Przed przeglądaniem/obsługą ticketów z danej kategorii, system powinien sprawdzić uprawnienia pracownika przez moduł Authorization
   - Pracownicy mogą przeglądać i obsługiwać tylko tickety z kategorii, do których mają dostęp
   - Managerzy mogą przeglądać wszystkie tickety

5. **Relacja z modułem WorkerSchedule:**
   - Moduł WorkerSchedule przypisuje tickety do pracowników w grafiku
   - Moduł Tickets dostarcza dane o ticketach i ich statusach dla modułu WorkerSchedule
   - Efektywność pracownika obliczana w module Tickets jest używana przez moduł WorkerSchedule do automatycznego przypisywania ticketów

6. **Statusy ticketów:**
   - `awaiting_response` - ticket oczekuje na odpowiedź z naszej strony (domyślny status przy utworzeniu)
   - `awaiting_customer` - ticket oczekuje na odpowiedź ze strony klienta
   - `in_progress` - ticket jest aktualnie obsługiwany przez pracownika (ma aktywny wpis w TicketRegisteredTime)
   - `closed` - ticket jest zamknięty (nie można go ponownie otworzyć)

7. **Rejestracja czasu pracy:**
   - Gdy pracownik rozpoczyna pracę nad ticketem, tworzony jest wpis w TicketRegisteredTime z `startedAt` i `endedAt = null`
   - Gdy pracownik kończy pracę nad ticketem, wpis jest aktualizowany z `endedAt` i obliczany jest `durationMinutes`
   - Jeden pracownik może mieć tylko jeden aktywny wpis zarejestrowanego czasu dla danego ticketa (endedAt = null)
   - Przy rozpoczęciu nowej sesji pracy, poprzednia sesja powinna być automatycznie zakończona

8. **Efektywność pracownika:**
   - Efektywność jest obliczana jako stosunek czasu rzeczywistego spędzonego na ticketach do czasu domyślnego z kategorii
   - Formuła: `efficiency = (suma czasu domyślnego) / (suma czasu rzeczywistego)`
   - Efektywność > 1 oznacza, że pracownik pracuje szybciej niż domyślny czas
   - Efektywność < 1 oznacza, że pracownik pracuje wolniej niż domyślny czas
   - Obliczenia są wykonywane na podstawie zamkniętych ticketów w określonym przedziale czasowym

9. **Rozproszony system:**
   - Przy projektowaniu należy uwzględnić możliwość przyszłego rozproszenia systemu
   - Zarejestrowany czas pracy nad ticketami może być synchronizowany między serwisami przez eventy domenowe
   - Rozważyć cache'owanie często wyszukiwanych ticketów w Redis
   - Rozważyć użycie eventów domenowych przy zmianie statusu ticketa

10. **Bezpieczeństwo i autoryzacja:**
    - Przed przeglądaniem/obsługą ticketów, system powinien sprawdzić uprawnienia pracownika do kategorii (moduł Authorization)
    - Pracownicy mogą przeglądać tylko tickety z kategorii, do których mają dostęp
    - Managerzy mogą przeglądać wszystkie tickety
    - Weryfikacja uprawnień powinna być wykonywana w module BackendForFrontend przed wywołaniem metod serwisu

11. **Integracja z modułem 'odbieram telefon':**
    - Gdy pracownik odbiera telefon, moduł Tickets tworzy nowy wpis w TicketRegisteredTime z `is_phone_call = true`
    - Po zakończeniu połączenia, czas jest rejestrowany do wybranego/nowego ticketa
    - Nowy ticket utworzony podczas rozmowy jest automatycznie dodawany do grafika bieżącego dnia z statusem 'in_progress'
   - TODO: przygotować usługę domenową wykorzystywaną przez `WorkerPhoneServiceInterface`, która obsłuży rozpoczęcie i zakończenie połączenia (zamykanie aktywnych wpisów czasu oraz odtwarzanie statusów ticketów)

12. **Historia i audyt:**
    - Wszystkie zmiany statusu ticketa powinny być logowane (można rozważyć osobne logi zmian)
    - Zarejestrowany czas pracy jest przechowywany w tabeli `ticket_registered_time` i nie może być usunięty
    - Notatki są przypisane do pracownika, który je dodał, dla celów audytu

13. **Wydajność:**
    - Indeksy na `status`, `created_at`, `client_id`, `category_id` dla szybkiego wyszukiwania
    - Indeks na `ticket_id`, `worker_id`, `ended_at` dla szybkiego wyszukiwania aktywnych sesji pracy
    - Rozważyć cache'owanie efektywności pracowników w Redis (aktualizowane przy zamknięciu ticketa)

14. **Walidacja:**
    - Status ticketa musi być jednym z dozwolonych wartości (enum)
    - Czas zakończenia nie może być wcześniejszy niż czas rozpoczęcia
    - Ticket w statusie 'in_progress' musi mieć aktywny wpis w TicketRegisteredTime
    - Zamknięty ticket nie może mieć aktywnych wpisów w TicketRegisteredTime

