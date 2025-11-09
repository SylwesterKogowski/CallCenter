# Moduł chatu ticketa

## Opis modułu

Moduł chatu ticketa jest komponentem Reactowym, który umożliwia kontynuację rozmowy w ramach ticketa. Moduł jest uruchamiany automatycznie po utworzeniu nowego ticketa przez klienta (z modułu `ticket-add`) i umożliwia dalszą komunikację między klientem a pracownikami Call Center.

Moduł działa bez autentykacji - klienci mogą komunikować się z pracownikami bez konieczności logowania się do systemu. Moduł odbiera na bieżąco nowe wiadomości od pracowników poprzez Server-Sent Events (SSE) wysyłane przez Mercure na backendzie.

## Funkcjonalność

1. **Wyświetlanie historii wiadomości** - moduł wyświetla wszystkie wiadomości w ramach ticketa (zarówno od klienta, jak i od pracowników)
2. **Wysyłanie wiadomości** - klient może wysyłać dalsze wiadomości do ticketa, kontynuując rozmowę
3. **Odbieranie wiadomości w czasie rzeczywistym** - moduł odbiera na bieżąco nowe wiadomości od pracowników poprzez Server-Sent Events (Mercure)
4. **Wyświetlanie statusu ticketa** - moduł pokazuje aktualny status ticketa (np. oczekujący, w toku, zamknięty)
5. **Wskaźnik pisania** - opcjonalnie może wyświetlać informację, że pracownik pisze odpowiedź
6. **Obsługa błędów połączenia** - moduł obsługuje przerwania połączenia SSE i automatyczne ponowne połączenie

## Podkomponenty

### TicketChat (Główny komponent chatu)
Główny komponent chatu, który zarządza stanem rozmowy i koordynuje wszystkie podkomponenty.

**Funkcjonalność:**
- Zarządzanie stanem wiadomości (lista wiadomości, nowa wiadomość do wysłania)
- Inicjalizacja połączenia SSE z Mercure dla danego ticketa
- Obsługa wysyłania wiadomości do API
- Obsługa odbierania wiadomości przez SSE
- Zarządzanie stanem połączenia SSE (połączony, rozłączony, błąd)
- Automatyczne ponowne połączenie przy przerwaniu połączenia
- Pobieranie historii wiadomości przy inicjalizacji
- Wyświetlanie statusu ticketa
- Obsługa błędów z API i SSE

**Props:**
- `ticketId: string` - ID ticketa, dla którego wyświetlany jest chat (wymagane)
- `onTicketStatusChange?: (status: TicketStatus) => void` - opcjonalna funkcja callback wywoływana przy zmianie statusu ticketa

**Interfejsy:**
```typescript
interface TicketStatus {
  id: string;
  status: 'awaiting_response' | 'in_progress' | 'awaiting_client' | 'closed';
  categoryId: string;
  title?: string;
  description?: string;
  createdAt: string;
  updatedAt: string;
}
```

### MessageList (Lista wiadomości)
Komponent wyświetlający listę wszystkich wiadomości w ramach ticketa.

**Funkcjonalność:**
- Wyświetlanie wiadomości w kolejności chronologicznej
- Rozróżnienie wiadomości od klienta i od pracowników (różne style wizualne)
- Wyświetlanie daty i godziny wiadomości
- Wyświetlanie nazwy nadawcy (dla wiadomości od pracowników)
- Automatyczne przewijanie do najnowszej wiadomości
- Obsługa długich wiadomości (z możliwością rozwinięcia)
- Wyświetlanie statusu dostarczenia wiadomości (wysłana, dostarczona, odczytana - opcjonalnie)

**Props:**
- `messages: Message[]` - tablica wiadomości do wyświetlenia
- `ticketId: string` - ID ticketa (do identyfikacji nadawcy wiadomości)

**Interfejsy:**
```typescript
interface Message {
  id: string;
  ticketId: string;
  senderType: 'client' | 'worker';
  senderId?: string; // ID pracownika (jeśli senderType === 'worker')
  senderName?: string; // Nazwa pracownika (jeśli senderType === 'worker')
  content: string;
  createdAt: string;
  status?: 'sent' | 'delivered' | 'read'; // opcjonalny status dostarczenia
}
```

### MessageInput (Pole wprowadzania wiadomości)
Komponent formularza do wprowadzania i wysyłania nowej wiadomości.

**Funkcjonalność:**
- Pole tekstowe do wprowadzania wiadomości (textarea)
- Przycisk wysyłania wiadomości
- Walidacja długości wiadomości (maksimum znaków)
- Obsługa wysyłania wiadomości przez Enter (z możliwością Shift+Enter dla nowej linii)
- Wyświetlanie stanu ładowania podczas wysyłania
- Dezaktywacja pola podczas wysyłania wiadomości
- Licznik znaków (opcjonalnie)
- Obsługa błędów przy wysyłaniu

**Props:**
- `onSend: (content: string) => Promise<void>` - funkcja wywoływana przy wysłaniu wiadomości
- `isLoading: boolean` - czy wiadomość jest w trakcie wysyłania
- `isDisabled?: boolean` - czy pole powinno być wyłączone (np. gdy ticket jest zamknięty)
- `maxLength?: number` - maksymalna długość wiadomości (domyślnie np. 5000 znaków)
- `placeholder?: string` - tekst placeholder w polu tekstowym

### ConnectionStatus (Status połączenia)
Komponent wyświetlający status połączenia SSE z Mercure.

**Funkcjonalność:**
- Wyświetlanie statusu połączenia (połączony, rozłączony, łączenie)
- Wyświetlanie informacji o błędzie połączenia
- Automatyczne ponowne połączenie przy rozłączeniu
- Wskaźnik wizualny statusu (np. kolorowa kropka)

**Props:**
- `status: 'connected' | 'disconnected' | 'connecting' | 'error'` - status połączenia
- `error?: string` - opcjonalny komunikat błędu
- `onRetry?: () => void` - opcjonalna funkcja do ręcznego ponowienia połączenia

### TicketStatusDisplay (Wyświetlanie statusu ticketa)
Komponent wyświetlający aktualny status ticketa.

**Funkcjonalność:**
- Wyświetlanie statusu ticketa w czytelny sposób
- Różne kolory/ikony dla różnych statusów
- Wyświetlanie informacji o kategorii ticketa
- Wyświetlanie daty utworzenia/aktualizacji ticketa

**Props:**
- `ticket: TicketStatus` - obiekt ze statusem ticketa

### TypingIndicator (Wskaźnik pisania)
Komponent wyświetlający informację, że pracownik pisze odpowiedź (opcjonalny).

**Funkcjonalność:**
- Wyświetlanie animacji "pracownik pisze..."
- Odbieranie sygnałów o pisaniu przez SSE (jeśli backend to obsługuje)
- Automatyczne ukrywanie po określonym czasie bezczynności

**Props:**
- `isTyping: boolean` - czy pracownik aktualnie pisze
- `workerName?: string` - opcjonalna nazwa pracownika, który pisze

### ErrorDisplay (Wyświetlanie błędów)
Komponent do wyświetlania błędów (z API, z połączenia SSE, walidacji).

**Funkcjonalność:**
- Wyświetlanie błędów z API (np. błąd wysyłania wiadomości)
- Wyświetlanie błędów połączenia SSE
- Wyświetlanie błędów walidacji
- Możliwość zamknięcia/ukrycia błędów
- Możliwość ponowienia operacji (np. ponowne połączenie)

**Props:**
- `errors: ChatErrors` - obiekt z błędami do wyświetlenia

**Interfejs błędów:**
```typescript
interface ChatErrors {
  message?: string; // błąd przy wysyłaniu wiadomości
  connection?: string; // błąd połączenia SSE
  api?: string; // ogólny błąd API
  general?: string; // ogólny błąd
}
```

### LoadingSpinner (Wskaźnik ładowania)
Komponent wyświetlający wskaźnik ładowania podczas pobierania danych.

**Funkcjonalność:**
- Wyświetlanie animacji ładowania podczas pobierania historii wiadomości
- Możliwość wyświetlenia z komunikatem tekstowym

**Props:**
- `message?: string` - opcjonalny komunikat do wyświetlenia podczas ładowania

## Integracja z API

Moduł komunikuje się z backendem przez następujące endpointy:

### GET /api/tickets/:ticketId
Pobranie szczegółów ticketa wraz z historią wiadomości.

**Odpowiedź:**
```json
{
  "ticket": {
    "id": "550e8400-e29b-41d4-a716-446655440010",
    "clientId": "550e8400-e29b-41d4-a716-446655440001",
    "categoryId": "550e8400-e29b-41d4-a716-446655440001",
    "categoryName": "Sprzedaż",
    "title": "Problem z połączeniem",
    "description": "Nie mogę połączyć się z internetem",
    "status": "awaiting_response",
    "createdAt": "2024-01-15T10:00:00Z",
    "updatedAt": "2024-01-15T10:05:00Z"
  },
  "messages": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440020",
      "ticketId": "550e8400-e29b-41d4-a716-446655440010",
      "senderType": "client",
      "content": "Witam, mam problem z połączeniem",
      "createdAt": "2024-01-15T10:00:00Z"
    },
    {
      "id": "550e8400-e29b-41d4-a716-446655440021",
      "ticketId": "550e8400-e29b-41d4-a716-446655440010",
      "senderType": "worker",
      "senderId": "550e8400-e29b-41d4-a716-446655440030",
      "senderName": "Jan Kowalski",
      "content": "Dzień dobry, pomogę Panu z tym problemem",
      "createdAt": "2024-01-15T10:02:00Z"
    }
  ]
}
```

### POST /api/tickets/:ticketId/messages
Wysłanie nowej wiadomości do ticketa.

**Request body:**
```json
{
  "content": "Dziękuję za pomoc"
}
```

**Odpowiedź (sukces):**
```json
{
  "message": {
    "id": "550e8400-e29b-41d4-a716-446655440022",
    "ticketId": "550e8400-e29b-41d4-a716-446655440010",
    "senderType": "client",
    "content": "Dziękuję za pomoc",
    "createdAt": "2024-01-15T10:10:00Z"
  }
}
```

**Odpowiedź (błąd):**
```json
{
  "error": "Validation failed",
  "errors": {
    "content": "Wiadomość nie może być pusta"
  }
}
```

## Integracja z Mercure (Server-Sent Events)

Moduł odbiera nowe wiadomości od pracowników w czasie rzeczywistym poprzez Server-Sent Events wysyłane przez Mercure.

### Konfiguracja połączenia SSE

Moduł łączy się z Mercure Hub używając endpointu SSE:
```
GET /hub?topic=tickets/{ticketId}
```

Lub jeśli Mercure jest skonfigurowany inaczej:
```
GET {MERCURE_HUB_URL}?topic=tickets/{ticketId}
```

### Format wiadomości z Mercure

Gdy pracownik wyśle wiadomość, Mercure wysyła event SSE z następującą strukturą:

**Event: message**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440023",
  "ticketId": "550e8400-e29b-41d4-a716-446655440010",
  "senderType": "worker",
  "senderId": "550e8400-e29b-41d4-a716-446655440030",
  "senderName": "Jan Kowalski",
  "content": "Sprawdzam teraz Pańskie konto",
  "createdAt": "2024-01-15T10:15:00Z"
}
```

**Event: ticket_status_changed**
```json
{
  "ticketId": "550e8400-e29b-41d4-a716-446655440010",
  "status": "in_progress",
  "updatedAt": "2024-01-15T10:15:00Z"
}
```

**Event: typing** (opcjonalny)
```json
{
  "ticketId": "550e8400-e29b-41d4-a716-446655440010",
  "workerId": "550e8400-e29b-41d4-a716-446655440030",
  "workerName": "Jan Kowalski",
  "isTyping": true
}
```

### Obsługa połączenia SSE

1. **Inicjalizacja połączenia:**
   - Po załadowaniu komponentu, moduł inicjuje połączenie SSE z Mercure
   - Używa `EventSource` API do połączenia z Mercure Hub
   - Subskrybuje topic `tickets/{ticketId}`

2. **Odbieranie wiadomości:**
   - Moduł nasłuchuje na eventy typu `message`
   - Po otrzymaniu wiadomości, dodaje ją do listy wiadomości
   - Automatycznie przewija do najnowszej wiadomości

3. **Obsługa zmiany statusu:**
   - Moduł nasłuchuje na eventy typu `ticket_status_changed`
   - Aktualizuje status ticketa w interfejsie
   - Może wywołać callback `onTicketStatusChange` jeśli został przekazany

4. **Obsługa wskaźnika pisania:**
   - Moduł nasłuchuje na eventy typu `typing` (jeśli obsługiwane)
   - Wyświetla wskaźnik pisania, gdy `isTyping === true`
   - Ukrywa wskaźnik po określonym czasie bezczynności lub gdy `isTyping === false`

5. **Obsługa rozłączenia:**
   - Moduł automatycznie wykrywa rozłączenie połączenia SSE
   - Próbuje automatycznie ponownie połączyć się po krótkim opóźnieniu (exponential backoff)
   - Wyświetla komunikat o rozłączeniu użytkownikowi
   - Po ponownym połączeniu, moduł może pobrać brakujące wiadomości z API

6. **Obsługa błędów:**
   - Moduł obsługuje błędy połączenia SSE
   - Wyświetla komunikat błędu użytkownikowi
   - Umożliwia ręczne ponowienie połączenia

## Walidacja

### Walidacja wiadomości:
- Treść wiadomości nie może być pusta (po usunięciu białych znaków)
- Maksymalna długość wiadomości: 5000 znaków (konfigurowalne)
- Wiadomość nie może zawierać tylko białych znaków

## Integracja z modułem ticket-add

Moduł `ticket-chat` jest automatycznie uruchamiany po utworzeniu nowego ticketa przez moduł `ticket-add`. Przekierowanie może być realizowane przez:

- React Router: `navigate('/ticket-chat/:ticketId')`
- Lub przekazanie danych przez kontekst/state management

Moduł `ticket-chat` przejmuje dalszą komunikację z klientem, umożliwiając:
- Wyświetlanie wiadomości od pracowników (Server-Sent Events przez Mercure)
- Wysyłanie odpowiedzi od klienta
- Śledzenie statusu ticketa

## Wymogi dostępności

Moduł musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA), aby umożliwić korzystanie z niego użytkownikom z niepełnosprawnościami:

1. **Semantyczne HTML:**
   - Użycie semantycznych elementów HTML (`<article>`, `<section>`, `<header>`)
   - Właściwa struktura nagłówków (`<h1>`, `<h2>`, etc.)
   - Lista wiadomości powinna używać semantycznych elementów (`<ul>`, `<li>` lub `<article>`)

2. **Etykiety i opisy:**
   - Pole wprowadzania wiadomości musi mieć widoczną etykietę (`<label>`)
   - Przycisk wysyłania musi mieć opisową etykietę (`aria-label` lub tekst)
   - Status połączenia powinien być dostępny tekstowo, nie tylko wizualnie

3. **Nawigacja klawiaturą:**
   - Wszystkie elementy interaktywne muszą być dostępne za pomocą klawiatury (Tab, Enter, Space)
   - Logiczna kolejność tabulacji
   - Widoczny wskaźnik fokusa dla wszystkich elementów interaktywnych (min. 2px obramowanie)
   - Obsługa Enter do wysłania wiadomości, Shift+Enter dla nowej linii

4. **ARIA atrybuty:**
   - `aria-label` lub `aria-labelledby` dla pola wprowadzania wiadomości
   - `aria-live="polite"` dla obszaru z wiadomościami (nowe wiadomości)
   - `aria-live="assertive"` dla ważnych komunikatów (błędy, rozłączenie)
   - `aria-atomic="false"` dla obszaru wiadomości (tylko nowe wiadomości są ogłaszane)
   - `aria-busy="true"` podczas ładowania historii wiadomości
   - `aria-disabled="true"` dla pola wprowadzania, gdy ticket jest zamknięty

5. **Komunikaty i statusy:**
   - Wszystkie komunikaty błędów muszą być dostępne dla czytników ekranu
   - Status połączenia SSE powinien być ogłaszany przez `aria-live`
   - Zmiana statusu ticketa powinna być ogłaszana użytkownikowi
   - Wskaźnik pisania powinien mieć tekstową alternatywę

6. **Wiadomości:**
   - Każda wiadomość powinna mieć unikalny `id` dla możliwości nawigacji
   - Wiadomości powinny być oznaczone jako od klienta lub pracownika (`aria-label` lub tekst)
   - Data i godzina wiadomości powinny być dostępne tekstowo
   - Długie wiadomości powinny mieć możliwość rozwinięcia z odpowiednimi etykietami

7. **Kontrast kolorów:**
   - Minimalny kontrast tekstu do tła: 4.5:1 dla zwykłego tekstu, 3:1 dla dużego tekstu
   - Rozróżnienie wiadomości od klienta i pracownika nie może opierać się wyłącznie na kolorze (różne ikony, tekst lub kształty)

8. **Focus management:**
   - Po wysłaniu wiadomości, fokus powinien wrócić do pola wprowadzania
   - Automatyczne przewijanie do nowych wiadomości nie powinno przerywać nawigacji klawiaturą
   - Możliwość wyłączenia automatycznego przewijania dla użytkowników czytników ekranu

9. **Obsługa dynamicznych treści:**
   - Nowe wiadomości powinny być ogłaszane przez `aria-live="polite"`
   - Ważne zmiany (błędy, rozłączenie) powinny używać `aria-live="assertive"`
   - Wskaźnik ładowania powinien mieć tekstową alternatywę

10. **Responsywność:**
    - Chat powinien działać poprawnie na urządzeniach mobilnych
    - Minimalny rozmiar obszarów klikalnych: 44x44px
    - Odpowiednie rozmiary czcionek (minimum 16px dla pól formularza na mobile)

11. **Struktura treści:**
    - Logiczna struktura nagłówków dla sekcji chatu
    - Każda wiadomość powinna być semantycznym elementem (`<article>` lub `<li>`)
    - Regiony ARIA dla głównych sekcji (`role="region"` z `aria-label`)

12. **Status połączenia:**
    - Status połączenia SSE powinien być dostępny tekstowo
    - Komunikaty o rozłączeniu powinny być ogłaszane przez `aria-live="assertive"`
    - Przycisk ponownego połączenia powinien mieć opisową etykietę

## Uwagi implementacyjne

1. **Bezpieczeństwo:**
   - Moduł działa bez autentykacji, ale ticket jest identyfikowany przez ID
   - Backend powinien weryfikować, czy klient ma dostęp do danego ticketa (np. przez token w URL lub cookie)
   - Wszystkie dane są wysyłane przez HTTPS
   - Mercure powinien być skonfigurowany z odpowiednimi uprawnieniami (tylko subskrypcja, bez publikacji od klienta)

2. **Performance:**
   - Historia wiadomości może być paginowana (pobieranie starszych wiadomości na żądanie)
   - Lista wiadomości powinna być zoptymalizowana pod kątem renderowania (np. virtual scrolling dla dużej liczby wiadomości)
   - Połączenie SSE powinno być zamykane przy unmount komponentu
   - Moduł powinien cache'ować historię wiadomości w pamięci komponentu

3. **UX:**
   - Chat powinien być intuicyjny i łatwy w użyciu
   - Wiadomości powinny być wyraźnie rozróżnione (klient vs pracownik)
   - Automatyczne przewijanie do najnowszej wiadomości (z możliwością wyłączenia)
   - Wskaźnik ładowania podczas pobierania historii
   - Komunikaty błędów powinny być pomocne i zrozumiałe
   - Chat powinien być responsywny (działać na urządzeniach mobilnych)
   - Obsługa klawiatury (Enter do wysłania, Shift+Enter dla nowej linii)

4. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy połączenia SSE powinny być obsługiwane z automatycznym ponownym połączeniem
   - Błędy walidacji powinny być wyświetlane przy polu wprowadzania
   - Moduł powinien obsługiwać sytuację, gdy ticket nie istnieje lub został usunięty

5. **Obsługa statusu ticketa:**
   - Gdy ticket jest zamknięty, pole wprowadzania wiadomości powinno być wyłączone
   - Wyświetlanie odpowiedniego komunikatu, gdy ticket jest zamknięty
   - Aktualizacja statusu w czasie rzeczywistym przez SSE

6. **Mercure:**
   - Moduł powinien używać biblioteki do obsługi Mercure (np. `@mercure/client` lub natywnego `EventSource`)
   - Konfiguracja URL Mercure Hub powinna być konfigurowalna (zmienna środowiskowa)
   - Obsługa autoryzacji Mercure (jeśli wymagana) - token JWT w parametrze URL
   - Moduł powinien obsługiwać różne typy eventów z Mercure (message, ticket_status_changed, typing)

7. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API i SSE)
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla połączenia SSE (mock EventSource)

