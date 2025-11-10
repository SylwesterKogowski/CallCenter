# Moduł 'odbieram telefon'

## Opis modułu

Moduł 'odbieram telefon' jest komponentem Reactowym, który umożliwia pracownikowi zarejestrowanie odbierania telefonu w systemie Call Center. Moduł reprezentuje odebranie telefonu, jeszcze nie wiemy w jakiej sprawie i od kogo on jest.

W momencie odebrania telefonu aplikacja zacznie rejestrować czas. Przerwie rejestrowanie czasu innych ticketów, przestawi je na status 'oczekujący'. W module jest możliwość wyszukania istniejącego ticketa, a także stworzenia nowego ticketa. Po wybraniu istniejącego ticketa lub stworzeniu nowego, umożliwia dodanie notatek do tego ticketa. Po zakończeniu połączenia pracownik klika 'Zakończyłem połączenie', a aplikacja zarejestruje czas połączenia do wybranego ticketa.

Po zakończeniu połączenia, nowostworzony/wybrany ticket jest automatycznie dodawany do grafika bieżącego dnia a jego status jest oznaczany na 'w toku' (a jego czas rejestrowany). W tym momencie pracownik ma czas żeby wykonać operacje związane z rozmową którą właśnie przeprowadził. Jeśli żaden nowy ticket nie został wybrany podczas rozmowy, ticket sprzed rozmowy jest ustawiany jako 'w toku'.

Moduł składa się z przycisku 'Odbieram telefon', który uruchamia okienko (modal/dialog) z całą obsługą odbioru telefonu.

## Funkcjonalność

1. **Przycisk 'Odbieram telefon'** - duży, wyróżniony przycisk uruchamiający okienko z obsługą odbioru telefonu
2. **Okienko obsługi telefonu** - modal/dialog z całą funkcjonalnością odbioru telefonu
3. **Rejestracja czasu** - automatyczne rozpoczęcie rejestracji czasu po odebraniu telefonu
4. **Przerwanie innych ticketów** - automatyczne przerwanie rejestracji czasu innych ticketów i ustawienie ich na status 'oczekujący'
5. **Wyszukiwanie istniejącego ticketa** - możliwość wyszukania i wyboru istniejącego ticketa związanego z rozmową
6. **Tworzenie nowego ticketa** - możliwość utworzenia nowego ticketa podczas rozmowy
7. **Dodawanie notatek** - możliwość dodania notatek do wybranego/utworzonego ticketa podczas rozmowy
8. **Zakończenie połączenia** - przycisk 'Zakończyłem połączenie' rejestrujący czas połączenia
9. **Automatyczne przypisanie do grafika** - po zakończeniu połączenia ticket jest automatycznie dodawany do grafika bieżącego dnia ze statusem 'w toku'
10. **Fallback do poprzedniego ticketa** - jeśli żaden ticket nie został wybrany podczas rozmowy, poprzedni aktywny ticket jest ustawiany jako 'w toku'

## Podkomponenty

### PhoneReceiveButton (Przycisk odbierania telefonu)
Główny przycisk uruchamiający moduł odbierania telefonu. Może być używany w module grafika pracownika lub jako osobny komponent.

**Funkcjonalność:**
- Wyświetlanie dużego, wyróżnionego przycisku 'Odbieram telefon'
- Uruchamianie okienka z obsługą odbioru telefonu
- Wizualne wyróżnienie jako głównej akcji
- Możliwość wyłączenia przycisku (np. jeśli pracownik już odbiera telefon)
- Wyświetlanie wskaźnika aktywności (np. jeśli telefon jest w trakcie)

**Props:**
- `onClick: () => void` - funkcja wywoływana przy kliknięciu
- `isDisabled?: boolean` - czy przycisk powinien być wyłączony
- `isActive?: boolean` - czy telefon jest aktualnie odbierany

### PhoneReceiveModal (Okienko obsługi telefonu)
Główny komponent okienka (modal/dialog) z całą obsługą odbioru telefonu.

**Funkcjonalność:**
- Zarządzanie stanem odbierania telefonu
- Rozpoczęcie rejestracji czasu po otwarciu okienka
- Przerwanie rejestracji czasu innych ticketów
- Koordynacja wszystkich podkomponentów
- Obsługa zakończenia połączenia
- Automatyczne przypisanie ticketa do grafika po zakończeniu

**Props:**
- `isOpen: boolean` - czy okienko jest otwarte
- `onClose: () => void` - funkcja zamykania okienka
- `workerId: string` - identyfikator zalogowanego pracownika
- `previousActiveTicket: Ticket | null` - poprzedni aktywny ticket (dla fallback)

**State:**
```typescript
interface PhoneReceiveState {
  callStartTime: Date | null; // czas rozpoczęcia połączenia
  selectedTicket: Ticket | null; // wybrany/utworzony ticket
  isSearchingTicket: boolean; // czy trwa wyszukiwanie ticketa
  isCreatingTicket: boolean; // czy trwa tworzenie nowego ticketa
  notes: string; // notatki do ticketa
  isLoading: boolean;
  error: string | null;
  callDuration: number; // czas trwania połączenia w sekundach
}
```

### TicketSearch (Wyszukiwanie ticketa)
Komponent umożliwiający wyszukanie istniejącego ticketa związanego z rozmową.

**Funkcjonalność:**
- Wyszukiwanie ticketów według tytułu, kategorii, klienta
- Wyświetlanie listy wyników wyszukiwania
- Wybór ticketa z listy wyników
- Filtrowanie wyników (kategoria, status)
- Wyświetlanie szczegółów ticketa przed wyborem

**Props:**
- `onTicketSelect: (ticket: Ticket) => void` - funkcja wywoływana przy wyborze ticketa
- `workerId: string` - identyfikator pracownika (dla filtrowania dostępnych kategorii)
- `excludeTicketId?: string` - ID ticketa do wykluczenia z wyników

**Interfejsy:**
```typescript
interface Ticket {
  id: string;
  title: string;
  category: TicketCategory;
  status: TicketStatus;
  client: Client;
  createdAt: string;
  timeSpent: number; // w minutach
}

interface TicketCategory {
  id: string;
  name: string;
}

interface Client {
  id: string;
  name: string;
  email?: string;
  phone?: string;
}
```

### TicketCreateForm (Formularz tworzenia ticketa)
Komponent formularza do utworzenia nowego ticketa podczas rozmowy.

**Funkcjonalność:**
- Pola formularza: tytuł, kategoria, klient (wyszukiwanie lub tworzenie)
- Walidacja pól formularza
- Tworzenie nowego klienta (jeśli nie istnieje)
- Wybór kategorii z dostępnych dla pracownika
- Przyciski zapisu i anulowania

**Props:**
- `onTicketCreate: (ticketData: NewTicketData) => void` - funkcja tworzenia ticketa
- `onCancel: () => void` - funkcja anulowania
- `workerId: string` - identyfikator pracownika (dla dostępnych kategorii)
- `clientSearchResults?: Client[]` - wyniki wyszukiwania klienta

**Interfejsy:**
```typescript
interface NewTicketData {
  title: string;
  categoryId: string;
  clientId?: string; // jeśli istniejący klient
  clientData?: { // jeśli nowy klient
    name: string;
    email?: string;
    phone?: string;
  };
}

interface ClientSearchResult {
  id: string;
  name: string;
  email?: string;
  phone?: string;
  matchScore?: number; // dla sortowania wyników
}
```

### TicketNotesEditor (Edytor notatek)
Komponent umożliwiający dodanie notatek do wybranego/utworzonego ticketa podczas rozmowy.

**Funkcjonalność:**
- Pole tekstowe do wprowadzenia notatek
- Podgląd notatek w czasie rzeczywistym
- Zapis notatek do ticketa
- Formatowanie tekstu (opcjonalnie)
- Wyświetlanie istniejących notatek ticketa

**Props:**
- `ticket: Ticket | null` - ticket, do którego dodajemy notatki
- `onNoteAdd: (ticketId: string, note: string) => void` - funkcja dodawania notatki
- `existingNotes?: TicketNote[]` - istniejące notatki ticketa

**Interfejsy:**
```typescript
interface TicketNote {
  id: string;
  content: string;
  createdAt: string;
  createdBy: string; // workerId
}
```

### CallTimer (Licznik czasu połączenia)
Komponent wyświetlający czas trwania połączenia w czasie rzeczywistym.

**Funkcjonalność:**
- Wyświetlanie czasu trwania połączenia (format: MM:SS lub HH:MM:SS)
- Aktualizacja czasu w czasie rzeczywistym (co sekundę)
- Wizualne wyróżnienie (np. duży, czytelny wyświetlacz)
- Zatrzymanie licznika po zakończeniu połączenia

**Props:**
- `startTime: Date | null` - czas rozpoczęcia połączenia
- `isActive: boolean` - czy połączenie jest aktywne
- `onDurationChange?: (seconds: number) => void` - funkcja wywoływana przy zmianie czasu

### EndCallButton (Przycisk zakończenia połączenia)
Przycisk do zakończenia połączenia i zapisania danych.

**Funkcjonalność:**
- Wyświetlanie przycisku 'Zakończyłem połączenie'
- Dialog potwierdzenia przed zakończeniem (opcjonalnie)
- Rejestracja czasu połączenia do ticketa
- Automatyczne przypisanie ticketa do grafika
- Zamknięcie okienka po zakończeniu
- Wyświetlanie stanu ładowania podczas zapisu

**Props:**
- `onEndCall: (callData: CallData) => void` - funkcja zakończenia połączenia
- `isLoading: boolean` - czy trwa zapisywanie danych
- `callDuration: number` - czas trwania połączenia w sekundach
- `hasSelectedTicket: boolean` - czy został wybrany/utworzony ticket

**Interfejsy:**
```typescript
interface CallData {
  ticketId: string | null; // null jeśli nie wybrano ticketa
  duration: number; // w sekundach
  notes: string;
  startTime: Date;
  endTime: Date;
}
```

### TicketDisplay (Wyświetlanie wybranego ticketa)
Komponent wyświetlający informacje o wybranym/utworzonym tickecie.

**Funkcjonalność:**
- Wyświetlanie szczegółów ticketa (tytuł, kategoria, klient)
- Wyświetlanie statusu ticketa
- Możliwość zmiany ticketa (wybór innego lub utworzenie nowego)
- Wyświetlanie czasu spędzonego na tickecie (przed rozmową)

**Props:**
- `ticket: Ticket | null` - wybrany ticket
- `onTicketChange: () => void` - funkcja zmiany ticketa
- `onTicketCreate: () => void` - funkcja tworzenia nowego ticketa

## Integracja z API

Moduł komunikuje się z backendem przez następujące endpointy:

### POST /api/worker/phone/receive
Rozpoczęcie odbierania telefonu. Przerwie rejestrację czasu innych ticketów i ustawi je na status 'oczekujący'.

**Request body:**
```json
{
  "workerId": "worker-123"
}
```

**Odpowiedź (sukces):**
```json
{
  "callId": "call-456",
  "startTime": "2024-01-15T10:30:00Z",
  "pausedTickets": [
    {
      "ticketId": "ticket-789",
      "previousStatus": "in_progress",
      "newStatus": "waiting"
    }
  ]
}
```

### GET /api/worker/tickets/search
Wyszukiwanie ticketów do wyboru podczas rozmowy.

**Query parameters:**
- `query` (opcjonalne) - wyszukiwanie tekstowe (tytuł, klient)
- `categoryId` (opcjonalne) - filtrowanie według kategorii
- `status` (opcjonalne) - filtrowanie według statusu
- `limit` (opcjonalne) - limit wyników (domyślnie 20)

**Odpowiedź (sukces):**
```json
{
  "tickets": [
    {
      "id": "ticket-123",
      "title": "Problem z połączeniem",
      "category": {
        "id": "cat-1",
        "name": "Wsparcie techniczne"
      },
      "status": "waiting",
      "client": {
        "id": "client-456",
        "name": "Jan Kowalski",
        "email": "jan@example.com",
        "phone": "+48123456789"
      },
      "createdAt": "2024-01-15T09:00:00Z",
      "timeSpent": 30
    }
  ],
  "total": 5
}
```

### POST /api/worker/tickets
Tworzenie nowego ticketa podczas rozmowy.

**Request body:**
```json
{
  "title": "Nowy problem",
  "categoryId": "cat-1",
  "clientId": "client-456",
  "clientData": null
}
```

Lub z nowym klientem:
```json
{
  "title": "Nowy problem",
  "categoryId": "cat-1",
  "clientId": null,
  "clientData": {
    "name": "Nowy Klient",
    "email": "nowy@example.com",
    "phone": "+48987654321"
  }
}
```

**Odpowiedź (sukces):**
```json
{
  "ticket": {
    "id": "ticket-789",
    "title": "Nowy problem",
    "category": {
      "id": "cat-1",
      "name": "Wsparcie techniczne"
    },
    "status": "waiting",
    "client": {
      "id": "client-456",
      "name": "Jan Kowalski"
    },
    "createdAt": "2024-01-15T10:35:00Z",
    "timeSpent": 0
  }
}
```

### POST /api/worker/tickets/{ticketId}/notes
Dodanie notatki do ticketa podczas rozmowy.

**Request body:**
```json
{
  "content": "Klient zgłasza problem z połączeniem internetowym. Sprawdzam konfigurację routera."
}
```

**Odpowiedź (sukces):**
```json
{
  "note": {
    "id": "note-123",
    "content": "Klient zgłasza problem z połączeniem internetowym. Sprawdzam konfigurację routera.",
    "createdAt": "2024-01-15T10:40:00Z",
    "createdBy": "worker-123"
  }
}
```

### POST /api/worker/phone/end
Zakończenie połączenia i zapisanie danych.

**Request body:**
```json
{
  "callId": "call-456",
  "ticketId": "ticket-789",
  "duration": 600,
  "notes": "Klient zgłasza problem z połączeniem internetowym. Sprawdzam konfigurację routera.",
  "startTime": "2024-01-15T10:30:00Z",
  "endTime": "2024-01-15T10:40:00Z"
}
```

Jeśli nie wybrano ticketa:
```json
{
  "callId": "call-456",
  "ticketId": null,
  "duration": 600,
  "notes": "",
  "startTime": "2024-01-15T10:30:00Z",
  "endTime": "2024-01-15T10:40:00Z"
}
```

**Odpowiedź (sukces):**
```json
{
  "call": {
    "id": "call-456",
    "ticketId": "ticket-789",
    "duration": 600,
    "startTime": "2024-01-15T10:30:00Z",
    "endTime": "2024-01-15T10:40:00Z"
  },
  "ticket": {
    "id": "ticket-789",
    "status": "in_progress",
    "timeSpent": 10,
    "scheduledDate": "2024-01-15",
    "updatedAt": "2024-01-15T10:40:00Z"
  },
  "previousTicket": {
    "id": "ticket-456",
    "status": "in_progress",
    "updatedAt": "2024-01-15T10:40:00Z"
  }
}
```

**Uwagi:**
- Jeśli `ticketId` jest `null`, poprzedni aktywny ticket jest ustawiany jako 'w toku'
- Jeśli `ticketId` jest podany, ticket jest automatycznie dodawany do grafika bieżącego dnia ze statusem 'w toku'
- Czas połączenia jest dodawany do czasu spędzonego na tickecie

### GET /api/worker/clients/search
Wyszukiwanie klientów podczas tworzenia nowego ticketa.

**Query parameters:**
- `query` (opcjonalne) - wyszukiwanie tekstowe (nazwa, email, telefon)
- `limit` (opcjonalne) - limit wyników (domyślnie 10)

**Odpowiedź (sukces):**
```json
{
  "clients": [
    {
      "id": "client-456",
      "name": "Jan Kowalski",
      "email": "jan@example.com",
      "phone": "+48123456789"
    }
  ],
  "total": 1
}
```

## Przepływ pracy

1. **Rozpoczęcie odbierania telefonu:**
   - Pracownik klika przycisk 'Odbieram telefon'
   - Otwiera się okienko z obsługą telefonu
   - Aplikacja wysyła żądanie `POST /api/worker/phone/receive`
   - Rozpoczyna się rejestracja czasu połączenia
   - Inne aktywne tickety są przerywane i ustawiane na status 'oczekujący'

2. **Wybór lub utworzenie ticketa:**
   - Pracownik może wyszukać istniejący ticket (komponent `TicketSearch`)
   - Pracownik może utworzyć nowy ticket (komponent `TicketCreateForm`)
   - Po wyborze/utworzeniu ticketa, wyświetlane są jego szczegóły

3. **Dodawanie notatek:**
   - Pracownik może dodawać notatki do ticketa podczas rozmowy
   - Notatki są zapisywane na bieżąco lub po zakończeniu połączenia

4. **Zakończenie połączenia:**
   - Pracownik klika przycisk 'Zakończyłem połączenie'
   - Aplikacja wysyła żądanie `POST /api/worker/phone/end` z czasem połączenia
   - Jeśli wybrano ticket, jest on automatycznie dodawany do grafika bieżącego dnia ze statusem 'w toku'
   - Jeśli nie wybrano ticketu, poprzedni aktywny ticket jest ustawiany jako 'w toku'
   - Okienko zamyka się
   - Pracownik wraca do modułu grafika

## Zarządzanie stanem

Moduł zarządza następującymi stanami:

1. **Stan połączenia** - czas rozpoczęcia, czas trwania, czy połączenie jest aktywne
2. **Wybrany ticket** - ticket wybrany lub utworzony podczas rozmowy
3. **Notatki** - notatki dodane do ticketa
4. **Stan wyszukiwania** - wyniki wyszukiwania ticketów i klientów
5. **Stan tworzenia** - dane formularza tworzenia nowego ticketa
6. **Stan ładowania** - informacja o trwających żądaniach API
7. **Błędy** - komunikaty błędów z API

## Rejestracja czasu

Moduł automatycznie rejestruje czas połączenia:

1. **Rozpoczęcie** - czas rozpoczyna się automatycznie po otwarciu okienka
2. **Licznik czasu** - czas jest wyświetlany w czasie rzeczywistym (co sekundę)
3. **Zakończenie** - czas jest rejestrowany do ticketa po zakończeniu połączenia
4. **Przerwanie innych ticketów** - inne aktywne tickety są przerywane i ustawiane na status 'oczekujący'

## Automatyczne przypisanie do grafika

Po zakończeniu połączenia:

1. **Jeśli wybrano/utworzono ticket:**
   - Ticket jest automatycznie dodawany do grafika bieżącego dnia
   - Status ticketa jest ustawiany na 'w toku'
   - Czas połączenia jest dodawany do czasu spędzonego na tickecie
   - Rozpoczyna się rejestracja czasu dla tego ticketa

2. **Jeśli nie wybrano ticketu:**
   - Poprzedni aktywny ticket (jeśli istniał) jest ustawiany jako 'w toku'
   - Rozpoczyna się rejestracja czasu dla poprzedniego ticketa

## Uwagi implementacyjne

1. **Wydajność:**
   - Licznik czasu powinien być zoptymalizowany (użycie `requestAnimationFrame` lub `setInterval` z 1 sekundą)
   - Wyszukiwanie ticketów powinno mieć debounce (~300ms)
   - Lazy loading komponentów, jeśli moduł jest duży
   - Minimalizacja liczby re-renderów

2. **UX:**
   - Intuicyjny interfejs wyszukiwania i tworzenia ticketów
   - Wyraźne wizualne wyróżnienie aktywnego połączenia
   - Czytelne wyświetlanie czasu połączenia
   - Responsywny design (działa na urządzeniach mobilnych)
   - Wyświetlanie wskaźników ładowania podczas operacji
   - Potwierdzenie przed zakończeniem połączenia (opcjonalnie)
   - Możliwość anulowania odbierania telefonu (bez zapisywania czasu)

3. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby
   - Walidacja formularzy przed wysłaniem
   - Obsługa konfliktów (np. jeśli ticket został już zaktualizowany przez innego użytkownika)

4. **Integracja z routingiem:**
   - Moduł może być dostępny jako modal/dialog (bez zmiany URL)
   - Lub jako osobna strona pod ścieżką `/worker/phone-receive`
   - Moduł powinien być chroniony przed dostępem dla nieautoryzowanych użytkowników
   - Możliwość otwarcia modułu z modułu grafika pracownika

5. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API)
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu odbierania telefonu
   - Testy dla rejestracji czasu
   - Testy dla automatycznego przypisania do grafika

6. **Format czasu:**
   - Czas połączenia powinien być wyświetlany w formacie czytelnym (MM:SS lub HH:MM:SS)
   - Czas powinien być zapisywany w sekundach lub minutach (zgodnie z API)
   - Obsługa różnych stref czasowych (jeśli potrzebne)

7. **Wyszukiwanie:**
   - Wyszukiwanie ticketów powinno być szybkie i responsywne
   - Możliwość filtrowania wyników (kategoria, status)
   - Wyświetlanie podpowiedzi podczas wpisywania
   - Sortowanie wyników (np. według daty utworzenia, priorytetu)

8. **Tworzenie ticketa:**
   - Formularz powinien być prosty i intuicyjny
   - Walidacja pól w czasie rzeczywistym
   - Możliwość szybkiego utworzenia klienta (jeśli nie istnieje)
   - Wybór kategorii tylko z dostępnych dla pracownika

9. **Notatki:**
   - Możliwość dodawania notatek w czasie rzeczywistym lub po zakończeniu połączenia
   - Formatowanie tekstu (opcjonalnie)
   - Wyświetlanie istniejących notatek ticketa

10. **Anulowanie:**
    - Możliwość anulowania odbierania telefonu bez zapisywania czasu
    - Przywrócenie poprzedniego stanu (aktywny ticket, statusy)
    - Dialog potwierdzenia przed anulowaniem

