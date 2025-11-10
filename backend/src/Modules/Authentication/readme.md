# Moduł autentykacji pracowników

## Opis modułu

Moduł autentykacji pracowników jest **modułem serwisowym (fasadą)**, który odpowiada za podstawową identyfikację i weryfikację tożsamości pracowników w systemie. Zajmuje się:

1. **Rejestracją nowych pracowników** - tworzenie kont pracowniczych z loginem i hasłem
2. **Weryfikacją tożsamości** - sprawdzanie poprawności loginu i hasła
3. **Zarządzaniem hasłami** - przechowywanie zahashowanych haseł zgodnie z najlepszymi praktykami bezpieczeństwa
4. **Zarządzaniem encjami pracowników** - operacje CRUD na danych pracowników

Moduł ten jest odpowiedzialny wyłącznie za **autentykację** (kto jest zalogowany), natomiast **autoryzacja** (do jakich kategorii ma dostęp) jest obsługiwana przez osobny moduł Authorization.

**Uwaga:** Moduł Authentication nie zawiera endpointów API. Endpointy HTTP są zaimplementowane w module **BackendForFrontend**, który korzysta z serwisów udostępnianych przez ten moduł.

## Warstwa serwisowa (Fasada)

Moduł udostępnia następujące serwisy, które mogą być używane przez inne moduły (w szczególności przez BackendForFrontend):

### AuthenticationService
Główny serwis autentykacji, udostępniający metody:

- `registerWorker(string $id, string $login, string $password): Worker` - rejestracja nowego pracownika
- `authenticateWorker(string $login, string $password): ?Worker` - weryfikacja loginu i hasła, zwraca obiekt Worker lub null
- `getWorkerById(string $id): ?Worker` - pobranie pracownika po ID
- `getWorkerByLogin(string $login): ?Worker` - pobranie pracownika po loginie
- `changePassword(Worker $worker, string $oldPassword, string $newPassword): void` - zmiana hasła pracownika

## Domenowa warstwa (Entities)

### Worker (Pracownik)
Główna encja reprezentująca pracownika w systemie.

**Pola:**
- `id` (uuid, primary key) - unikalny identyfikator pracownika
- `login` (string, unique, not null) - unikalna nazwa użytkownika do logowania
- `passwordHash` (string, not null) - zahashowane hasło (używając `password_hash()` z algorytmem PASSWORD_BCRYPT)
- `createdAt` (Carbon, not null) - data i czas utworzenia konta
- `updatedAt` (Carbon, nullable) - data i czas ostatniej aktualizacji

**Metody domenowe:**
- `setPassword(string $plainPassword): void` - ustawia i haszuje hasło
- `verifyPassword(string $plainPassword): bool` - weryfikuje podane hasło z zahashowanym
- `changePassword(string $oldPassword, string $newPassword): void` - zmiana hasła z weryfikacją starego

**Relacje:**
- Relacja z modułem Authorization (WorkerCategoryAssignment) - wiele do wielu z kategoriami ticketów
- Relacja z modułem WorkerAvailability - jeden do wielu (pracownik ma wiele wpisów dostępności)
- Relacja z modułem WorkerSchedule - jeden do wielu (pracownik ma wiele przypisanych ticketów w grafiku)

## Tabele bazy danych

### `workers`
Tabela przechowująca dane pracowników.

**Kolumny:**
- `id` UUID NOT NULL PRIMARY KEY
- `login` VARCHAR(255) UNIQUE NOT NULL - unikalna nazwa użytkownika
- `password_hash` VARCHAR(255) NOT NULL - zahashowane hasło (bcrypt, 60 znaków)
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP

**Indeksy:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_login` (`login`)
- INDEX `idx_created_at` (`created_at`)

**Przykładowe dane:**
```sql
INSERT INTO workers (id, login, password_hash, created_at) VALUES 
('4f86c38b-7e90-4a4d-b1ac-53edbe17e743', 'jan.kowalski', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW()),
('a7de62b2-6d00-4b2d-95ad-bd0ec3e225fa', 'anna.nowak', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW()),
('53de51de-3d17-4e5c-89e1-b44cf32e706f', 'piotr.zielinski', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW());
```

## Uwagi implementacyjne

1. **Relacja z modułem BackendForFrontend:**
   - Moduł Authentication udostępnia serwisy (fasadę), które są używane przez moduł BackendForFrontend
   - Wszystkie endpointy HTTP są zaimplementowane w module BackendForFrontend, który wywołuje metody serwisu AuthenticationService
   - Moduł BackendForFrontend odpowiada za walidację żądań, zarządzanie sesjami i formatowanie odpowiedzi

2. **Relacja z modułem Authorization:**
   - Moduł Authorization korzysta z encji `Worker` z modułu Authentication
   - Worker musi istnieć w systemie przed przypisaniem mu uprawnień
   - Moduł Authentication nie zarządza uprawnieniami do kategorii - to robi moduł Authorization
   - Po rejestracji pracownika, uprawnienia do kategorii są przypisywane przez moduł Authorization (na podstawie checkboxów z formularza rejestracji)

3. **Relacja z modułem WorkerAvailability:**
   - Moduł WorkerAvailability korzysta z encji `Worker` z modułu Authentication
   - Worker musi istnieć w systemie przed utworzeniem jego dostępności

4. **Relacja z modułem WorkerSchedule:**
   - Moduł WorkerSchedule korzysta z encji `Worker` z modułu Authentication
   - Worker musi istnieć w systemie przed przypisaniem mu ticketów w grafiku

5. **Relacja z modułem Tickets:**
   - Moduł Tickets korzysta z encji `Worker` z modułu Authentication
   - Worker musi istnieć w systemie przed przypisaniem mu ticketów

6. **Bezpieczeństwo haseł:**
   - Hasła muszą być hashowane używając `password_hash()` z algorytmem `PASSWORD_BCRYPT`
   - Weryfikacja haseł przez `password_verify()`
   - Hasła nie powinny być nigdy przechowywane w formie plaintext

7. **Sesje:**
   - Moduł Authentication nie zarządza sesjami - to jest odpowiedzialność modułu BackendForFrontend
   - Moduł Authentication tylko weryfikuje tożsamość i zwraca obiekt Worker, a zarządzanie sesją odbywa się w warstwie kontrolera
   - Zarządzanie sesjami (Redis, JWT tokeny) jest odpowiedzialnością modułu BackendForFrontend

8. **Walidacja:**
   - Login: minimum 3 znaki, maksimum 255 znaków, tylko litery, cyfry, kropki i podkreślenia
   - Hasło: minimum 8 znaków, zalecane użycie małych i wielkich liter, cyfr i znaków specjalnych

9. **Rozproszony system:**
   - Przy projektowaniu należy uwzględnić możliwość przyszłego rozproszenia systemu
   - Moduł Authentication powinien być niezależny od mechanizmu sesji - zwraca tylko obiekty domenowe
   - Rozważyć użycie eventów domenowych przy rejestracji pracownika dla synchronizacji między serwisami

