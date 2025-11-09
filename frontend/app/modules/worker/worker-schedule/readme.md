# Moduł grafika pracownika

## Opis modułu

Moduł grafika pracownika jest komponentem Reactowym, który umożliwia pracownikowi planowanie i zarządzanie ticketami przypisanymi do niego w systemie Call Center. Moduł stanowi główne centrum pracy dla pracownika i jest pierwszą stroną, którą zobaczy po zalogowaniu.

Moduł wyświetla aktualny dzień oraz następny dzień, w którym pracownik jest dostępny, wraz z zaplanowanymi ticketami i umożliwia pracownikowi wybór jednego ticketa, nad którym aktualnie pracuje, spośród tych, które zostały mu zaplanowane. Moduł odbiera na bieżąco zmiany w planingu poprzez Server-Sent Events (SSE), zapewniając synchronizację danych w czasie rzeczywistym.

## Funkcjonalność

1. **Wyświetlanie grafika** - prezentacja aktualnego dnia oraz następnego dnia dostępności pracownika z przypisanymi ticketami
2. **Wybór aktywnego ticketa** - możliwość wybrania jednego ticketa, nad którym pracownik aktualnie pracuje
3. **Śledzenie czasu** - wyświetlanie czasu spędzonego na ticketach dzisiaj oraz rejestrowanie czasu dla ticketów w statusie 'w toku'
4. **Zarządzanie statusami** - zmiana statusu ticketa na 'w toku' lub 'oczekujący'
5. **Dodawanie czasu rozmowy telefonicznej** - możliwość dodania czasu spędzonego na rozmowie telefonicznej
6. **Sekcja ticketa w toku** - wyświetlanie ticketa aktualnie w trakcie obsługi z możliwością dodawania notatek
7. **Status bar** - wyświetlanie ostrzeżeń, jeśli pracownik ma za mało lub za dużo pracy
8. **Odbieranie telefonu** - duży przycisk 'odbieram telefon', który uruchamia moduł 'odbieram telefon'
9. **Synchronizacja w czasie rzeczywistym** - odbieranie zmian w planingu poprzez Server-Sent Events (SSE)

## Podkomponenty

### WorkerSchedule (Główny komponent grafika)
Główny komponent modułu grafika, który zarządza stanem grafika i koordynuje wszystkie podkomponenty.

**Funkcjonalność:**
- Zarządzanie stanem grafika (aktualny dzień + następny dzień dostępności, przypisane tickety)
- Sprawdzanie dostępności pracownika w kolejnych dniach
- Pobieranie danych grafika z API
- Zarządzanie połączeniem SSE do odbierania zmian w czasie rzeczywistym
- Zarządzanie aktywnym ticketem (ticket w toku)
- Obsługa wyboru ticketa do pracy
- Obsługa zmiany statusu ticketów
- Obsługa dodawania czasu do ticketów
- Przekierowanie do modułu 'odbieram telefon'
- Wyświetlanie statusu pracy (za mało/za dużo pracy)

**Props:**
- `workerId: string` - identyfikator zalogowanego pracownika

**State:**
```typescript
interface WorkerScheduleState {
  schedule: ScheduleDay[]; // aktualny dzień + następny dzień dostępności z ticketami
  activeTicket: Ticket | null; // ticket aktualnie w toku
  isLoading: boolean;
  error: string | null;
  workStatus: WorkStatus; // informacja o obciążeniu pracą
}
```

### ScheduleCalendar (Kalendarz grafika)
Komponent wyświetlający kalendarz z aktualnym dniem oraz następnym dniem dostępności pracownika i przypisanymi ticketami.

**Funkcjonalność:**
- Wyświetlanie aktualnego dnia oraz następnego dnia, w którym pracownik jest dostępny
- Sprawdzanie dostępności pracownika w kolejnych dniach
- Wyświetlanie przypisanych ticketów dla każdego dnia
- Wyświetlanie czasu spędzonego na ticketach dzisiaj
- Wyróżnienie dzisiejszego dnia
- Wyróżnienie ticketów w statusie 'w toku'
- Interakcja z ticketami (kliknięcie do wyboru jako aktywny)

**Props:**
- `schedule: ScheduleDay[]` - dane grafika dla aktualnego dnia i następnego dnia dostępności
- `activeTicket: Ticket | null` - aktualnie aktywny ticket
- `onTicketSelect: (ticket: Ticket) => void` - funkcja wywoływana przy wyborze ticketa
- `onTicketStatusChange: (ticketId: string, status: TicketStatus) => void` - funkcja zmiany statusu

**Interfejsy:**
```typescript
interface ScheduleDay {
  date: string; // YYYY-MM-DD
  tickets: ScheduledTicket[];
  totalTimeSpent: number; // w minutach
}

interface ScheduledTicket {
  id: string;
  title: string;
  category: TicketCategory;
  status: TicketStatus;
  timeSpent: number; // w minutach
  estimatedTime: number; // w minutach
  isActive: boolean; // czy jest aktualnie w toku
}
```

### ActiveTicketSection (Sekcja aktywnego ticketa)
Komponent wyświetlający informacje o tickecie aktualnie w trakcie obsługi oraz umożliwiający dodawanie notatek.

**Funkcjonalność:**
- Wyświetlanie szczegółów aktywnego ticketa (tytuł, kategoria, status)
- Wyświetlanie czasu spędzonego na tickecie
- Wyświetlanie czasu rejestrowanego na bieżąco (jeśli ticket jest w toku)
- Formularz dodawania notatek do ticketa
- Przycisk do zmiany statusu ticketa
- Przycisk do zakończenia pracy nad ticketem

**Props:**
- `ticket: Ticket | null` - aktywny ticket
- `onNoteAdd: (ticketId: string, note: string) => void` - funkcja dodawania notatki
- `onStatusChange: (ticketId: string, status: TicketStatus) => void` - funkcja zmiany statusu
- `onStopWork: (ticketId: string) => void` - funkcja zakończenia pracy nad ticketem

**Interfejs:**
```typescript
interface Ticket {
  id: string;
  title: string;
  category: TicketCategory;
  status: TicketStatus;
  timeSpent: number; // w minutach
  notes: TicketNote[];
  client: Client;
  scheduledDate: string; // YYYY-MM-DD
}

interface TicketNote {
  id: string;
  content: string;
  createdAt: string;
  createdBy: string; // workerId
}
```

### TicketList (Lista ticketów)
Komponent wyświetlający listę ticketów przypisanych do danego dnia.

**Funkcjonalność:**
- Wyświetlanie listy ticketów dla wybranego dnia
- Sortowanie ticketów (np. według priorytetu, czasu zaplanowanego)
- Wyróżnienie aktywnego ticketa
- Wyświetlanie statusu każdego ticketa
- Wyświetlanie czasu spędzonego i zaplanowanego
- Możliwość wyboru ticketa do pracy
- Możliwość zmiany statusu ticketa

**Props:**
- `tickets: ScheduledTicket[]` - lista ticketów
- `activeTicketId: string | null` - ID aktywnego ticketa
- `onTicketSelect: (ticket: ScheduledTicket) => void` - funkcja wyboru ticketa
- `onStatusChange: (ticketId: string, status: TicketStatus) => void` - funkcja zmiany statusu

### WorkStatusBar (Pasek statusu pracy)
Komponent wyświetlający ostrzeżenia dotyczące obciążenia pracą pracownika.

**Funkcjonalność:**
- Wyświetlanie informacji o obciążeniu pracą
- Ostrzeżenie, jeśli pracownik ma za mało pracy (poniżej minimalnego progu)
- Ostrzeżenie, jeśli pracownik ma za dużo pracy (powyżej maksymalnego progu)
- Wyświetlanie statystyk (liczba ticketów, czas spędzony, czas zaplanowany)
- Wizualne wskaźniki (kolory: zielony - OK, żółty - ostrzeżenie, czerwony - problem)

**Props:**
- `workStatus: WorkStatus` - status obciążenia pracą
- `todayStats: DayStats` - statystyki dzisiejszego dnia

**Interfejsy:**
```typescript
interface WorkStatus {
  level: 'low' | 'normal' | 'high' | 'critical';
  message: string;
  ticketsCount: number;
  timeSpent: number; // w minutach
  timePlanned: number; // w minutach
}

interface DayStats {
  date: string;
  ticketsCount: number;
  timeSpent: number;
  timePlanned: number;
  completedTickets: number;
  inProgressTickets: number;
  waitingTickets: number;
}
```

### AnswerPhoneButton (Przycisk odbierania telefonu)
Duży, wyróżniony przycisk do uruchomienia modułu 'odbieram telefon'.

**Funkcjonalność:**
- Wyświetlanie dużego, widocznego przycisku
- Przekierowanie do modułu 'odbieram telefon'
- Wizualne wyróżnienie jako głównej akcji
- Możliwość wyłączenia przycisku (np. jeśli pracownik już odbiera telefon)

**Props:**
- `onClick: () => void` - funkcja wywoływana przy kliknięciu
- `isDisabled?: boolean` - czy przycisk powinien być wyłączony

### TimeTracker (Śledzenie czasu)
Komponent wyświetlający i zarządzający czasem spędzonym na ticketach.

**Funkcjonalność:**
- Wyświetlanie czasu spędzonego na aktywnym tickecie
- Rejestrowanie czasu dla ticketów w statusie 'w toku'
- Wyświetlanie czasu w formacie czytelnym (np. "2h 30min")
- Możliwość ręcznego dodania czasu (np. dla rozmowy telefonicznej)
- Automatyczne zatrzymanie rejestracji czasu przy zmianie ticketa

**Props:**
- `ticket: Ticket | null` - ticket, którego czas jest śledzony
- `onTimeAdd: (ticketId: string, minutes: number) => void` - funkcja dodawania czasu
- `isTracking: boolean` - czy czas jest aktualnie rejestrowany

### SSEConnection (Połączenie Server-Sent Events)
Komponent zarządzający połączeniem SSE do odbierania zmian w planingu w czasie rzeczywistym.

**Funkcjonalność:**
- Nawiązanie połączenia SSE z backendem
- Odbieranie aktualizacji grafika w czasie rzeczywistym
- Automatyczne odtwarzanie połączenia w przypadku rozłączenia
- Obsługa różnych typów zdarzeń (nowy ticket, zmiana statusu, usunięcie ticketa)
- Aktualizacja stanu komponentu na podstawie otrzymanych zdarzeń

**Props:**
- `workerId: string` - identyfikator pracownika
- `onScheduleUpdate: (update: ScheduleUpdate) => void` - funkcja wywoływana przy otrzymaniu aktualizacji
- `onError: (error: Error) => void` - funkcja obsługi błędów

**Interfejsy:**
```typescript
interface ScheduleUpdate {
  type: 'ticket_added' | 'ticket_updated' | 'ticket_removed' | 'status_changed' | 'time_updated';
  ticketId: string;
  data: any; // dane zależne od typu zdarzenia
  timestamp: string;
}
```

## Integracja z API

Moduł komunikuje się z backendem przez następujące endpointy:

### GET /api/worker/schedule
Pobranie grafika pracownika dla aktualnego dnia oraz następnego dnia, w którym pracownik jest dostępny. Backend automatycznie określa dostępność pracownika na podstawie zadeklarowanej dostępności w module dostępności pracownika i zwraca tylko te dni, w których pracownik ma zadeklarowaną dostępność.

**Uwagi:**
- Jeśli pracownik jest dostępny dzisiaj, zwracany jest dzisiejszy dzień oraz następny dzień dostępności
- Jeśli pracownik nie jest dostępny dzisiaj, zwracany jest najbliższy dzień dostępności oraz następny dzień dostępności
- Jeśli pracownik ma tylko jeden dzień dostępności, zwracany jest tylko ten dzień

**Odpowiedź (sukces):**
```json
{
  "schedule": [
    {
      "date": "2024-01-15",
      "tickets": [
        {
          "id": "ticket-123",
          "title": "Problem z połączeniem",
          "category": {
            "id": "cat-1",
            "name": "Wsparcie techniczne"
          },
          "status": "waiting",
          "timeSpent": 45,
          "estimatedTime": 60,
          "scheduledDate": "2024-01-15"
        }
      ],
      "totalTimeSpent": 45
    }
  ],
  "activeTicket": {
    "id": "ticket-123",
    "title": "Problem z połączeniem",
    "status": "in_progress",
    "timeSpent": 45,
    "notes": []
  }
}
```

### GET /events/worker/schedule/{workerId}
Endpoint SSE do odbierania zmian w planingu w czasie rzeczywistym.

**Format zdarzeń:**
```
event: ticket_updated
data: {"ticketId": "ticket-123", "status": "in_progress", "timeSpent": 50}

event: ticket_added
data: {"ticket": {...}, "scheduledDate": "2024-01-15"}

event: ticket_removed
data: {"ticketId": "ticket-123", "scheduledDate": "2024-01-15"}
```

### POST /api/worker/tickets/{ticketId}/status
Zmiana statusu ticketa.

**Request body:**
```json
{
  "status": "in_progress"
}
```

**Odpowiedź (sukces):**
```json
{
  "ticket": {
    "id": "ticket-123",
    "status": "in_progress",
    "updatedAt": "2024-01-15T10:30:00Z"
  }
}
```

### POST /api/worker/tickets/{ticketId}/time
Dodanie czasu spędzonego na tickecie.

**Request body:**
```json
{
  "minutes": 15,
  "type": "phone_call" // lub "work"
}
```

**Odpowiedź (sukces):**
```json
{
  "ticket": {
    "id": "ticket-123",
    "timeSpent": 60,
    "updatedAt": "2024-01-15T10:45:00Z"
  }
}
```

### POST /api/worker/tickets/{ticketId}/notes
Dodanie notatki do ticketa.

**Request body:**
```json
{
  "content": "Klient potwierdził rozwiązanie problemu"
}
```

**Odpowiedź (sukces):**
```json
{
  "note": {
    "id": "note-456",
    "content": "Klient potwierdził rozwiązanie problemu",
    "createdAt": "2024-01-15T10:50:00Z",
    "createdBy": "worker-789"
  }
}
```

### GET /api/worker/work-status
Pobranie statusu obciążenia pracą pracownika.

**Odpowiedź (sukces):**
```json
{
  "status": {
    "level": "normal",
    "message": "Obciążenie pracą jest w normie",
    "ticketsCount": 5,
    "timeSpent": 240,
    "timePlanned": 480
  },
  "todayStats": {
    "date": "2024-01-15",
    "ticketsCount": 5,
    "timeSpent": 240,
    "timePlanned": 480,
    "completedTickets": 2,
    "inProgressTickets": 1,
    "waitingTickets": 2
  }
}
```

## Server-Sent Events (SSE)

Moduł wykorzystuje Server-Sent Events do odbierania aktualizacji grafika w czasie rzeczywistym. Połączenie SSE jest nawiązywane automatycznie po załadowaniu modułu i jest utrzymywane przez cały czas działania.

### Typy zdarzeń:

1. **ticket_added** - nowy ticket został przypisany do pracownika
2. **ticket_updated** - ticket został zaktualizowany (zmiana statusu, czasu, itp.)
3. **ticket_removed** - ticket został usunięty z grafika
4. **status_changed** - status ticketa został zmieniony
5. **time_updated** - czas spędzony na tickecie został zaktualizowany

### Obsługa połączenia:

- Automatyczne ponowne połączenie w przypadku rozłączenia
- Obsługa błędów połączenia
- Zamykanie połączenia przy opuszczeniu modułu
- Filtrowanie zdarzeń tylko dla zalogowanego pracownika

## Zarządzanie stanem

Moduł zarządza następującymi stanami:

1. **Stan grafika** - dane o aktualnym dniu oraz następnym dniu dostępności pracownika z przypisanymi ticketami
2. **Aktywny ticket** - ticket, nad którym pracownik aktualnie pracuje
3. **Status pracy** - informacja o obciążeniu pracą
4. **Połączenie SSE** - stan połączenia do odbierania aktualizacji
5. **Stan ładowania** - informacja o trwających żądaniach API
6. **Błędy** - komunikaty błędów z API

## Wybór aktywnego ticketa

Pracownik może wybrać jeden ticket, nad którym aktualnie pracuje, spośród tych, które zostały mu zaplanowane. Wybór aktywnego ticketa powoduje:

1. Ustawienie statusu poprzedniego aktywnego ticketa na 'oczekujący' (jeśli istniał)
2. Ustawienie statusu nowego ticketa na 'w toku'
3. Rozpoczęcie rejestracji czasu dla nowego ticketa
4. Zatrzymanie rejestracji czasu dla poprzedniego ticketa
5. Wyświetlenie szczegółów nowego ticketa w sekcji aktywnego ticketa

## Rejestracja czasu

Moduł automatycznie rejestruje czas dla ticketów w statusie 'w toku'. Czas jest rejestrowany:

1. **Automatycznie** - od momentu ustawienia statusu na 'w toku' do momentu zmiany statusu
2. **Ręcznie** - pracownik może dodać czas spędzony na rozmowie telefonicznej lub innej pracy

Czas jest aktualizowany w czasie rzeczywistym i synchronizowany z backendem.

## Uwagi implementacyjne

1. **Synchronizacja w czasie rzeczywistym:**
   - Moduł powinien automatycznie aktualizować dane na podstawie zdarzeń SSE
   - Zmiany wprowadzone przez pracownika powinny być natychmiast widoczne
   - Konflikty zmian powinny być rozwiązywane (np. ostatnia zmiana wygrywa)

2. **Wydajność:**
   - Moduł powinien być zoptymalizowany pod kątem renderowania dużej liczby ticketów
   - Lazy loading komponentów, jeśli moduł jest duży
   - Minimalizacja liczby re-renderów
   - Optymalizacja połączenia SSE (tylko niezbędne dane)

3. **UX:**
   - Intuicyjny interfejs wyboru ticketa
   - Wyraźne wizualne wyróżnienie aktywnego ticketa
   - Czytelne wyświetlanie czasu
   - Responsywny design (działa na urządzeniach mobilnych)
   - Wyświetlanie wskaźników ładowania podczas operacji

4. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy połączenia SSE powinny być obsługiwane z automatycznym ponowieniem
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby

5. **Integracja z routingiem:**
   - Moduł powinien być dostępny pod ścieżką `/worker` lub `/worker/schedule`
   - Moduł powinien być chroniony przed dostępem dla nieautoryzowanych użytkowników
   - Przekierowanie do modułu 'odbieram telefon' przy kliknięciu odpowiedniego przycisku

6. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API i SSE)
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu wyboru ticketa i rejestracji czasu
   - Testy dla obsługi zdarzeń SSE