# Moduł ustawiania dostępności

## Opis modułu

Moduł ustawiania dostępności jest komponentem Reactowym, który umożliwia pracownikowi deklarowanie swojej dostępności w systemie Call Center. Moduł wyświetla najbliższe 7 dni i pozwala pracownikowi ustawić godziny, w których jest dostępny w poszczególnych dniach.

Pracownicy deklarują dostępność z tygodniowym wyprzedzeniem, ale jest ona zmienna i może się zmienić po wygenerowaniu grafiku. Pracownik może mieć wiele dostępności w jednym dniu (np. 9:00-12:00 i 14:00-17:00). Wszystkie zmiany są zapisywane na serwerze w czasie rzeczywistym.

## Funkcjonalność

1. **Wyświetlanie najbliższych 7 dni** - prezentacja kalendarza z najbliższymi 7 dniami
2. **Ustawianie godzin dostępności** - możliwość dodania, edycji i usunięcia przedziałów czasowych dostępności dla każdego dnia
3. **Wielokrotne przedziały czasowe** - możliwość ustawienia wielu przedziałów czasowych w jednym dniu
4. **Zapisywanie na serwerze** - automatyczne zapisywanie zmian dostępności na serwerze
5. **Walidacja przedziałów czasowych** - sprawdzanie poprawności ustawionych godzin (np. nie nakładające się przedziały, poprawna kolejność godzin)
6. **Wizualizacja dostępności** - czytelne wyświetlanie ustawionych przedziałów czasowych
7. **Kopiowanie dostępności** - możliwość skopiowania dostępności z jednego dnia na inne dni
8. **Szybkie szablony** - możliwość szybkiego ustawienia typowych godzin pracy (np. 9:00-17:00)

## Podkomponenty

### WorkerAvailability (Główny komponent dostępności)
Główny komponent modułu dostępności, który zarządza stanem dostępności pracownika i koordynuje wszystkie podkomponenty.

**Funkcjonalność:**
- Zarządzanie stanem dostępności dla najbliższych 7 dni
- Pobieranie aktualnej dostępności z API
- Zapisywanie zmian dostępności na serwerze
- Walidacja przedziałów czasowych
- Obsługa dodawania, edycji i usuwania przedziałów czasowych
- Zarządzanie stanem ładowania i błędów

**Props:**
- `workerId: string` - identyfikator zalogowanego pracownika

**State:**
```typescript
interface WorkerAvailabilityState {
  availability: DayAvailability[]; // dostępność dla najbliższych 7 dni
  selectedDay: string | null; // wybrany dzień do edycji (YYYY-MM-DD)
  isLoading: boolean;
  isSaving: boolean;
  error: string | null;
  validationErrors: ValidationError[]; // błędy walidacji
}
```

### AvailabilityCalendar (Kalendarz dostępności)
Komponent wyświetlający kalendarz z najbliższymi 7 dniami i ustawioną dostępnością.

**Funkcjonalność:**
- Wyświetlanie najbliższych 7 dni w formie kalendarza
- Wizualizacja ustawionych przedziałów czasowych dla każdego dnia
- Wyróżnienie dzisiejszego dnia
- Wyróżnienie dni z ustawioną dostępnością
- Interakcja z dniami (kliknięcie do edycji)
- Wyświetlanie podsumowania godzin dostępności dla każdego dnia

**Props:**
- `availability: DayAvailability[]` - dostępność dla najbliższych 7 dni
- `selectedDay: string | null` - wybrany dzień
- `onDaySelect: (date: string) => void` - funkcja wywoływana przy wyborze dnia
- `onDayEdit: (date: string) => void` - funkcja wywoływana przy edycji dnia

**Interfejsy:**
```typescript
interface DayAvailability {
  date: string; // YYYY-MM-DD
  timeSlots: TimeSlot[]; // przedziały czasowe dostępności
  totalHours: number; // suma godzin dostępności
}

interface TimeSlot {
  id: string;
  startTime: string; // HH:mm
  endTime: string; // HH:mm
}
```

### TimeSlotEditor (Edytor przedziałów czasowych)
Komponent umożliwiający dodawanie, edycję i usuwanie przedziałów czasowych dla wybranego dnia.

**Funkcjonalność:**
- Wyświetlanie listy przedziałów czasowych dla wybranego dnia
- Dodawanie nowego przedziału czasowego
- Edycja istniejącego przedziału czasowego (zmiana godzin rozpoczęcia i zakończenia)
- Usuwanie przedziału czasowego
- Walidacja przedziałów czasowych (nie nakładające się, poprawna kolejność)
- Wizualizacja przedziałów czasowych na osi czasu
- Sortowanie przedziałów czasowych według godziny rozpoczęcia

**Props:**
- `date: string` - data wybranego dnia (YYYY-MM-DD)
- `timeSlots: TimeSlot[]` - lista przedziałów czasowych
- `onTimeSlotAdd: (date: string, timeSlot: Omit<TimeSlot, 'id'>) => void` - funkcja dodawania przedziału
- `onTimeSlotUpdate: (date: string, timeSlotId: string, timeSlot: Partial<TimeSlot>) => void` - funkcja aktualizacji przedziału
- `onTimeSlotRemove: (date: string, timeSlotId: string) => void` - funkcja usuwania przedziału
- `validationErrors: ValidationError[]` - błędy walidacji

**Interfejsy:**
```typescript
interface ValidationError {
  timeSlotId: string | null; // null jeśli błąd dotyczy całego dnia
  field: 'startTime' | 'endTime' | 'overlap' | 'order';
  message: string;
}
```

### TimeSlotForm (Formularz przedziału czasowego)
Komponent formularza do dodawania lub edycji pojedynczego przedziału czasowego.

**Funkcjonalność:**
- Pola do wprowadzenia godziny rozpoczęcia i zakończenia
- Walidacja formatu czasu (HH:mm)
- Walidacja logiczna (godzina zakończenia po godzinie rozpoczęcia)
- Przyciski zapisu i anulowania
- Wyświetlanie błędów walidacji

**Props:**
- `timeSlot: TimeSlot | null` - przedział czasowy do edycji (null dla nowego)
- `onSave: (timeSlot: Omit<TimeSlot, 'id'>) => void` - funkcja zapisu
- `onCancel: () => void` - funkcja anulowania
- `errors: ValidationError[]` - błędy walidacji

### TimeSlotList (Lista przedziałów czasowych)
Komponent wyświetlający listę przedziałów czasowych dla wybranego dnia.

**Funkcjonalność:**
- Wyświetlanie wszystkich przedziałów czasowych w formie listy
- Wizualizacja przedziałów na osi czasu (timeline)
- Sortowanie przedziałów według godziny rozpoczęcia
- Wyróżnienie nakładających się przedziałów (błąd walidacji)
- Możliwość edycji i usunięcia każdego przedziału
- Wyświetlanie sumy godzin dostępności

**Props:**
- `timeSlots: TimeSlot[]` - lista przedziałów czasowych
- `onEdit: (timeSlotId: string) => void` - funkcja edycji przedziału
- `onRemove: (timeSlotId: string) => void` - funkcja usuwania przedziału
- `validationErrors: ValidationError[]` - błędy walidacji

### AvailabilityTimeline (Oś czasu dostępności)
Komponent wizualizujący przedziały czasowe na osi czasu (od 00:00 do 23:59).

**Funkcjonalność:**
- Wizualizacja przedziałów czasowych jako bloków na osi czasu
- Wyświetlanie godzin na osi
- Wyróżnienie nakładających się przedziałów
- Interakcja z przedziałami (kliknięcie do edycji)
- Wyświetlanie etykiet z godzinami dla każdego przedziału

**Props:**
- `timeSlots: TimeSlot[]` - lista przedziałów czasowych
- `onTimeSlotClick: (timeSlotId: string) => void` - funkcja wywoływana przy kliknięciu przedziału
- `validationErrors: ValidationError[]` - błędy walidacji

### QuickTemplates (Szybkie szablony)
Komponent umożliwiający szybkie ustawienie typowych godzin pracy.

**Funkcjonalność:**
- Predefiniowane szablony godzin pracy (np. 9:00-17:00, 8:00-16:00)
- Możliwość zastosowania szablonu do wybranego dnia lub wszystkich dni
- Możliwość utworzenia własnego szablonu
- Wyświetlanie podglądu szablonu przed zastosowaniem

**Props:**
- `onTemplateApply: (template: TimeSlotTemplate, dates: string[]) => void` - funkcja zastosowania szablonu
- `selectedDates: string[]` - wybrane daty

**Interfejsy:**
```typescript
interface TimeSlotTemplate {
  id: string;
  name: string;
  timeSlots: Omit<TimeSlot, 'id'>[];
}
```

### CopyAvailabilityButton (Przycisk kopiowania dostępności)
Komponent umożliwiający skopiowanie dostępności z jednego dnia na inne dni.

**Funkcjonalność:**
- Wybór dnia źródłowego (z którego kopiujemy)
- Wybór dni docelowych (na które kopiujemy)
- Podgląd dostępności przed kopiowaniem
- Weryfikacja, czy w dniach docelowych nie ma już ustawionej dostępności (z opcją nadpisania)

**Props:**
- `sourceDate: string` - data źródłowa
- `onCopy: (sourceDate: string, targetDates: string[], overwrite: boolean) => void` - funkcja kopiowania

## Integracja z API

Moduł komunikuje się z backendem przez następujące endpointy:

### GET /api/worker/availability
Pobranie dostępności pracownika dla najbliższych 7 dni.

**Odpowiedź (sukces):**
```json
{
  "availability": [
    {
      "date": "2024-01-15",
      "timeSlots": [
        {
          "id": "slot-1",
          "startTime": "09:00",
          "endTime": "12:00"
        },
        {
          "id": "slot-2",
          "startTime": "14:00",
          "endTime": "17:00"
        }
      ],
      "totalHours": 6
    },
    {
      "date": "2024-01-16",
      "timeSlots": [
        {
          "id": "slot-3",
          "startTime": "09:00",
          "endTime": "17:00"
        }
      ],
      "totalHours": 8
    }
  ]
}
```

### POST /api/worker/availability/{date}
Zapisanie dostępności pracownika dla konkretnego dnia.

**Request body:**
```json
{
  "timeSlots": [
    {
      "startTime": "09:00",
      "endTime": "12:00"
    },
    {
      "startTime": "14:00",
      "endTime": "17:00"
    }
  ]
}
```

**Odpowiedź (sukces):**
```json
{
  "date": "2024-01-15",
  "timeSlots": [
    {
      "id": "slot-1",
      "startTime": "09:00",
      "endTime": "12:00"
    },
    {
      "id": "slot-2",
      "startTime": "14:00",
      "endTime": "17:00"
    }
  ],
  "totalHours": 6,
  "updatedAt": "2024-01-14T10:30:00Z"
}
```

**Błędy walidacji (400):**
```json
{
  "errors": [
    {
      "field": "timeSlots[0].endTime",
      "message": "Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia"
    },
    {
      "field": "timeSlots",
      "message": "Przedziały czasowe nie mogą się nakładać"
    }
  ]
}
```

### PUT /api/worker/availability/{date}/time-slots/{timeSlotId}
Aktualizacja pojedynczego przedziału czasowego.

**Request body:**
```json
{
  "startTime": "10:00",
  "endTime": "13:00"
}
```

**Odpowiedź (sukces):**
```json
{
  "timeSlot": {
    "id": "slot-1",
    "startTime": "10:00",
    "endTime": "13:00"
  },
  "updatedAt": "2024-01-14T10:35:00Z"
}
```

### DELETE /api/worker/availability/{date}/time-slots/{timeSlotId}
Usunięcie pojedynczego przedziału czasowego.

**Odpowiedź (sukces):**
```json
{
  "message": "Przedział czasowy został usunięty",
  "deletedAt": "2024-01-14T10:40:00Z"
}
```

### POST /api/worker/availability/copy
Kopiowanie dostępności z jednego dnia na inne dni.

**Request body:**
```json
{
  "sourceDate": "2024-01-15",
  "targetDates": ["2024-01-16", "2024-01-17", "2024-01-18"],
  "overwrite": false
}
```

**Odpowiedź (sukces):**
```json
{
  "copied": [
    {
      "date": "2024-01-16",
      "timeSlots": [...]
    },
    {
      "date": "2024-01-17",
      "timeSlots": [...]
    },
    {
      "date": "2024-01-18",
      "timeSlots": [...]
    }
  ],
  "skipped": [] // daty, które zostały pominięte (jeśli overwrite=false i już miały dostępność)
}
```

## Walidacja przedziałów czasowych

Moduł waliduje przedziały czasowe zarówno po stronie klienta, jak i serwera:

1. **Format czasu** - godziny muszą być w formacie HH:mm (00:00 - 23:59)
2. **Kolejność godzin** - godzina zakończenia musi być późniejsza niż godzina rozpoczęcia
3. **Nakładanie się** - przedziały czasowe nie mogą się nakładać w tym samym dniu
4. **Minimalna długość** - przedział czasowy musi trwać co najmniej 15 minut (opcjonalnie)
5. **Maksymalna długość** - przedział czasowy nie może przekraczać 12 godzin (opcjonalnie)

Błędy walidacji są wyświetlane w czasie rzeczywistym podczas edycji.

## Zarządzanie stanem

Moduł zarządza następującymi stanami:

1. **Stan dostępności** - dane o dostępności dla najbliższych 7 dni
2. **Wybrany dzień** - dzień aktualnie edytowany
3. **Stan zapisywania** - informacja o trwającym zapisie na serwer
4. **Błędy walidacji** - komunikaty błędów walidacji przedziałów czasowych
5. **Błędy API** - komunikaty błędów z serwera
6. **Stan ładowania** - informacja o trwających żądaniach API

## Zapisywanie zmian

Zmiany dostępności są zapisywane na serwerze w następujących sytuacjach:

1. **Automatyczne zapisywanie** - po zakończeniu edycji przedziału czasowego (debounce ~500ms)
2. **Ręczne zapisywanie** - po kliknięciu przycisku "Zapisz"
3. **Kopiowanie dostępności** - natychmiast po zastosowaniu kopiowania
4. **Zastosowanie szablonu** - natychmiast po zastosowaniu szablonu

Moduł wyświetla wskaźnik zapisywania podczas trwania operacji zapisu.

## Uwagi implementacyjne

1. **Wydajność:**
   - Moduł powinien być zoptymalizowany pod kątem renderowania 7 dni z wieloma przedziałami czasowymi
   - Debounce dla automatycznego zapisywania, aby uniknąć zbyt wielu żądań API
   - Lazy loading komponentów, jeśli moduł jest duży
   - Minimalizacja liczby re-renderów

2. **UX:**
   - Intuicyjny interfejs dodawania i edycji przedziałów czasowych
   - Wizualna reprezentacja przedziałów na osi czasu
   - Czytelne wyświetlanie błędów walidacji
   - Responsywny design (działa na urządzeniach mobilnych)
   - Wyświetlanie wskaźników ładowania podczas operacji
   - Potwierdzenie przed usunięciem przedziału czasowego

3. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy walidacji powinny być wyświetlane w kontekście edytowanego przedziału
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby
   - Zachowanie lokalnych zmian w przypadku błędu zapisu (z możliwością ponowienia)

4. **Integracja z routingiem:**
   - Moduł powinien być dostępny pod ścieżką `/worker/availability`
   - Moduł powinien być chroniony przed dostępem dla nieautoryzowanych użytkowników
   - Przekierowanie do modułu grafika po zapisaniu dostępności (opcjonalnie)

5. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API)
   - Testy jednostkowe dla logiki walidacji przedziałów czasowych
   - Testy integracyjne dla procesu dodawania, edycji i usuwania przedziałów
   - Testy dla obsługi błędów walidacji i API

6. **Format czasu:**
   - Użycie formatu 24-godzinnego (HH:mm)
   - Obsługa różnych stref czasowych (jeśli potrzebne)
   - Walidacja formatu czasu w polach formularza

