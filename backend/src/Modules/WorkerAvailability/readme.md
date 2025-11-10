# Moduł dostępności pracownika

## Opis modułu

Moduł dostępności pracownika jest **modułem serwisowym (fasadą)**, który odpowiada za zarządzanie dostępnością pracowników w systemie Call Center. Zajmuje się:

1. **Zarządzaniem dostępnością pracowników** - deklarowanie, w których dniach i godzinach pracownik jest dostępny do pracy
2. **Planowaniem dostępności z wyprzedzeniem** - pracownicy deklarują dostępność z tygodniowym wyprzedzeniem
3. **Aktualizacją dostępności** - możliwość zmiany dostępności po wygenerowaniu grafiku (dostępność jest zmienna)
4. **Wyszukiwaniem dostępności** - pobieranie dostępności pracownika w określonym okresie (np. najbliższe 7 dni)
5. **Walidacją dostępności** - sprawdzanie poprawności deklarowanych godzin dostępności
6. **Obsługą wielu dostępności w jednym dniu** - pracownik może mieć wiele przedziałów czasowych dostępności w jednym dniu (np. 9:00-12:00 i 14:00-17:00)

Dostępność pracownika jest deklarowana z tygodniowym wyprzedzeniem, ale może być zmieniana w dowolnym momencie. Pracownik może mieć wiele dostępności w jednym dniu, co umożliwia elastyczne planowanie (np. przerwa na lunch). Moduł WorkerSchedule (grafik pracownika) korzysta z danych dostępności do automatycznego przypisywania ticketów do pracowników.

**Uwaga:** Moduł WorkerAvailability nie zawiera endpointów API. Endpointy HTTP są zaimplementowane w module **BackendForFrontend**, który korzysta z serwisów udostępnianych przez ten moduł.

## Warstwa serwisowa (Fasada)

Moduł udostępnia następujące serwisy, które mogą być używane przez inne moduły (w szczególności przez BackendForFrontend):

### WorkerAvailabilityService
Główny serwis dostępności pracowników, udostępniający metody:

- `addWorkerAvailability(Worker $worker, Carbon $startDatetime, Carbon $endDatetime): WorkerAvailability` - dodanie dostępności pracownika w określonym przedziale czasowym (może być wiele dostępności w jednym dniu)
- `getWorkerAvailabilityById(string $id): ?WorkerAvailability` - pobranie dostępności po ID
- `getWorkerAvailabilitiesForDay(Worker $worker, Carbon $date): array` - pobranie wszystkich dostępności pracownika na konkretny dzień (zwraca tablicę WorkerAvailability, może być wiele wpisów)
- `getWorkerAvailabilityForPeriod(Worker $worker, Carbon $startDate, Carbon $endDate): array` - pobranie wszystkich dostępności pracownika w określonym okresie (zwraca tablicę WorkerAvailability)
- `getWorkerAvailabilityForWeek(Worker $worker, Carbon $weekStartDate): array` - pobranie wszystkich dostępności pracownika na tydzień (7 dni od podanej daty)
- `updateWorkerAvailability(WorkerAvailability $availability, Carbon $startDatetime, Carbon $endDatetime): WorkerAvailability` - aktualizacja przedziału czasowego dostępności
- `removeWorkerAvailability(WorkerAvailability $availability): void` - usunięcie dostępności pracownika
- `removeAllWorkerAvailabilitiesForDay(Worker $worker, Carbon $date): void` - usunięcie wszystkich dostępności pracownika na dany dzień
- `isWorkerAvailableAt(Worker $worker, Carbon $datetime): bool` - sprawdzenie, czy pracownik jest dostępny w danym momencie (data i czas)
- `isWorkerAvailableInTimeRange(Worker $worker, Carbon $startDatetime, Carbon $endDatetime): bool` - sprawdzenie, czy pracownik jest dostępny w całym przedziale czasowym
- `getAvailableWorkersForTime(Carbon $datetime): array` - pobranie listy pracowników dostępnych w danym momencie
- `getAvailableWorkersForTimeRange(Carbon $startDatetime, Carbon $endDatetime): array` - pobranie listy pracowników dostępnych w danym przedziale czasowym
- `getNextAvailableDate(Worker $worker, Carbon $fromDate): ?Carbon` - pobranie następnej daty, w której pracownik jest dostępny (null jeśli brak dostępności w przyszłości)
- `hasAvailabilityForPeriod(Worker $worker, Carbon $startDate, Carbon $endDate): bool` - sprawdzenie, czy pracownik ma jakąkolwiek dostępność w określonym okresie

## Domenowa warstwa (Entities)

### WorkerAvailability (Dostępność pracownika)
Główna encja reprezentująca pojedynczy przedział czasowy dostępności pracownika.

**Pola:**
- `id` (uuid, primary key) - unikalny identyfikator wpisu dostępności
- `worker` (Worker, ManyToOne, not null) - pracownik, którego dotyczy dostępność
- `startDatetime` (Carbon, not null) - data i czas rozpoczęcia dostępności
- `endDatetime` (Carbon, not null) - data i czas zakończenia dostępności
- `createdAt` (Carbon, not null) - data i czas utworzenia wpisu dostępności
- `updatedAt` (Carbon, nullable) - data i czas ostatniej aktualizacji

**Metody domenowe:**
- `updateAvailability(Carbon $startDatetime, Carbon $endDatetime): void` - aktualizacja przedziału czasowego dostępności
- `isAvailableAt(Carbon $datetime): bool` - sprawdzenie, czy pracownik jest dostępny w danym momencie (czy datetime mieści się w przedziale startDatetime - endDatetime)
- `isAvailableInRange(Carbon $startDatetime, Carbon $endDatetime): bool` - sprawdzenie, czy pracownik jest dostępny w całym przedziale czasowym (czy przedział się pokrywa)
- `getDurationMinutes(): int` - pobranie czasu trwania dostępności w minutach
- `getDate(): Carbon` - pobranie daty dostępności (tylko data, bez czasu, z startDatetime)
- `overlapsWith(WorkerAvailability $other): bool` - sprawdzenie, czy ta dostępność nakłada się z inną dostępnością tego samego pracownika
- `isOnSameDay(Carbon $date): bool` - sprawdzenie, czy dostępność jest w danym dniu

**Relacje:**
- ManyToOne z Worker (pracownik z modułu Authentication)

**Reguły biznesowe:**
- Pracownik może mieć wiele dostępności w jednym dniu (np. 9:00-12:00 i 14:00-17:00)
- `startDatetime` nie może być późniejsza niż `endDatetime`
- `startDatetime` i `endDatetime` muszą być w tym samym dniu (nie można mieć dostępności przechodzącej przez północ)
- Dostępność może być ustawiana z wyprzedzeniem (np. tygodniowym)
- Dostępność może być zmieniana w dowolnym momencie (nawet po wygenerowaniu grafiku)
- Data dostępności nie może być w przeszłości (tylko przyszłe daty lub dzisiaj)
- Dostępności tego samego pracownika mogą się nakładać (system powinien to wykrywać i ostrzegać, ale nie blokować)

## Tabele bazy danych

### `worker_availability`
Tabela przechowująca dostępność pracowników w poszczególnych przedziałach czasowych.

**Kolumny:**
- `id` UUID NOT NULL PRIMARY KEY
- `worker_id` UUID NOT NULL - identyfikator pracownika (FK do `workers.id`)
- `start_datetime` DATETIME NOT NULL - data i czas rozpoczęcia dostępności
- `end_datetime` DATETIME NOT NULL - data i czas zakończenia dostępności
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP - data i czas utworzenia wpisu
- `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP - data i czas ostatniej aktualizacji

**Indeksy:**
- PRIMARY KEY (`id`)
- INDEX `idx_worker_id` (`worker_id`) - dla szybkiego wyszukiwania dostępności pracownika
- INDEX `idx_start_datetime` (`start_datetime`) - dla szybkiego wyszukiwania dostępności od danej daty
- INDEX `idx_end_datetime` (`end_datetime`) - dla szybkiego wyszukiwania dostępności do danej daty
- INDEX `idx_worker_datetime_range` (`worker_id`, `start_datetime`, `end_datetime`) - dla szybkiego wyszukiwania dostępności pracownika w okresie
- INDEX `idx_date_range` (`start_datetime`, `end_datetime`) - dla wyszukiwania dostępności w przedziale czasowym
- FOREIGN KEY `fk_worker_availability_worker` (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE

**Przykładowe dane:**
```sql
-- Pracownik dostępny w poniedziałek 9:00-17:00
INSERT INTO worker_availability (id, worker_id, start_datetime, end_datetime, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440100', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '2024-01-15 09:00:00', '2024-01-15 17:00:00', NOW());

-- Pracownik dostępny we wtorek 10:00-18:00
INSERT INTO worker_availability (id, worker_id, start_datetime, end_datetime, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440101', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '2024-01-16 10:00:00', '2024-01-16 18:00:00', NOW());

-- Pracownik dostępny w środę w dwóch przedziałach: 9:00-12:00 i 14:00-17:00
INSERT INTO worker_availability (id, worker_id, start_datetime, end_datetime, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440102', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '2024-01-17 09:00:00', '2024-01-17 12:00:00', NOW()),
('550e8400-e29b-41d4-a716-446655440103', '4f86c38b-7e90-4a4d-b1ac-53edbe17e743', '2024-01-17 14:00:00', '2024-01-17 17:00:00', NOW());

-- Inny pracownik dostępny w czwartek 8:00-16:00
INSERT INTO worker_availability (id, worker_id, start_datetime, end_datetime, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440104', 'a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', '2024-01-18 08:00:00', '2024-01-18 16:00:00', NOW());
```

## Uwagi implementacyjne

1. **Relacja z modułem BackendForFrontend:**
   - Moduł WorkerAvailability udostępnia serwisy (fasadę), które są używane przez moduł BackendForFrontend
   - Wszystkie endpointy HTTP są zaimplementowane w module BackendForFrontend, który wywołuje metody serwisu WorkerAvailabilityService
   - Moduł BackendForFrontend odpowiada za walidację żądań, autentykację użytkownika i formatowanie odpowiedzi

2. **Relacja z modułem Authentication:**
   - Moduł WorkerAvailability korzysta z encji `Worker` z modułu Authentication
   - Worker musi istnieć w systemie przed ustawieniem jego dostępności

3. **Relacja z modułem WorkerSchedule:**
   - Moduł WorkerSchedule (grafik pracownika) korzysta z danych dostępności do automatycznego przypisywania ticketów
   - Moduł WorkerSchedule sprawdza dostępność pracownika przed przypisaniem mu ticketa w grafiku
   - Dostępność może być zmieniana nawet po wygenerowaniu grafiku, co może wymagać aktualizacji przypisań w module WorkerSchedule

4. **Deklarowanie dostępności:**
   - Pracownicy deklarują dostępność z tygodniowym wyprzedzeniem (moduł ustawiania dostępności pokazuje najbliższe 7 dni)
   - Dostępność może być ustawiana na pojedyncze dni lub na cały tydzień
   - Pracownik może mieć wiele dostępności w jednym dniu (np. 9:00-12:00 i 14:00-17:00)
   - Brak dostępności w danym dniu oznacza brak wpisów w tabeli dla tego dnia

5. **Zmienność dostępności:**
   - Dostępność jest zmienna i może być zmieniana w dowolnym momencie (nawet po wygenerowaniu grafiku)
   - Zmiana dostępności może wpłynąć na istniejące przypisania w module WorkerSchedule
   - Rozważyć powiadomienie modułu WorkerSchedule o zmianie dostępności (eventy domenowe)

6. **Walidacja danych:**
   - `startDatetime` i `endDatetime` muszą być obiektami Carbon (nie null)
   - `startDatetime` nie może być późniejsza niż `endDatetime`
   - `startDatetime` i `endDatetime` muszą być w tym samym dniu (nie można mieć dostępności przechodzącej przez północ)
   - Data dostępności nie może być w przeszłości (tylko przyszłe daty lub dzisiaj)
   - Dostępności tego samego pracownika mogą się nakładać (system powinien to wykrywać i ostrzegać, ale nie blokować)

7. **Wyszukiwanie dostępności:**
   - Indeksy na `worker_id`, `start_datetime`, `end_datetime` i kombinacji `(worker_id, start_datetime, end_datetime)` dla szybkiego wyszukiwania
   - Wyszukiwanie dostępności w okresie powinno być wydajne (użycie indeksów)
   - Wyszukiwanie dostępności na dzień wymaga filtrowania po dacie z `start_datetime`
   - Rozważyć cache'owanie dostępności pracowników w Redis dla najbliższych 7 dni

8. **Sprawdzanie dostępności w czasie rzeczywistym:**
   - Metoda `isWorkerAvailableAt()` sprawdza, czy pracownik jest dostępny w konkretnym momencie (sprawdza wszystkie dostępności pracownika)
   - Metoda `isWorkerAvailableInTimeRange()` sprawdza, czy pracownik jest dostępny w całym przedziale czasowym (sprawdza nakładanie się przedziałów)
   - Metody te są używane przez moduł WorkerSchedule do automatycznego przypisywania ticketów
   - Przy sprawdzaniu dostępności w danym momencie, system sprawdza wszystkie dostępności pracownika i sprawdza, czy którykolwiek z przedziałów zawiera dany moment

9. **Rozproszony system:**
   - Przy projektowaniu należy uwzględnić możliwość przyszłego rozproszenia systemu
   - Dostępność może być synchronizowana między serwisami przez eventy domenowe
   - Rozważyć cache'owanie często wyszukiwanej dostępności w Redis
   - Rozważyć użycie eventów domenowych przy zmianie dostępności dla synchronizacji z modułem WorkerSchedule

10. **Bezpieczeństwo:**
    - Pracownicy mogą przeglądać i modyfikować tylko swoją własną dostępność (chyba że są managerami)
    - Managerzy mogą przeglądać dostępność wszystkich pracowników
    - Weryfikacja uprawnień powinna być wykonywana w module BackendForFrontend przed wywołaniem metod serwisu

11. **Integracja z modułem ustawiania dostępności (frontend):**
    - Moduł ustawiania dostępności pokazuje najbliższe 7 dni
    - Umożliwia ustawienie wielu przedziałów czasowych dostępności w poszczególnych dniach (np. 9:00-12:00 i 14:00-17:00)
    - Frontend powinien walidować format daty i czasu przed wysłaniem żądania
    - Frontend może wyświetlać dostępność w formie kalendarza lub listy dni z możliwością dodawania wielu przedziałów czasowych
    - Frontend powinien umożliwiać dodawanie, edycję i usuwanie poszczególnych przedziałów czasowych

12. **Historia i audyt:**
    - Pole `createdAt` przechowuje datę pierwszego utworzenia wpisu dostępności
    - Pole `updatedAt` jest automatycznie aktualizowane przy każdej zmianie dostępności
    - Rozważyć rozszerzenie w przyszłości o osobne logi zmian dostępności (audit trail) dla śledzenia historii zmian

13. **Wydajność:**
    - Indeksy na `worker_id`, `start_datetime`, `end_datetime` i kombinacji `(worker_id, start_datetime, end_datetime)` dla szybkiego wyszukiwania
    - Rozważyć cache'owanie dostępności pracowników w Redis dla najbliższych 7-14 dni
    - Zapytania o dostępność w okresie powinny być zoptymalizowane (użycie indeksów, limitowanie wyników)
    - Przy sprawdzaniu dostępności w danym momencie, zapytanie powinno używać indeksów na `start_datetime` i `end_datetime`

14. **Walidacja biznesowa:**
    - Pracownik nie może mieć dostępności w przeszłości (tylko przyszłe daty lub dzisiaj)
    - `startDatetime` i `endDatetime` muszą być obiektami Carbon
    - `startDatetime` nie może być późniejsza niż `endDatetime`
    - `startDatetime` i `endDatetime` muszą być w tym samym dniu
    - Jeśli pracownik nie jest dostępny w danym dniu, nie ma żadnych wpisów w tabeli dla tego dnia
    - Dostępności tego samego pracownika mogą się nakładać (system powinien to wykrywać i ostrzegać, ale nie blokować)

15. **Przypadki brzegowe:**
    - Pracownik może mieć dostępność tylko w części dnia (np. 9:00-12:00)
    - Pracownik może mieć wiele dostępności w jednym dniu (np. 9:00-12:00 i 14:00-17:00)
    - Pracownik może nie mieć dostępności w danym dniu (brak wpisów w tabeli)
    - Pracownik może zmienić dostępność z pełnego dnia na część dnia (i odwrotnie)
    - Pracownik może usunąć pojedynczą dostępność lub wszystkie dostępności w danym dniu
    - Dostępności mogą się nakładać (np. 9:00-13:00 i 12:00-17:00) - system powinien to wykrywać i ostrzegać

