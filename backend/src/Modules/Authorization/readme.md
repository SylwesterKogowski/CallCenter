# Moduł autoryzacji pracowników

## Opis modułu

Moduł autoryzacji pracowników jest **modułem serwisowym (fasadą)**, który odpowiada za zarządzanie uprawnieniami dostępu pracowników do kategorii ticketów oraz określanie ich ról w systemie. Zajmuje się:

1. **Przypisywaniem uprawnień do kategorii** - określanie, do których kategorii ticketów (kolejek) pracownik ma dostęp
2. **Zarządzaniem rolą managera** - określanie, czy pracownik jest managerem w systemie
3. **Weryfikacją uprawnień** - sprawdzanie, czy pracownik ma dostęp do danej kategorii przed przeglądaniem i obsługą ticketów
4. **Zarządzaniem przypisaniami** - dodawanie, usuwanie i modyfikowanie przypisań pracowników do kategorii

Moduł ten jest odpowiedzialny za **autoryzację** (do jakich kategorii ma dostęp), natomiast **autentykacja** (kto jest zalogowany) jest obsługiwana przez moduł Authentication.

**Uwaga:** Moduł Authorization nie zawiera endpointów API. Endpointy HTTP są zaimplementowane w module **BackendForFrontend**, który korzysta z serwisów udostępnianych przez ten moduł.

## Warstwa serwisowa (Fasada)

Moduł udostępnia następujące serwisy, które mogą być używane przez inne moduły (w szczególności przez BackendForFrontend):

### AuthorizationService
Główny serwis autoryzacji, udostępniający metody:

- `getWorkerCategories(Worker $worker): array` - pobranie listy kategorii, do których pracownik ma dostęp
- `getWorkerPermissions(Worker $worker): array` - pobranie pełnych informacji o uprawnieniach pracownika (kategorie + rola managera)
- `assignCategoriesToWorker(Worker $worker, array $categoryIds, ?Worker $assignedBy = null): array` - przypisanie kategorii do pracownika
- `removeCategoryFromWorker(Worker $worker, TicketCategory $category): void` - usunięcie przypisania kategorii do pracownika
- `setManagerRole(Worker $worker, bool $isManager): void` - ustawienie lub usunięcie roli managera dla pracownika
- `canWorkerAccessCategory(Worker $worker, TicketCategory $category): bool` - sprawdzenie, czy pracownik ma dostęp do danej kategorii
- `isWorkerManager(Worker $worker): bool` - sprawdzenie, czy pracownik jest managerem

## Domenowa warstwa (Entities)

### WorkerCategoryAssignment (Przypisanie pracownika do kategorii)
Encja reprezentująca przypisanie pracownika do kategorii ticketów.

**Pola:**
- `worker` (Worker, ManyToOne, not null) - pracownik przypisany do kategorii
- `category` (TicketCategory, ManyToOne, not null) - kategoria, do której pracownik ma dostęp
- `assignedAt` (Carbon, not null) - data i czas przypisania
- `assignedBy` (Worker, ManyToOne, nullable) - pracownik, który dokonał przypisania (opcjonalne, dla audytu)

**Klucz główny:**
- Composite primary key składający się z `worker` i `category`

**Metody domenowe:**
- `assign(Worker $worker, TicketCategory $category, ?Worker $assignedBy = null): void` - przypisanie pracownika do kategorii
- `revoke(): void` - usunięcie przypisania

**Relacje:**
- ManyToOne z Worker (pracownik)
- ManyToOne z TicketCategory (kategoria z modułu TicketCategories)

**Reguły biznesowe:**
- Jeden pracownik może być przypisany do danej kategorii tylko raz (composite primary key)
- Pracownik musi istnieć w systemie przed przypisaniem do kategorii
- Kategoria musi istnieć w systemie przed przypisaniem do pracownika
- Przypisanie może być wykonane przez innego pracownika (dla audytu)

### WorkerRole (Rola pracownika)
Encja reprezentująca role pracownika w systemie (obecnie tylko rola managera).

**Pola:**
- `id` (UUID, primary key) - unikalny identyfikator roli
- `worker` (Worker, OneToOne, not null, unique) - pracownik
- `isManager` (bool, not null, default: false) - czy pracownik jest managerem
- `updatedAt` (Carbon, nullable) - data i czas ostatniej aktualizacji roli

**Metody domenowe:**
- `promoteToManager(): void` - nadanie roli managera
- `demoteFromManager(): void` - odebranie roli managera
- `isManager(): bool` - sprawdzenie, czy pracownik jest managerem

**Relacje:**
- OneToOne z Worker (pracownik)

**Reguły biznesowe:**
- Jeden pracownik może mieć tylko jedną rolę (OneToOne, unique constraint)
- Pracownik musi istnieć w systemie przed utworzeniem roli
- Domyślnie pracownik nie jest managerem (`isManager = false`)
- Rola managera może być nadana lub odebrana w dowolnym momencie

## Tabele bazy danych

### `worker_category_assignments`
Tabela przechowująca przypisania pracowników do kategorii ticketów.

**Kolumny:**
- `worker_id` UUID NOT NULL - identyfikator pracownika (FK do `workers.id`)
- `category_id` UUID NOT NULL - identyfikator kategorii (FK do `ticket_categories.id`)
- `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP - data przypisania
- `assigned_by_id` UUID NULL - identyfikator pracownika, który dokonał przypisania (FK do `workers.id`, opcjonalne)

**Indeksy:**
- PRIMARY KEY (`worker_id`, `category_id`) - jeden pracownik może być przypisany do danej kategorii tylko raz
- INDEX `idx_worker_id` (`worker_id`)
- INDEX `idx_category_id` (`category_id`)
- FOREIGN KEY `fk_worker_category_worker` (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
- FOREIGN KEY `fk_worker_category_category` (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE CASCADE
- FOREIGN KEY `fk_worker_category_assigned_by` (`assigned_by_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL

**Przykładowe dane:**
```sql
INSERT INTO worker_category_assignments (worker_id, category_id, assigned_at) VALUES 
('550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440001', NOW()),
('550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440002', NOW()),
('550e8400-e29b-41d4-a716-446655440003', '550e8400-e29b-41d4-a716-446655440001', NOW()),
('550e8400-e29b-41d4-a716-446655440003', '550e8400-e29b-41d4-a716-446655440004', NOW());
```

### `worker_roles`
Tabela przechowująca role pracowników w systemie.

**Kolumny:**
- `id` UUID PRIMARY KEY
- `worker_id` UUID NOT NULL UNIQUE - identyfikator pracownika (FK do `workers.id`)
- `is_manager` BOOLEAN NOT NULL DEFAULT FALSE - czy pracownik jest managerem
- `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP - data ostatniej aktualizacji

**Indeksy:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_worker_role` (`worker_id`) - jeden pracownik ma jedną rolę
- FOREIGN KEY `fk_worker_role_worker` (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE

**Przykładowe dane:**
```sql
INSERT INTO worker_roles (id, worker_id, is_manager) VALUES 
('550e8400-e29b-41d4-a716-446655440010', '550e8400-e29b-41d4-a716-446655440000', FALSE),
('550e8400-e29b-41d4-a716-446655440011', '550e8400-e29b-41d4-a716-446655440003', TRUE),
('550e8400-e29b-41d4-a716-446655440012', '550e8400-e29b-41d4-a716-446655440005', FALSE);
```

## Uwagi implementacyjne

1. **Relacja z modułem BackendForFrontend:**
   - Moduł Authorization udostępnia serwisy (fasadę), które są używane przez moduł BackendForFrontend
   - Wszystkie endpointy HTTP są zaimplementowane w module BackendForFrontend, który wywołuje metody serwisu AuthorizationService
   - Moduł BackendForFrontend odpowiada za walidację żądań, autentykację użytkownika i formatowanie odpowiedzi

2. **Relacja z modułem Authentication:**
   - Moduł Authorization korzysta z encji `Worker` z modułu Authentication
   - Worker musi istnieć w systemie przed przypisaniem mu uprawnień

3. **Relacja z modułem TicketCategories:**
   - Moduł Authorization korzysta z encji `TicketCategory` z modułu TicketCategories
   - Kategoria musi istnieć w systemie przed przypisaniem jej do pracownika

4. **Walidacja uprawnień:**
   - Przed przeglądaniem/obsługą ticketów z danej kategorii, system powinien sprawdzić uprawnienia przez `WorkerCategoryAssignment`
   - Managerzy mogą mieć dodatkowe uprawnienia (np. przeglądanie wszystkich kategorii, zarządzanie pracownikami)

5. **Rejestracja pracownika:**
   - Podczas rejestracji pracownika (moduł Authentication), checkboxy kategorii są przetwarzane przez moduł Authorization
   - Moduł Authorization tworzy odpowiednie wpisy w `worker_category_assignments` na podstawie wybranych kategorii

6. **Zarządzanie rolami:**
   - Rola managera jest przechowywana w osobnej tabeli `worker_roles` dla łatwiejszego rozszerzenia w przyszłości
   - Managerzy mogą mieć dostęp do wszystkich kategorii lub dodatkowych funkcji (np. moduł monitoringu)

7. **Bezpieczeństwo:**
   - Moduł Authorization nie zarządza autentykacją - to jest odpowiedzialność modułu Authentication
   - Tylko managerzy mogą modyfikować uprawnienia innych pracowników
   - Pracownicy mogą przeglądać tylko swoje własne uprawnienia (chyba że są managerami)
   - Weryfikacja uprawnień do modyfikacji powinna być wykonywana w module BackendForFrontend przed wywołaniem metod serwisu

8. **Rozproszony system:**
   - Przy projektowaniu należy uwzględnić możliwość przyszłego rozproszenia systemu
   - Uprawnienia mogą być cache'owane w Redis dla lepszej wydajności
   - Rozważyć użycie eventów domenowych przy zmianie uprawnień dla synchronizacji między serwisami

9. **Efektywność pracownika:**
   - Moduł Authorization nie zarządza efektywnością pracownika na kolejce - to jest w gestii modułu Tickets
   - Efektywność jest obliczana na podstawie historii ticketów, do których pracownik ma dostęp przez przypisania w tym module

