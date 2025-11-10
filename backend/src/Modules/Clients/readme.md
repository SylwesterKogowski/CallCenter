# Moduł klientów

## Opis modułu

Moduł klientów jest **modułem serwisowym (fasadą)**, który odpowiada za zarządzanie danymi klientów zgłaszających tickety do systemu Call Center. Zajmuje się:

1. **Zarządzaniem danymi klientów** - tworzenie, aktualizacja i wyszukiwanie klientów
2. **Obsługą klientów anonimowych** - możliwość tworzenia klientów bez pełnych danych (np. tylko email lub telefon)
3. **Identyfikacją klientów** - uzupełnianie danych klienta podczas rozmowy telefonicznej lub w trakcie obsługi ticketa
4. **Zarządzaniem kontaktami** - przechowywanie różnych sposobów kontaktu z klientem (email, telefon)
5. **Historią interakcji** - powiązanie klienta z jego ticketami w systemie

Klienci w systemie mogą być zarówno **zidentyfikowani** (z pełnymi danymi kontaktowymi), jak i **anonimowi** (np. osoby zgłaszające tickety przez formularz na stronie internetowej bez logowania, lub osoby dzwoniące, które jeszcze nie zostały w pełni zidentyfikowane podczas rozmowy).

**Uwaga:** Moduł Clients nie zawiera endpointów API. Endpointy HTTP są zaimplementowane w module **BackendForFrontend**, który korzysta z serwisów udostępnianych przez ten moduł.

## Warstwa serwisowa (Fasada)

Moduł udostępnia następujące serwisy, które mogą być używane przez inne moduły (w szczególności przez BackendForFrontend):

### ClientService
Główny serwis klientów, udostępniający metody:

- `createClient(string $id, ?string $email = null, ?string $phone = null, ?string $firstName = null, ?string $lastName = null): Client` - utworzenie nowego klienta (wszystkie parametry opcjonalne, pozwala na tworzenie klientów anonimowych)
- `getClientById(string $id): ?Client` - pobranie klienta po ID
- `findClientByEmail(string $email): ?Client` - wyszukanie klienta po adresie email
- `findClientByPhone(string $phone): ?Client` - wyszukanie klienta po numerze telefonu
- `updateClient(Client $client, ?string $email = null, ?string $phone = null, ?string $firstName = null, ?string $lastName = null): Client` - aktualizacja danych klienta (uzupełnianie danych anonimowego klienta)
- `identifyClient(Client $client, string $email, ?string $phone = null, ?string $firstName = null, ?string $lastName = null): Client` - identyfikacja anonimowego klienta (uzupełnienie danych)
- `isClientAnonymous(Client $client): bool` - sprawdzenie, czy klient jest anonimowy (brak pełnych danych)
- `getClientTickets(Client $client): array` - pobranie wszystkich ticketów przypisanych do klienta (relacja z modułem Tickets)

### ClientSearchService _(nowy interfejs na potrzeby BackendForFrontend)_

Warstwa BFF korzysta z interfejsu `ClientSearchServiceInterface`, który powinien umożliwić wyszukiwanie
klientów po dowolnej frazie (imię/nazwisko/email/telefon) z limitem wyników oraz opcjonalną wartością
`matchScore` do sortowania wyników. Implementacja powinna:

- uwzględniać anonimizację danych zgodnie z polityką prywatności,
- respektować limit wyników przekazywany przez BFF (domyślnie 10, maksymalnie 100),
- być zoptymalizowana pod kątem szybkiego wyszukiwania (np. indeksy FULLTEXT, trigramy, cache).

> TODO: zaprojektować i dostarczyć implementację `ClientSearchServiceInterface`, aby endpoint
> `/api/worker/clients/search` zwracał rzeczywiste dane.

## Domenowa warstwa (Entities)

### Client (Klient)
Główna encja reprezentująca klienta w systemie.

**Pola:**
- `id` (uuid, primary key) - unikalny identyfikator klienta
- `email` (string, nullable, unique) - adres email klienta (opcjonalny, może być null dla klientów anonimowych)
- `phone` (string, nullable) - numer telefonu klienta (opcjonalny)
- `firstName` (string, nullable) - imię klienta (opcjonalne)
- `lastName` (string, nullable) - nazwisko klienta (opcjonalne)
- `isAnonymous` (bool, not null, default: true) - flaga określająca, czy klient jest anonimowy (brak pełnych danych)
- `createdAt` (Carbon, not null) - data i czas utworzenia rekordu klienta
- `updatedAt` (Carbon, nullable) - data i czas ostatniej aktualizacji danych
- `identifiedAt` (Carbon, nullable) - data i czas identyfikacji klienta (gdy anonimowy klient został zidentyfikowany)

**Metody domenowe:**
- `identify(string $email, ?string $phone = null, ?string $firstName = null, ?string $lastName = null): void` - identyfikacja anonimowego klienta (uzupełnienie danych)
- `updateContact(?string $email = null, ?string $phone = null): void` - aktualizacja danych kontaktowych
- `updatePersonalData(?string $firstName = null, ?string $lastName = null): void` - aktualizacja danych osobowych
- `isAnonymous(): bool` - sprawdzenie, czy klient jest anonimowy
- `getFullName(): ?string` - pobranie pełnego imienia i nazwiska (lub null, jeśli brak danych)
- `hasContactData(): bool` - sprawdzenie, czy klient ma jakiekolwiek dane kontaktowe (email lub telefon)

**Relacje:**
- Relacja z modułem Tickets (Ticket) - jeden do wielu (klient może mieć wiele ticketów)

**Reguły biznesowe:**
- Klient jest uznawany za anonimowego, jeśli nie ma zarówno emaila, jak i pełnych danych osobowych (imię i nazwisko)
- Email musi być unikalny w systemie (jeśli podany)
- Klient może być utworzony z minimalnymi danymi (np. tylko email lub tylko telefon)
- Identyfikacja klienta następuje, gdy uzupełniamy dane anonimowego klienta (ustawienie `isAnonymous` na false i `identifiedAt` na aktualną datę)

## Tabele bazy danych

### `clients`
Tabela przechowująca dane klientów.

**Kolumny:**
- `id` UUID NOT NULL PRIMARY KEY
- `email` VARCHAR(255) NULL UNIQUE - adres email klienta (opcjonalny, unikalny jeśli podany)
- `phone` VARCHAR(50) NULL - numer telefonu klienta (opcjonalny, format: +48XXXXXXXXX lub XXXXXXXXX)
- `first_name` VARCHAR(100) NULL - imię klienta (opcjonalne)
- `last_name` VARCHAR(100) NULL - nazwisko klienta (opcjonalne)
- `is_anonymous` BOOLEAN NOT NULL DEFAULT TRUE - flaga określająca, czy klient jest anonimowy
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP - data i czas utworzenia rekordu
- `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP - data i czas ostatniej aktualizacji
- `identified_at` DATETIME NULL - data i czas identyfikacji klienta (gdy anonimowy został zidentyfikowany)

**Indeksy:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_email` (`email`) - email musi być unikalny (jeśli podany)
- INDEX `idx_email` (`email`) - dla szybkiego wyszukiwania po emailu
- INDEX `idx_phone` (`phone`) - dla szybkiego wyszukiwania po telefonie
- INDEX `idx_is_anonymous` (`is_anonymous`) - dla filtrowania klientów anonimowych
- INDEX `idx_created_at` (`created_at`)

**Przykładowe dane:**
```sql
-- Klient zidentyfikowany (pełne dane)
INSERT INTO clients (id, email, phone, first_name, last_name, is_anonymous, created_at, identified_at) VALUES 
('550e8400-e29b-41d4-a716-446655440001', 'jan.kowalski@example.com', '+48123456789', 'Jan', 'Kowalski', FALSE, NOW(), NOW());

-- Klient anonimowy (tylko email)
INSERT INTO clients (id, email, is_anonymous, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440002', 'anonim@example.com', TRUE, NOW());

-- Klient anonimowy (tylko telefon, zidentyfikowany później)
INSERT INTO clients (id, phone, first_name, last_name, is_anonymous, created_at, identified_at) VALUES 
('550e8400-e29b-41d4-a716-446655440003', '+48987654321', 'Anna', 'Nowak', FALSE, '2024-01-15 10:00:00', '2024-01-15 10:30:00');

-- Klient całkowicie anonimowy (brak danych kontaktowych)
INSERT INTO clients (id, is_anonymous, created_at) VALUES 
('550e8400-e29b-41d4-a716-446655440004', TRUE, NOW());
```

## Uwagi implementacyjne

1. **Relacja z modułem BackendForFrontend:**
   - Moduł Clients udostępnia serwisy (fasadę), które są używane przez moduł BackendForFrontend
   - Wszystkie endpointy HTTP są zaimplementowane w module BackendForFrontend, który wywołuje metody serwisu ClientService
   - Moduł BackendForFrontend odpowiada za walidację żądań i formatowanie odpowiedzi

2. **Relacja z modułem Tickets:**
   - Moduł Clients jest powiązany z modułem Tickets przez relację jeden-do-wielu (jeden klient może mieć wiele ticketów)
   - Ticket musi mieć przypisanego klienta (relacja wymagana)
   - Przy tworzeniu nowego ticketa, jeśli klient nie istnieje, należy go utworzyć (może być anonimowy)
   - Przy tworzeniu ticketa przez formularz na stronie, klient może być utworzony tylko z emailem

3. **Klienci anonimowi:**
   - Klienci anonimowi mogą być tworzeni bez pełnych danych (np. tylko email lub tylko telefon)
   - Podczas rozmowy telefonicznej lub w trakcie obsługi ticketa, dane anonimowego klienta mogą być uzupełniane
   - Identyfikacja klienta następuje przez metodę `identifyClient()` lub `identify()` na encji
   - Po identyfikacji, flaga `isAnonymous` jest ustawiana na `false`, a `identifiedAt` na aktualną datę

4. **Walidacja danych:**
   - Email: format email (walidacja przez `filter_var()` z `FILTER_VALIDATE_EMAIL`)
   - Telefon: format numeru telefonu (opcjonalnie z prefiksem kraju, np. +48XXXXXXXXX)
   - Imię i nazwisko: minimum 2 znaki, maksimum 100 znaków, tylko litery, spacje i znaki diakrytyczne
   - Co najmniej jedno pole kontaktowe (email lub telefon) powinno być wypełnione przy tworzeniu klienta

5. **Wyszukiwanie klientów:**
   - Wyszukiwanie po emailu jest priorytetowe (email jest unikalny)
   - Wyszukiwanie po telefonie może zwrócić wiele wyników (telefon nie jest unikalny)
   - Przy tworzeniu ticketa, system powinien sprawdzić, czy klient o danym emailu już istnieje
   - Jeśli klient istnieje, należy użyć istniejącego rekordu zamiast tworzyć nowy

6. **Bezpieczeństwo i prywatność:**
   - Klienci nie są autoryzowani w systemie (nie mają kont użytkownika)
   - Klienci mogą przeglądać tylko swoje własne tickety (identyfikacja przez email lub token)
   - Pracownicy mogą przeglądać dane klientów tylko w kontekście przypisanych do nich ticketów
   - Managerzy mogą przeglądać wszystkie dane klientów
   - Weryfikacja uprawnień powinna być wykonywana w module BackendForFrontend przed wywołaniem metod serwisu

7. **Rozproszony system:**
   - Przy projektowaniu należy uwzględnić możliwość przyszłego rozproszenia systemu
   - Klienci mogą być synchronizowani między serwisami przez eventy domenowe
   - Rozważyć cache'owanie często wyszukiwanych klientów w Redis
   - Rozważyć użycie eventów domenowych przy tworzeniu i aktualizacji klientów dla synchronizacji między serwisami

8. **Integracja z formularzem zgłoszeń:**
   - Formularz na stronie internetowej może tworzyć klienta tylko z emailem (anonimowy)
   - Po utworzeniu ticketa, klient może być zidentyfikowany przez pracownika podczas obsługi
   - Podczas rozmowy telefonicznej, pracownik może wyszukać istniejącego klienta lub utworzyć nowego

9. **Historia i audyt:**
   - Pole `createdAt` przechowuje datę pierwszego utworzenia klienta
   - Pole `updatedAt` jest automatycznie aktualizowane przy każdej zmianie danych
   - Pole `identifiedAt` przechowuje moment identyfikacji anonimowego klienta
   - Historia zmian może być rozszerzona w przyszłości o osobne logi zmian (audit trail)

10. **Wydajność:**
    - Indeksy na `email`, `phone`, `is_anonymous`, `created_at` dla szybkiego wyszukiwania
    - Rozważyć cache'owanie często wyszukiwanych klientów w Redis
    - Wyszukiwanie po emailu jest priorytetowe (email jest unikalny i ma indeks)
    - Wyszukiwanie po telefonie może zwrócić wiele wyników (telefon nie jest unikalny)

11. **Przypadki brzegowe:**
    - Klient może być utworzony bez żadnych danych kontaktowych (całkowicie anonimowy)
    - Klient może być utworzony tylko z emailem (anonimowy)
    - Klient może być utworzony tylko z telefonem (anonimowy)
    - Klient może być zidentyfikowany później (uzupełnienie danych anonimowego klienta)
    - Email klienta może być zmieniony (ale musi pozostać unikalny)
    - Klient może mieć wiele ticketów w różnych kategoriach
    - Przy tworzeniu ticketa, jeśli klient o danym emailu już istnieje, należy użyć istniejącego rekordu

