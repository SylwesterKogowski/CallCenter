# Moduł przypisania/planowania ticketów

## Opis modułu

Moduł przypisania/planowania ticketów jest komponentem Reactowym, który umożliwia pracownikowi planowanie i przypisywanie ticketów z backlogu na najbliższy tydzień. Moduł wyświetla backlog ticketów spośród kategorii, do których pracownik ma dostęp, oraz umożliwia ręczne lub automatyczne przypisanie ticketów na poszczególne dostępne dni.

Moduł pokazuje przewidywaną ilość ticketów, którą pracownik może obsłużyć danego dnia, bazując na jego dostępności, efektywności oraz domyślnym czasie rozwiązania z kategorii. Pracownik może ręcznie przypisać tickety na konkretne dni lub skorzystać z funkcji automatycznego dopisania ticketów do wszystkich dni na podstawie przewidywanej ilości obsługiwanych ticketów.

## Funkcjonalność

1. **Wyświetlanie backlogu ticketów** - prezentacja listy ticketów oczekujących na przypisanie, filtrowanych według kategorii dostępnych dla pracownika
2. **Wyświetlanie najbliższego tygodnia** - prezentacja 7 najbliższych dni z dostępnością pracownika
3. **Przewidywana ilość ticketów** - wyświetlanie przewidywanej ilości ticketów, którą pracownik może obsłużyć danego dnia
4. **Ręczne przypisanie ticketów** - możliwość przeciągnięcia lub wybrania ticketa i przypisania go do konkretnego dnia
5. **Automatyczne przypisanie ticketów** - funkcja automatycznego dopisania ticketów do wszystkich dni na podstawie przewidywanej ilości obsługiwanych ticketów
6. **Filtrowanie i sortowanie** - możliwość filtrowania ticketów według kategorii, statusu, priorytetu oraz sortowania
7. **Podgląd przypisanych ticketów** - wyświetlanie już przypisanych ticketów na poszczególne dni
8. **Edycja przypisań** - możliwość usunięcia przypisania ticketa lub przeniesienia go na inny dzień

## Podkomponenty

### TicketPlanning (Główny komponent planowania)
Główny komponent modułu planowania, który zarządza stanem backlogu, grafika tygodniowego i koordynuje wszystkie podkomponenty.

**Funkcjonalność:**
- Zarządzanie stanem backlogu ticketów
- Zarządzanie stanem grafika tygodniowego (7 najbliższych dni)
- Pobieranie danych backlogu i grafika z API
- Obsługa ręcznego przypisania ticketów
- Obsługa automatycznego przypisania ticketów
- Zarządzanie filtrowaniem i sortowaniem ticketów
- Obsługa usuwania i przenoszenia przypisań

**Props:**
- `workerId: string` - identyfikator zalogowanego pracownika

**State:**
```typescript
interface TicketPlanningState {
  backlog: Ticket[]; // lista ticketów oczekujących na przypisanie
  weekSchedule: WeekScheduleDay[]; // 7 najbliższych dni z przypisanymi ticketami
  selectedDay: string | null; // wybrany dzień (YYYY-MM-DD)
  filters: TicketFilters; // filtry dla backlogu
  sortBy: SortOption; // opcja sortowania
  isLoading: boolean;
  error: string | null;
  predictions: DayPrediction[]; // przewidywana ilość ticketów na każdy dzień
}
```

### TicketBacklog (Backlog ticketów)
Komponent wyświetlający listę ticketów oczekujących na przypisanie.

**Funkcjonalność:**
- Wyświetlanie listy ticketów z backlogu
- Filtrowanie ticketów według kategorii, statusu, priorytetu
- Sortowanie ticketów (według daty utworzenia, priorytetu, kategorii)
- Wyszukiwanie ticketów
- Wyświetlanie szczegółów każdego ticketa (tytuł, kategoria, klient, czas rozwiązania)
- Możliwość wyboru ticketa do przypisania
- Wyróżnienie ticketów już przypisanych (jeśli są widoczne w backlogu)

**Props:**
- `tickets: Ticket[]` - lista ticketów z backlogu
- `filters: TicketFilters` - aktywne filtry
- `sortBy: SortOption` - opcja sortowania
- `onTicketSelect: (ticket: Ticket) => void` - funkcja wywoływana przy wyborze ticketa
- `onFiltersChange: (filters: TicketFilters) => void` - funkcja zmiany filtrów
- `onSortChange: (sortBy: SortOption) => void` - funkcja zmiany sortowania

**Interfejsy:**
```typescript
interface Ticket {
  id: string;
  title: string;
  category: TicketCategory;
  status: TicketStatus;
  priority: TicketPriority;
  client: Client;
  estimatedTime: number; // w minutach (domyślny czas z kategorii)
  createdAt: string;
  scheduledDate: string | null; // null jeśli nieprzypisany
}

interface TicketCategory {
  id: string;
  name: string;
  defaultResolutionTime: number; // w minutach
}

interface TicketFilters {
  categories: string[]; // ID kategorii
  statuses: TicketStatus[];
  priorities: TicketPriority[];
  searchQuery: string;
}

type SortOption = 'created_at' | 'priority' | 'category' | 'estimated_time';
```

### WeekScheduleView (Widok grafika tygodniowego)
Komponent wyświetlający najbliższe 7 dni z przypisanymi ticketami i przewidywaną ilością ticketów.

**Funkcjonalność:**
- Wyświetlanie 7 najbliższych dni
- Wyświetlanie dostępności pracownika w każdym dniu
- Wyświetlanie przypisanych ticketów dla każdego dnia
- Wyświetlanie przewidywanej ilości ticketów na każdy dzień
- Wyróżnienie dzisiejszego dnia
- Wyróżnienie dni z dostępnością pracownika
- Możliwość wyboru dnia do przypisania ticketa
- Obsługa przeciągania i upuszczania ticketów (drag & drop)

**Props:**
- `weekSchedule: WeekScheduleDay[]` - dane grafika dla 7 dni
- `predictions: DayPrediction[]` - przewidywania dla każdego dnia
- `selectedDay: string | null` - wybrany dzień
- `onDaySelect: (date: string) => void` - funkcja wyboru dnia
- `onTicketAssign: (ticketId: string, date: string) => void` - funkcja przypisania ticketa
- `onTicketUnassign: (ticketId: string, date: string) => void` - funkcja usunięcia przypisania

**Interfejsy:**
```typescript
interface WeekScheduleDay {
  date: string; // YYYY-MM-DD
  isAvailable: boolean; // czy pracownik jest dostępny w tym dniu
  availabilityHours: AvailabilitySlot[]; // godziny dostępności
  tickets: ScheduledTicket[];
  totalEstimatedTime: number; // suma czasu wszystkich ticketów w minutach
}

interface AvailabilitySlot {
  startTime: string; // HH:mm
  endTime: string; // HH:mm
}

interface ScheduledTicket {
  id: string;
  title: string;
  category: TicketCategory;
  estimatedTime: number;
  priority: TicketPriority;
}

interface DayPrediction {
  date: string; // YYYY-MM-DD
  predictedTicketCount: number; // przewidywana ilość ticketów
  availableTime: number; // dostępny czas w minutach
  efficiency: number; // efektywność pracownika w danej kategorii (średnia)
}
```

### TicketCard (Karta ticketa)
Komponent wyświetlający pojedynczy ticket w backlogu lub w grafiku.

**Funkcjonalność:**
- Wyświetlanie podstawowych informacji o tickecie (tytuł, kategoria, priorytet)
- Wyświetlanie przewidywanego czasu rozwiązania
- Wyświetlanie informacji o kliencie
- Wyróżnienie wizualne według priorytetu
- Możliwość przeciągnięcia ticketa (drag & drop)
- Możliwość kliknięcia do wyświetlenia szczegółów

**Props:**
- `ticket: Ticket | ScheduledTicket` - dane ticketa
- `isAssigned: boolean` - czy ticket jest już przypisany
- `onClick?: (ticket: Ticket) => void` - funkcja wywoływana przy kliknięciu
- `isDraggable?: boolean` - czy ticket może być przeciągany

### DayColumn (Kolumna dnia)
Komponent wyświetlający pojedynczy dzień w grafiku tygodniowym.

**Funkcjonalność:**
- Wyświetlanie daty i dnia tygodnia
- Wyświetlanie dostępności pracownika
- Wyświetlanie przypisanych ticketów
- Wyświetlanie przewidywanej ilości ticketów
- Obsługa przeciągania i upuszczania ticketów
- Wyróżnienie wybranego dnia
- Wyróżnienie dzisiejszego dnia

**Props:**
- `day: WeekScheduleDay` - dane dnia
- `prediction: DayPrediction` - przewidywania dla dnia
- `isSelected: boolean` - czy dzień jest wybrany
- `isToday: boolean` - czy to dzisiejszy dzień
- `onTicketDrop: (ticketId: string) => void` - funkcja obsługi upuszczenia ticketa
- `onTicketRemove: (ticketId: string) => void` - funkcja usunięcia przypisania

### AutoAssignButton (Przycisk automatycznego przypisania)
Komponent przycisku do automatycznego przypisania ticketów.

**Funkcjonalność:**
- Wyświetlanie przycisku do automatycznego przypisania
- Wyświetlanie dialogu potwierdzenia przed wykonaniem akcji
- Wyświetlanie stanu ładowania podczas przetwarzania
- Wyświetlanie informacji o przewidywanej ilości przypisanych ticketów

**Props:**
- `onAutoAssign: () => void` - funkcja wywoływana przy kliknięciu
- `isLoading: boolean` - czy trwa automatyczne przypisanie
- `predictions: DayPrediction[]` - przewidywania dla każdego dnia

### TicketFiltersPanel (Panel filtrów)
Komponent wyświetlający panel z filtrami i opcjami sortowania.

**Funkcjonalność:**
- Filtrowanie według kategorii (checkboxy lub multi-select)
- Filtrowanie według statusu
- Filtrowanie według priorytetu
- Wyszukiwanie tekstowe
- Sortowanie (dropdown)
- Resetowanie filtrów

**Props:**
- `filters: TicketFilters` - aktywne filtry
- `sortBy: SortOption` - aktualne sortowanie
- `availableCategories: TicketCategory[]` - dostępne kategorie
- `onFiltersChange: (filters: TicketFilters) => void` - funkcja zmiany filtrów
- `onSortChange: (sortBy: SortOption) => void` - funkcja zmiany sortowania

### PredictionDisplay (Wyświetlanie przewidywań)
Komponent wyświetlający przewidywaną ilość ticketów dla każdego dnia.

**Funkcjonalność:**
- Wyświetlanie przewidywanej ilości ticketów na każdy dzień
- Wyświetlanie dostępnego czasu w każdym dniu
- Wyświetlanie efektywności pracownika
- Wizualne wskaźniki (np. pasek postępu, kolory)
- Wyświetlanie różnicy między przewidywaną a przypisaną ilością ticketów

**Props:**
- `predictions: DayPrediction[]` - przewidywania dla każdego dnia
- `weekSchedule: WeekScheduleDay[]` - aktualny grafik

## Integracja z API

Moduł komunikuje się z backendem przez następujące endpointy:

### GET /api/worker/tickets/backlog
Pobranie backlogu ticketów oczekujących na przypisanie dla pracownika.

**Query parameters:**
- `categories` (opcjonalne) - filtry kategorii (comma-separated IDs)
- `statuses` (opcjonalne) - filtry statusów (comma-separated)
- `priorities` (opcjonalne) - filtry priorytetów (comma-separated)
- `search` (opcjonalne) - wyszukiwanie tekstowe
- `sort` (opcjonalne) - opcja sortowania

**Odpowiedź (sukces):**
```json
{
  "tickets": [
    {
      "id": "ticket-123",
      "title": "Problem z połączeniem",
      "category": {
        "id": "cat-1",
        "name": "Wsparcie techniczne",
        "defaultResolutionTime": 60
      },
      "status": "waiting",
      "priority": "high",
      "client": {
        "id": "client-456",
        "name": "Jan Kowalski"
      },
      "estimatedTime": 60,
      "createdAt": "2024-01-15T10:00:00Z",
      "scheduledDate": null
    }
  ],
  "total": 25
}
```

### GET /api/worker/schedule/week
Pobranie grafika pracownika dla najbliższych 7 dni.

**Odpowiedź (sukces):**
```json
{
  "schedule": [
    {
      "date": "2024-01-15",
      "isAvailable": true,
      "availabilityHours": [
        {
          "startTime": "09:00",
          "endTime": "17:00"
        }
      ],
      "tickets": [
        {
          "id": "ticket-123",
          "title": "Problem z połączeniem",
          "category": {
            "id": "cat-1",
            "name": "Wsparcie techniczne"
          },
          "estimatedTime": 60,
          "priority": "high"
        }
      ],
      "totalEstimatedTime": 60
    }
  ]
}
```

### GET /api/worker/schedule/predictions
Pobranie przewidywań ilości ticketów dla najbliższych 7 dni.

**Odpowiedź (sukces):**
```json
{
  "predictions": [
    {
      "date": "2024-01-15",
      "predictedTicketCount": 8,
      "availableTime": 480,
      "efficiency": 0.85
    }
  ]
}
```

### POST /api/worker/schedule/assign
Przypisanie ticketa do konkretnego dnia.

**Request body:**
```json
{
  "ticketId": "ticket-123",
  "date": "2024-01-15"
}
```

**Odpowiedź (sukces):**
```json
{
  "assignment": {
    "ticketId": "ticket-123",
    "date": "2024-01-15",
    "assignedAt": "2024-01-15T10:30:00Z"
  }
}
```

### DELETE /api/worker/schedule/assign
Usunięcie przypisania ticketa z dnia.

**Request body:**
```json
{
  "ticketId": "ticket-123",
  "date": "2024-01-15"
}
```

**Odpowiedź (sukces):**
```json
{
  "success": true
}
```

### POST /api/worker/schedule/auto-assign
Automatyczne przypisanie ticketów do wszystkich dni na podstawie przewidywanej ilości obsługiwanych ticketów.

**Request body:**
```json
{
  "weekStartDate": "2024-01-15",
  "categories": ["cat-1", "cat-2"] // opcjonalne, jeśli puste to wszystkie dostępne kategorie
}
```

**Odpowiedź (sukces):**
```json
{
  "assignments": [
    {
      "ticketId": "ticket-123",
      "date": "2024-01-15"
    },
    {
      "ticketId": "ticket-124",
      "date": "2024-01-15"
    }
  ],
  "totalAssigned": 15
}
```

## Przewidywana ilość ticketów

Moduł wyświetla przewidywaną ilość ticketów, którą pracownik może obsłużyć danego dnia. Przewidywanie bazuje na:

1. **Dostępności pracownika** - godziny, w których pracownik jest dostępny w danym dniu
2. **Efektywności pracownika** - średnia efektywność pracownika w kategoriach, do których ma dostęp (bazująca na historii ticketów)
3. **Domyślnym czasie rozwiązania** - domyślny czas rozwiązania ticketa z kategorii

**Wzór przewidywania:**
```
przewidywana_ilość_ticketów = (dostępny_czas_w_minutach * efektywność) / średni_czas_rozwiązania_ticketa
```

Przewidywania są obliczane przez backend i zwracane przez endpoint `/api/worker/schedule/predictions`.

## Automatyczne przypisanie ticketów

Funkcja automatycznego przypisania ticketów dopisuje tickety do wszystkich dni na podstawie przewidywanej ilości obsługiwanych ticketów. Algorytm:

1. Dla każdego dnia z dostępnością pracownika:
   - Pobiera przewidywaną ilość ticketów dla tego dnia
   - Sprawdza, ile ticketów jest już przypisanych
   - Oblicza różnicę (ile ticketów jeszcze trzeba przypisać)
   - Wybiera tickety z backlogu (zgodnie z filtrami i sortowaniem)
   - Przypisuje tickety do dnia

2. Priorytetyzacja:
   - Tickety są przypisywane zgodnie z priorytetem (wyższy priorytet = wcześniej)
   - Tickety są przypisywane zgodnie z datą utworzenia (starsze = wcześniej)
   - Uwzględniane są filtry aktywne w panelu filtrów

3. Ograniczenia:
   - Nie przypisuje więcej ticketów niż przewidywana ilość
   - Nie przypisuje ticketów do dni bez dostępności pracownika
   - Nie przypisuje już przypisanych ticketów

## Zarządzanie stanem

Moduł zarządza następującymi stanami:

1. **Stan backlogu** - lista ticketów oczekujących na przypisanie
2. **Stan grafika tygodniowego** - dane o 7 najbliższych dniach z przypisanymi ticketami
3. **Filtry i sortowanie** - aktywne filtry i opcja sortowania
4. **Wybrany dzień** - dzień, do którego użytkownik chce przypisać ticket
5. **Przewidywania** - przewidywana ilość ticketów dla każdego dnia
6. **Stan ładowania** - informacja o trwających żądaniach API
7. **Błędy** - komunikaty błędów z API

## Ręczne przypisanie ticketów

Pracownik może ręcznie przypisać tickety na konkretne dni na kilka sposobów:

1. **Drag & Drop** - przeciągnięcie ticketa z backlogu do kolumny dnia
2. **Kliknięcie i wybór** - kliknięcie ticketa i wybór dnia z listy
3. **Wybór dnia i ticketa** - wybór dnia, a następnie wybór ticketa z backlogu

Po przypisaniu ticketa:
- Ticket jest usuwany z backlogu (lub oznaczany jako przypisany)
- Ticket pojawia się w grafiku danego dnia
- Aktualizowane są statystyki dnia (suma czasu, ilość ticketów)

## Edycja przypisań

Pracownik może edytować przypisania ticketów:

1. **Usunięcie przypisania** - usunięcie ticketa z dnia (ticket wraca do backlogu)
2. **Przeniesienie ticketa** - przeniesienie ticketa z jednego dnia na inny (drag & drop lub wybór)
3. **Zmiana kolejności** - zmiana kolejności ticketów w dniu (opcjonalnie)

## Uwagi implementacyjne

1. **Synchronizacja danych:**
   - Moduł powinien automatycznie odświeżać dane po przypisaniu/usunięciu ticketa
   - Backlog powinien być aktualizowany po każdej zmianie
   - Grafik tygodniowy powinien być aktualizowany po każdej zmianie

2. **Wydajność:**
   - Moduł powinien być zoptymalizowany pod kątem renderowania dużej liczby ticketów
   - Lazy loading komponentów, jeśli moduł jest duży
   - Wirtualizacja listy ticketów w backlogu (jeśli jest dużo ticketów)
   - Minimalizacja liczby re-renderów
   - Debouncing dla wyszukiwania i filtrów

3. **UX:**
   - Intuicyjny interfejs drag & drop
   - Wyraźne wizualne wyróżnienie przypisanych ticketów
   - Czytelne wyświetlanie przewidywań
   - Responsywny design (działa na urządzeniach mobilnych)
   - Wyświetlanie wskaźników ładowania podczas operacji
   - Potwierdzenie przed automatycznym przypisaniem (dialog)
   - Informacja zwrotna po każdej akcji (sukces/błąd)

4. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby
   - Walidacja przed przypisaniem (np. czy pracownik jest dostępny w danym dniu)
   - Obsługa konfliktów (np. jeśli ticket został już przypisany przez innego użytkownika)

5. **Integracja z routingiem:**
   - Moduł powinien być dostępny pod ścieżką `/worker/ticket-planning` lub `/worker/planning`
   - Moduł powinien być chroniony przed dostępem dla nieautoryzowanych użytkowników
   - Tylko pracownicy z dostępem do kategorii mogą przypisywać tickety z tych kategorii

6. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API)
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu przypisania ticketów
   - Testy dla funkcji automatycznego przypisania
   - Testy dla drag & drop

7. **Filtrowanie i sortowanie:**
   - Filtry powinny być zapisywane w localStorage (opcjonalnie)
   - Filtry powinny być resetowane przy przeładowaniu strony (lub zapisane, w zależności od wymagań)
   - Sortowanie powinno być intuicyjne i szybkie

8. **Backlog vs przypisane tickety:**
   - Backlog powinien zawierać tylko nieprzypisane tickety (lub tickety przypisane do innych dni)
   - Tickety przypisane do bieżącego tygodnia nie powinny być widoczne w backlogu (lub powinny być oznaczone)
   - Możliwość wyświetlenia wszystkich ticketów (przypisanych i nieprzypisanych) w trybie podglądu

