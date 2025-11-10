# Moduł monitoringu dla kierownika

## Opis modułu

Moduł monitoringu dla kierownika jest komponentem Reactowym, który umożliwia kierownikowi monitorowanie stanu systemu Call Center w czasie rzeczywistym. Moduł składa się z sekcji zawierających różne informacje o obciążeniu pracowników, stanie kolejek ticketów oraz możliwości zarządzania automatycznym przypisywaniem zadań.

Moduł jest dostępny wyłącznie dla zalogowanych pracowników z rolą managera. Kierownik może przeglądać statystyki obciążenia pracowników w danym dniu, monitorować ilość oczekujących ticketów w kolejkach oraz włączać lub wyłączać automatyczne przypisywanie zadań dla pracowników i kolejek.

## Funkcjonalność

1. **Wyświetlanie statystyk obciążenia pracowników** - prezentacja ogólnych statystyk obciążenia pracą dla wszystkich pracowników w danym dniu
2. **Monitorowanie kolejek ticketów** - wyświetlanie ilości oczekujących ticketów w poszczególnych kolejkach (kategoriach)
3. **Automatyczne przypisywanie zadań** - możliwość włączenia/wyłączenia automatycznego przypisywania ticketów do pracowników
4. **Wybór dnia monitoringu** - możliwość wyboru dnia, dla którego wyświetlane są statystyki
5. **Szczegółowe statystyki pracowników** - wyświetlanie szczegółowych informacji o każdym pracowniku (liczba ticketów, czas spędzony, efektywność)
6. **Wizualizacja danych** - wykresy i wskaźniki wizualne przedstawiające stan systemu
7. **Aktualizacja w czasie rzeczywistym** - odbieranie zmian w systemie poprzez Server-Sent Events (SSE)
8. **Filtrowanie i sortowanie** - możliwość filtrowania pracowników według różnych kryteriów

## Podkomponenty

### ManagerMonitoring (Główny komponent monitoringu)
Główny komponent modułu monitoringu, który zarządza stanem monitoringu i koordynuje wszystkie podkomponenty.

**Funkcjonalność:**
- Zarządzanie stanem monitoringu (wybrany dzień, statystyki pracowników, kolejki ticketów)
- Pobieranie danych monitoringu z API
- Zarządzanie połączeniem SSE do odbierania zmian w czasie rzeczywistym
- Zarządzanie ustawieniami automatycznego przypisywania zadań
- Obsługa wyboru dnia monitoringu
- Koordynacja wszystkich sekcji monitoringu

**Props:**
- `managerId: string` - identyfikator zalogowanego managera

**State:**
```typescript
interface ManagerMonitoringState {
  selectedDate: string; // YYYY-MM-DD
  workerStats: WorkerStats[];
  queueStats: QueueStats[];
  autoAssignmentSettings: AutoAssignmentSettings;
  isLoading: boolean;
  error: string | null;
  lastUpdate: string | null; // timestamp ostatniej aktualizacji
}
```

### DateSelector (Wybór dnia monitoringu)
Komponent umożliwiający wybór dnia, dla którego wyświetlane są statystyki monitoringu.

**Funkcjonalność:**
- Wyświetlanie kalendarza lub selektora daty
- Wybór dnia monitoringu (domyślnie dzisiejszy dzień)
- Nawigacja między dniami (poprzedni/następny dzień)
- Wyświetlanie wybranego dnia w czytelnej formie
- Walidacja wybranego dnia (np. nie można wybrać przyszłych dni)

**Props:**
- `selectedDate: string` - wybrana data (YYYY-MM-DD)
- `onDateChange: (date: string) => void` - funkcja wywoływana przy zmianie daty
- `minDate?: string` - minimalna dostępna data (opcjonalnie)
- `maxDate?: string` - maksymalna dostępna data (opcjonalnie)

### WorkerStatsSection (Sekcja statystyk pracowników)
Komponent wyświetlający ogólne statystyki obciążenia pracowników w danym dniu.

**Funkcjonalność:**
- Wyświetlanie listy wszystkich pracowników z ich statystykami
- Wyświetlanie obciążenia pracą dla każdego pracownika
- Wizualne wskaźniki obciążenia (kolory: zielony - OK, żółty - ostrzeżenie, czerwony - przeciążenie)
- Sortowanie pracowników według różnych kryteriów (obciążenie, liczba ticketów, efektywność)
- Filtrowanie pracowników (np. według kategorii, obciążenia)
- Szczegółowe informacje o każdym pracowniku (rozszerzalne sekcje)

**Props:**
- `workerStats: WorkerStats[]` - statystyki wszystkich pracowników
- `selectedDate: string` - wybrana data
- `onWorkerClick?: (workerId: string) => void` - opcjonalna funkcja wywoływana przy kliknięciu pracownika

**Interfejsy:**
```typescript
interface WorkerStats {
  workerId: string;
  workerLogin: string;
  ticketsCount: number;
  timeSpent: number; // w minutach
  timePlanned: number; // w minutach
  workloadLevel: 'low' | 'normal' | 'high' | 'critical';
  efficiency: number; // procent efektywności (0-100)
  categories: string[]; // kategorie, do których pracownik ma dostęp
  completedTickets: number;
  inProgressTickets: number;
  waitingTickets: number;
}
```

### WorkerStatsCard (Karta statystyk pracownika)
Komponent wyświetlający statystyki pojedynczego pracownika w formie karty.

**Funkcjonalność:**
- Wyświetlanie podstawowych informacji o pracowniku (login, kategorie)
- Wyświetlanie statystyk obciążenia (liczba ticketów, czas spędzony, czas zaplanowany)
- Wizualny wskaźnik obciążenia (pasek postępu, kolory)
- Wyświetlanie efektywności pracownika
- Rozszerzalna sekcja ze szczegółowymi informacjami
- Link do szczegółowego widoku pracownika (opcjonalnie)

**Props:**
- `workerStats: WorkerStats` - statystyki pracownika
- `onExpand?: () => void` - opcjonalna funkcja rozszerzenia karty
- `isExpanded?: boolean` - czy karta jest rozszerzona

### WorkloadIndicator (Wskaźnik obciążenia)
Komponent wizualizujący poziom obciążenia pracownika.

**Funkcjonalność:**
- Wizualizacja obciążenia w formie paska postępu lub wskaźnika
- Kolory wskaźnika zależne od poziomu obciążenia:
  - Zielony - niskie obciążenie (poniżej 50% zaplanowanego czasu)
  - Żółty - normalne obciążenie (50-80% zaplanowanego czasu)
  - Pomarańczowy - wysokie obciążenie (80-100% zaplanowanego czasu)
  - Czerwony - krytyczne obciążenie (powyżej 100% zaplanowanego czasu)
- Wyświetlanie procentowego wskaźnika obciążenia
- Wyświetlanie komunikatu tekstowego o obciążeniu

**Props:**
- `workloadLevel: 'low' | 'normal' | 'high' | 'critical'` - poziom obciążenia
- `timeSpent: number` - czas spędzony (w minutach)
- `timePlanned: number` - czas zaplanowany (w minutach)
- `showPercentage?: boolean` - czy wyświetlać procent (domyślnie true)

### QueueStatsSection (Sekcja statystyk kolejek)
Komponent wyświetlający statystyki kolejek ticketów (kategorii).

**Funkcjonalność:**
- Wyświetlanie listy wszystkich kolejek z ilością oczekujących ticketów
- Wyświetlanie statystyk dla każdej kolejki (liczba oczekujących, w toku, zamkniętych)
- Wizualne wskaźniki obciążenia kolejek (kolory, ikony)
- Sortowanie kolejek według ilości oczekujących ticketów
- Filtrowanie kolejek (opcjonalnie)
- Szczegółowe informacje o każdej kolejce (rozszerzalne sekcje)

**Props:**
- `queueStats: QueueStats[]` - statystyki wszystkich kolejek
- `selectedDate: string` - wybrana data
- `onQueueClick?: (queueId: string) => void` - opcjonalna funkcja wywoływana przy kliknięciu kolejki

**Interfejsy:**
```typescript
interface QueueStats {
  queueId: string;
  queueName: string;
  waitingTickets: number;
  inProgressTickets: number;
  completedTickets: number;
  totalTickets: number;
  averageResolutionTime: number; // w minutach
  assignedWorkers: number; // liczba pracowników przypisanych do kolejki
}
```

### QueueStatsCard (Karta statystyk kolejki)
Komponent wyświetlający statystyki pojedynczej kolejki w formie karty.

**Funkcjonalność:**
- Wyświetlanie nazwy kolejki
- Wyświetlanie ilości oczekujących ticketów (wyróżnione)
- Wyświetlanie statystyk (w toku, zamknięte, średni czas rozwiązania)
- Wizualny wskaźnik obciążenia kolejki
- Wyświetlanie liczby przypisanych pracowników
- Rozszerzalna sekcja ze szczegółowymi informacjami

**Props:**
- `queueStats: QueueStats` - statystyki kolejki
- `onExpand?: () => void` - opcjonalna funkcja rozszerzenia karty
- `isExpanded?: boolean` - czy karta jest rozszerzona

### AutoAssignmentSection (Sekcja automatycznego przypisywania)
Komponent umożliwiający włączenie/wyłączenie automatycznego przypisywania zadań dla pracowników i kolejek.

**Funkcjonalność:**
- Wyświetlanie statusu automatycznego przypisywania (włączone/wyłączone)
- Przełącznik do włączenia/wyłączenia automatycznego przypisywania
- Ustawienia automatycznego przypisywania (opcjonalnie - zaawansowane opcje)
- Wyświetlanie informacji o ostatnim automatycznym przypisaniu
- Statystyki automatycznego przypisywania (ile ticketów zostało przypisanych)
- Możliwość ręcznego uruchomienia automatycznego przypisywania

**Props:**
- `autoAssignmentSettings: AutoAssignmentSettings` - ustawienia automatycznego przypisywania
- `onToggle: (enabled: boolean) => void` - funkcja włączenia/wyłączenia
- `onManualTrigger?: () => void` - opcjonalna funkcja ręcznego uruchomienia

**Interfejsy:**
```typescript
interface AutoAssignmentSettings {
  enabled: boolean;
  lastRun: string | null; // timestamp ostatniego uruchomienia
  ticketsAssigned: number; // liczba przypisanych ticketów w ostatnim uruchomieniu
  settings: {
    considerEfficiency: boolean; // czy uwzględniać efektywność pracowników
    considerAvailability: boolean; // czy uwzględniać dostępność pracowników
    maxTicketsPerWorker: number; // maksymalna liczba ticketów na pracownika
  };
}
```

### AutoAssignmentToggle (Przełącznik automatycznego przypisywania)
Komponent przełącznika do włączenia/wyłączenia automatycznego przypisywania.

**Funkcjonalność:**
- Przełącznik typu toggle (on/off)
- Wyświetlanie statusu (włączone/wyłączone)
- Wyświetlanie opisu funkcjonalności
- Wizualne wyróżnienie stanu (kolory)
- Potwierdzenie przed zmianą (opcjonalnie)

**Props:**
- `enabled: boolean` - czy automatyczne przypisywanie jest włączone
- `onToggle: (enabled: boolean) => void` - funkcja wywoływana przy zmianie
- `isLoading?: boolean` - czy trwa aktualizacja ustawień

### StatisticsSummary (Podsumowanie statystyk)
Komponent wyświetlający ogólne podsumowanie statystyk systemu.

**Funkcjonalność:**
- Wyświetlanie ogólnych statystyk (łączna liczba ticketów, pracowników, kolejek)
- Wyświetlanie średnich wartości (średnie obciążenie, średni czas rozwiązania)
- Wizualne wskaźniki ogólnego stanu systemu
- Porównanie z poprzednim dniem (opcjonalnie)
- Wyświetlanie trendów (wzrost/spadek)

**Props:**
- `summary: MonitoringSummary` - podsumowanie statystyk
- `selectedDate: string` - wybrana data

**Interfejsy:**
```typescript
interface MonitoringSummary {
  totalTickets: number;
  totalWorkers: number;
  totalQueues: number;
  averageWorkload: number; // procent średniego obciążenia
  averageResolutionTime: number; // w minutach
  waitingTicketsTotal: number;
  inProgressTicketsTotal: number;
  completedTicketsTotal: number;
}
```

### MonitoringCharts (Wykresy monitoringu)
Komponent wyświetlający wykresy wizualizujące dane monitoringu.

**Funkcjonalność:**
- Wykres obciążenia pracowników (słupkowy lub liniowy)
- Wykres ilości ticketów w kolejkach (słupkowy)
- Wykres trendów czasowych (liniowy - opcjonalnie)
- Interaktywne wykresy z możliwością powiększenia
- Eksport wykresów (opcjonalnie)

**Props:**
- `workerStats: WorkerStats[]` - statystyki pracowników
- `queueStats: QueueStats[]` - statystyki kolejek
- `selectedDate: string` - wybrana data
- `chartType?: 'bar' | 'line' | 'pie'` - typ wykresu (opcjonalnie)

### SSEConnection (Połączenie Server-Sent Events)
Komponent zarządzający połączeniem SSE do odbierania zmian w systemie w czasie rzeczywistym.

**Funkcjonalność:**
- Nawiązanie połączenia SSE z backendem
- Odbieranie aktualizacji statystyk w czasie rzeczywistym
- Automatyczne odtwarzanie połączenia w przypadku rozłączenia
- Obsługa różnych typów zdarzeń (nowy ticket, zmiana statusu, aktualizacja statystyk)
- Aktualizacja stanu komponentu na podstawie otrzymanych zdarzeń

**Props:**
- `managerId: string` - identyfikator managera
- `selectedDate: string` - wybrana data
- `onUpdate: (update: MonitoringUpdate) => void` - funkcja wywoływana przy otrzymaniu aktualizacji
- `onError: (error: Error) => void` - funkcja obsługi błędów

**Interfejsy:**
```typescript
interface MonitoringUpdate {
  type: 'worker_stats_updated' | 'queue_stats_updated' | 'ticket_added' | 'ticket_status_changed';
  data: any; // dane zależne od typu zdarzenia
  timestamp: string;
}
```

### LastUpdateIndicator (Wskaźnik ostatniej aktualizacji)
Komponent wyświetlający informację o ostatniej aktualizacji danych.

**Funkcjonalność:**
- Wyświetlanie czasu ostatniej aktualizacji
- Wskaźnik aktywności połączenia SSE
- Przycisk ręcznego odświeżenia danych
- Animacja podczas aktualizacji

**Props:**
- `lastUpdate: string | null` - timestamp ostatniej aktualizacji
- `isConnected: boolean` - czy połączenie SSE jest aktywne
- `onRefresh: () => void` - funkcja ręcznego odświeżenia

## Integracja z API

Moduł komunikuje się z backendem przez następujące endpointy:

### GET /api/manager/monitoring
Pobranie danych monitoringu dla wybranego dnia.

**Query parameters:**
- `date: string` - data w formacie YYYY-MM-DD (wymagane)

**Odpowiedź (sukces):**
```json
{
  "date": "2024-01-15",
  "summary": {
    "totalTickets": 150,
    "totalWorkers": 25,
    "totalQueues": 5,
    "averageWorkload": 75,
    "averageResolutionTime": 45,
    "waitingTicketsTotal": 30,
    "inProgressTicketsTotal": 20,
    "completedTicketsTotal": 100
  },
  "workerStats": [
    {
      "workerId": "worker-123",
      "workerLogin": "jan.kowalski",
      "ticketsCount": 8,
      "timeSpent": 360,
      "timePlanned": 480,
      "workloadLevel": "normal",
      "efficiency": 85,
      "categories": ["cat-1", "cat-2"],
      "completedTickets": 5,
      "inProgressTickets": 2,
      "waitingTickets": 1
    }
  ],
  "queueStats": [
    {
      "queueId": "cat-1",
      "queueName": "Sprzedaż",
      "waitingTickets": 15,
      "inProgressTickets": 8,
      "completedTickets": 50,
      "totalTickets": 73,
      "averageResolutionTime": 30,
      "assignedWorkers": 10
    }
  ],
  "autoAssignmentSettings": {
    "enabled": true,
    "lastRun": "2024-01-15T10:00:00Z",
    "ticketsAssigned": 12,
    "settings": {
      "considerEfficiency": true,
      "considerAvailability": true,
      "maxTicketsPerWorker": 10
    }
  }
}
```

### GET /events/manager/monitoring/{managerId}
Endpoint SSE do odbierania zmian w systemie w czasie rzeczywistym.

**Query parameters:**
- `date: string` - data w formacie YYYY-MM-DD

**Format zdarzeń:**
```
event: worker_stats_updated
data: {"workerId": "worker-123", "ticketsCount": 9, "timeSpent": 380}

event: queue_stats_updated
data: {"queueId": "cat-1", "waitingTickets": 14, "inProgressTickets": 9}

event: ticket_added
data: {"ticketId": "ticket-456", "queueId": "cat-1", "status": "waiting"}

event: ticket_status_changed
data: {"ticketId": "ticket-123", "oldStatus": "waiting", "newStatus": "in_progress", "workerId": "worker-123"}
```

### PUT /api/manager/auto-assignment
Aktualizacja ustawień automatycznego przypisywania zadań.

**Request body:**
```json
{
  "enabled": true,
  "settings": {
    "considerEfficiency": true,
    "considerAvailability": true,
    "maxTicketsPerWorker": 10
  }
}
```

**Odpowiedź (sukces):**
```json
{
  "autoAssignmentSettings": {
    "enabled": true,
    "lastRun": "2024-01-15T10:00:00Z",
    "ticketsAssigned": 12,
    "settings": {
      "considerEfficiency": true,
      "considerAvailability": true,
      "maxTicketsPerWorker": 10
    }
  },
  "updatedAt": "2024-01-15T11:00:00Z"
}
```

### POST /api/manager/auto-assignment/trigger
Ręczne uruchomienie automatycznego przypisywania zadań.

**Request body:**
```json
{
  "date": "2024-01-15"
}
```

**Odpowiedź (sukces):**
```json
{
  "message": "Automatyczne przypisywanie zostało uruchomione",
  "ticketsAssigned": 15,
  "assignedTo": [
    {
      "workerId": "worker-123",
      "ticketsCount": 3
    },
    {
      "workerId": "worker-456",
      "ticketsCount": 2
    }
  ],
  "completedAt": "2024-01-15T11:05:00Z"
}
```

## Server-Sent Events (SSE)

Moduł wykorzystuje Server-Sent Events do odbierania aktualizacji statystyk w czasie rzeczywistym. Połączenie SSE jest nawiązywane automatycznie po załadowaniu modułu i jest utrzymywane przez cały czas działania.

### Typy zdarzeń:

1. **worker_stats_updated** - statystyki pracownika zostały zaktualizowane
2. **queue_stats_updated** - statystyki kolejki zostały zaktualizowane
3. **ticket_added** - nowy ticket został dodany do systemu
4. **ticket_status_changed** - status ticketa został zmieniony

### Obsługa połączenia:

- Automatyczne ponowne połączenie w przypadku rozłączenia
- Obsługa błędów połączenia
- Zamykanie połączenia przy opuszczeniu modułu
- Filtrowanie zdarzeń tylko dla wybranego dnia

## Zarządzanie stanem

Moduł zarządza następującymi stanami:

1. **Wybrany dzień** - data, dla której wyświetlane są statystyki
2. **Statystyki pracowników** - dane o obciążeniu wszystkich pracowników
3. **Statystyki kolejek** - dane o stanie wszystkich kolejek ticketów
4. **Ustawienia automatycznego przypisywania** - konfiguracja automatycznego przypisywania zadań
5. **Połączenie SSE** - stan połączenia do odbierania aktualizacji
6. **Stan ładowania** - informacja o trwających żądaniach API
7. **Błędy** - komunikaty błędów z API
8. **Ostatnia aktualizacja** - timestamp ostatniej aktualizacji danych

## Wybór dnia monitoringu

Kierownik może wybrać dzień, dla którego wyświetlane są statystyki monitoringu. Domyślnie wyświetlany jest dzisiejszy dzień. Wybór innego dnia powoduje:

1. Pobranie danych monitoringu dla wybranego dnia z API
2. Aktualizację połączenia SSE dla nowego dnia
3. Odświeżenie wszystkich sekcji monitoringu
4. Aktualizację wykresów i wizualizacji

## Automatyczne przypisywanie zadań

Moduł umożliwia kierownikowi włączenie lub wyłączenie automatycznego przypisywania ticketów do pracowników. Gdy automatyczne przypisywanie jest włączone:

1. System automatycznie przypisuje nowe tickety do pracowników na podstawie:
   - Efektywności pracowników w danej kategorii
   - Dostępności pracowników
   - Obecnego obciążenia pracowników
   - Maksymalnej liczby ticketów na pracownika
2. Statystyki automatycznego przypisywania są wyświetlane w sekcji
3. Kierownik może ręcznie uruchomić automatyczne przypisywanie w dowolnym momencie

## Uwagi implementacyjne

1. **Synchronizacja w czasie rzeczywistym:**
   - Moduł powinien automatycznie aktualizować dane na podstawie zdarzeń SSE
   - Zmiany w systemie powinny być natychmiast widoczne
   - Konflikty zmian powinny być rozwiązywane (np. ostatnia zmiana wygrywa)

2. **Wydajność:**
   - Moduł powinien być zoptymalizowany pod kątem renderowania dużej liczby pracowników i kolejek
   - Lazy loading komponentów, jeśli moduł jest duży
   - Minimalizacja liczby re-renderów
   - Optymalizacja połączenia SSE (tylko niezbędne dane)
   - Cache'owanie danych dla poprzednich dni (opcjonalnie)

3. **UX:**
   - Intuicyjny interfejs monitoringu
   - Wyraźne wizualne wskaźniki obciążenia
   - Czytelne wyświetlanie statystyk
   - Responsywny design (działa na urządzeniach mobilnych)
   - Wyświetlanie wskaźników ładowania podczas operacji
   - Możliwość eksportu danych (opcjonalnie)

4. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy połączenia SSE powinny być obsługiwane z automatycznym ponowieniem
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby
   - Wyświetlanie komunikatu o braku danych dla wybranego dnia

5. **Integracja z routingiem:**
   - Moduł powinien być dostępny pod ścieżką `/manager/monitoring`
   - Moduł powinien być chroniony przed dostępem dla nieautoryzowanych użytkowników
   - Tylko użytkownicy z rolą managera powinni mieć dostęp do modułu

6. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API i SSE)
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu monitoringu
   - Testy dla obsługi zdarzeń SSE
   - Testy dla automatycznego przypisywania zadań

7. **Wizualizacja danych:**
   - Użycie biblioteki do wykresów (np. Chart.js, Recharts, D3.js)
   - Interaktywne wykresy z możliwością powiększenia
   - Responsywne wykresy (działają na urządzeniach mobilnych)
   - Eksport wykresów do obrazów (opcjonalnie)

8. **Dostępność (a11y):**
   - Moduł powinien być dostępny dla użytkowników korzystających z czytników ekranu
   - Właściwe etykiety dla wszystkich elementów interfejsu
   - Obsługa nawigacji klawiaturą
   - Komunikaty błędów powinny być czytelne i zrozumiałe
   - Wskaźniki wizualne powinny mieć również tekstowe alternatywy

9. **Filtrowanie i sortowanie:**
   - Możliwość filtrowania pracowników według różnych kryteriów (kategoria, obciążenie, efektywność)
   - Możliwość sortowania pracowników według różnych kolumn
   - Zapisywanie preferencji filtrowania i sortowania (opcjonalnie)

10. **Aktualizacja danych:**
    - Automatyczne odświeżanie danych co określony czas (opcjonalnie - np. co 30 sekund)
    - Ręczne odświeżanie danych przez przycisk
    - Wskaźnik ostatniej aktualizacji
    - Wskaźnik aktywności połączenia SSE

