# Moduł kategorii ticketów

## Opis modułu

Moduł kategorii ticketów jest **modułem serwisowym (fasadą)**, który odpowiada za zarządzanie kategoriami ticketów (kolejkami tematycznymi) w systemie Call Center. Zajmuje się:

1. **Zarządzaniem kategoriami ticketów** - definiowanie różnych kolejek tematycznych (np. sprzedaż, wsparcie techniczne, reklamacje)
2. **Przechowywaniem domyślnego czasu rozwiązania** - określanie domyślnego czasu rozwiązania ticketa per kategoria w minutach
3. **Udostępnianiem danych kategorii** - dostarczanie informacji o kategoriach dla innych modułów systemu

**Uwaga:** Moduł TicketCategories nie zawiera endpointów API. Endpointy HTTP są zaimplementowane w module **BackendForFrontend**, który korzysta z serwisów udostępnianych przez ten moduł.

**Uwaga:** Moduł TicketCategories nie korzysta z bazy danych. Wszystkie dane (kategorie i domyślne czasy rozwiązania) są zhardkodowane w kodzie.

## Warstwa serwisowa (Fasada)

Moduł udostępnia następujące serwisy, które mogą być używane przez inne moduły (w szczególności przez BackendForFrontend):

### TicketCategoryService
Główny serwis kategorii ticketów, udostępniający metody:

- `getAllCategories(): array` - pobranie listy wszystkich dostępnych kategorii ticketów
- `getCategoryById(string $id): ?TicketCategory` - pobranie kategorii po ID
- `getCategoryByName(string $name): ?TicketCategory` - wyszukanie kategorii po nazwie
- `getDefaultResolutionTime(string $categoryId): int` - pobranie domyślnego czasu rozwiązania ticketa dla danej kategorii w minutach
- `categoryExists(string $categoryId): bool` - sprawdzenie, czy kategoria o danym ID istnieje
- `getCategoriesByIds(array $categoryIds): array` - pobranie wielu kategorii po ich ID

## Domenowa warstwa (Entities)

### TicketCategory (Kategoria ticketów)
Główna encja reprezentująca kategorię ticketów w systemie.

**Pola:**
- `id` (string, not null) - unikalny identyfikator kategorii (zhardkodowany)
- `name` (string, not null) - nazwa kategorii (np. "Sprzedaż", "Wsparcie techniczne")
- `description` (string, nullable) - opis kategorii (opcjonalny)
- `defaultResolutionTimeMinutes` (int, not null) - domyślny czas rozwiązania ticketa w tej kategorii w minutach

**Metody domenowe:**
- `getId(): string` - pobranie ID kategorii
- `getName(): string` - pobranie nazwy kategorii
- `getDescription(): ?string` - pobranie opisu kategorii
- `getDefaultResolutionTimeMinutes(): int` - pobranie domyślnego czasu rozwiązania w minutach

**Relacje:**
- Relacja z modułem Tickets (Ticket) - jeden do wielu (kategoria może mieć wiele ticketów)
- Relacja z modułem Authorization (WorkerCategoryAssignment) - wiele do wielu z pracownikami

**Reguły biznesowe:**
- Każda kategoria musi mieć unikalne ID
- Każda kategoria musi mieć unikalną nazwę
- Domyślny czas rozwiązania musi być większy od 0
- Kategoria nie może być usunięta, jeśli istnieją przypisane do niej tickety (walidacja w innych modułach)

## Zhardkodowane dane

Moduł zawiera zhardkodowane dane kategorii ticketów. Wszystkie kategorie są zdefiniowane w kodzie jako stałe lub w konfiguracji.

### Lista kategorii ticketów

```php
[
    [
        'id' => '550e8400-e29b-41d4-a716-446655440001',
        'name' => 'Sprzedaż',
        'description' => 'Kategoria dla ticketów związanych ze sprzedażą produktów i usług',
        'defaultResolutionTimeMinutes' => 30
    ],
    [
        'id' => '550e8400-e29b-41d4-a716-446655440002',
        'name' => 'Wsparcie techniczne',
        'description' => 'Kategoria dla ticketów związanych z problemami technicznymi i wsparciem',
        'defaultResolutionTimeMinutes' => 45
    ],
    [
        'id' => '550e8400-e29b-41d4-a716-446655440003',
        'name' => 'Reklamacje',
        'description' => 'Kategoria dla ticketów związanych z reklamacjami klientów',
        'defaultResolutionTimeMinutes' => 60
    ],
    [
        'id' => '550e8400-e29b-41d4-a716-446655440004',
        'name' => 'Faktury i płatności',
        'description' => 'Kategoria dla ticketów związanych z fakturami i płatnościami',
        'defaultResolutionTimeMinutes' => 20
    ],
    [
        'id' => '550e8400-e29b-41d4-a716-446655440005',
        'name' => 'Instalacje i serwis',
        'description' => 'Kategoria dla ticketów związanych z instalacjami i serwisem',
        'defaultResolutionTimeMinutes' => 90
    ]
]
```

### Domyślne czasy rozwiązania per kategoria

- **Sprzedaż**: 30 minut
- **Wsparcie techniczne**: 45 minut
- **Reklamacje**: 60 minut
- **Faktury i płatności**: 20 minut
- **Instalacje i serwis**: 90 minut

## Uwagi implementacyjne

1. **Relacja z modułem BackendForFrontend:**
   - Moduł TicketCategories udostępnia serwisy (fasadę), które są używane przez moduł BackendForFrontend
   - Wszystkie endpointy HTTP są zaimplementowane w module BackendForFrontend, który wywołuje metody serwisu TicketCategoryService
   - Moduł BackendForFrontend odpowiada za walidację żądań i formatowanie odpowiedzi

2. **Relacja z modułem Tickets:**
   - Moduł Tickets korzysta z kategorii z tego modułu
   - Każdy ticket musi być przypisany do kategorii
   - Domyślny czas rozwiązania z kategorii jest używany do przewidywania ilości ticketów, które pracownik może obsłużyć

3. **Relacja z modułem Authorization:**
   - Moduł Authorization przypisuje pracowników do kategorii z tego modułu
   - Pracownicy mogą mieć dostęp do wielu kategorii
   - Weryfikacja uprawnień do kategorii jest wykonywana w module Authorization

4. **Relacja z modułem WorkerSchedule:**
   - Moduł WorkerSchedule używa domyślnego czasu rozwiązania z kategorii do automatycznego przypisywania ticketów do pracowników
   - Przewidywana ilość ticketów, które pracownik może obsłużyć danego dnia, jest obliczana na podstawie domyślnego czasu rozwiązania

5. **Zhardkodowane dane:**
   - Wszystkie kategorie są zdefiniowane w kodzie jako stałe lub w pliku konfiguracyjnym
   - Dane mogą być przechowywane w klasie konfiguracyjnej, pliku YAML/JSON lub jako stałe w klasie serwisu
   - W przyszłości można rozważyć przeniesienie danych do bazy danych, ale na razie pozostają zhardkodowane

6. **Walidacja:**
   - ID kategorii musi być unikalne
   - Nazwa kategorii musi być unikalna
   - Domyślny czas rozwiązania musi być większy od 0
   - Próba pobrania nieistniejącej kategorii powinna zwracać null lub rzucać wyjątek

7. **Rozproszony system:**
   - Przy projektowaniu należy uwzględnić możliwość przyszłego rozproszenia systemu
   - Kategorie mogą być cache'owane w Redis dla lepszej wydajności
   - Rozważyć użycie eventów domenowych przy zmianie kategorii (jeśli w przyszłości będą edytowalne)

8. **Efektywność i wydajność:**
   - Ponieważ dane są zhardkodowane, wszystkie operacje są bardzo szybkie (brak zapytań do bazy danych)
   - Lista kategorii może być cache'owana w pamięci aplikacji
   - Wyszukiwanie po ID lub nazwie powinno być zoptymalizowane (np. przez użycie tablicy asocjacyjnej)

9. **Rozszerzalność:**
   - W przyszłości można rozważyć przeniesienie kategorii do bazy danych
   - Struktura modułu powinna umożliwiać łatwe dodanie nowych kategorii w kodzie
   - Można rozważyć dodanie dodatkowych pól do kategorii (np. priorytet, kolor, ikona)

10. **Integracja z innymi modułami:**
    - Moduł Tickets używa kategorii do przypisywania ticketów
    - Moduł Authorization używa kategorii do przypisywania uprawnień pracownikom
    - Moduł WorkerSchedule używa domyślnego czasu rozwiązania do planowania pracy
    - Moduł BackendForFrontend udostępnia endpointy do pobierania listy kategorii dla frontendu

