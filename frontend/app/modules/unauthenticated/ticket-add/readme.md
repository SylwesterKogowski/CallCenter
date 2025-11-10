# Moduł dodawania ticketów przez klientów

## Opis modułu

Moduł dodawania ticketów jest komponentem Reactowym, który umożliwia klientom (w tym anonimowym) utworzenie nowego ticketa w systemie Call Center. Moduł zbiera wszystkie dane potrzebne do zainicjalizowania ticketa, a następnie automatycznie uruchamia moduł `ticket-chat`, który przejmuje dalszą komunikację z klientem.

Moduł działa bez autentykacji - klienci mogą tworzyć tickety bez konieczności logowania się do systemu. Klient może być anonimowy (bez pełnych danych osobowych) lub zidentyfikowany (z pełnymi danymi kontaktowymi).

## Funkcjonalność

1. **Zbieranie danych klienta** - formularz umożliwia wprowadzenie danych kontaktowych klienta (email, telefon, imię, nazwisko - wszystkie pola opcjonalne)
2. **Wybór kategorii ticketa** - klient wybiera kategorię (kolejkę tematyczną) z listy dostępnych kategorii (wymagane)
3. **Wprowadzenie tytułu i opisu** - klient może opcjonalnie podać tytuł i opis problemu/zgłoszenia
4. **Walidacja danych** - weryfikacja poprawności wprowadzonych danych przed wysłaniem
5. **Utworzenie ticketa** - wysłanie żądania do API backendu w celu utworzenia nowego ticketa
6. **Przekierowanie do chatu** - po pomyślnym utworzeniu ticketa, moduł automatycznie uruchamia moduł `ticket-chat` z ID nowo utworzonego ticketa

## Podkomponenty

### TicketAddForm (Główny komponent formularza)
Główny komponent formularza, który zarządza stanem formularza i koordynuje wszystkie podkomponenty.

**Funkcjonalność:**
- Zarządzanie stanem formularza (dane klienta, kategoria, tytuł, opis)
- Walidacja danych przed wysłaniem
- Obsługa wysyłania formularza do API
- Obsługa błędów z API
- Przekierowanie do modułu `ticket-chat` po pomyślnym utworzeniu ticketa
- Wyświetlanie stanu ładowania podczas przetwarzania żądania

**Props:**
- `onTicketCreated?: (ticketId: string) => void` - opcjonalna funkcja callback wywoływana po utworzeniu ticketa

### ClientDataForm (Formularz danych klienta)
Komponent formularza do wprowadzania danych kontaktowych klienta.

**Funkcjonalność:**
- Pole email (opcjonalne, walidacja formatu email)
- Pole telefon (opcjonalne, walidacja formatu numeru telefonu)
- Pole imię (opcjonalne)
- Pole nazwisko (opcjonalne)
- Walidacja, że co najmniej jedno pole kontaktowe (email lub telefon) jest wypełnione
- Wyświetlanie komunikatów błędów walidacji

**Props:**
- `clientData: ClientData` - obiekt z danymi klienta
- `onChange: (data: ClientData) => void` - funkcja wywoływana przy zmianie danych
- `errors?: ClientDataErrors` - opcjonalne błędy walidacji do wyświetlenia

**Interfejsy:**
```typescript
interface ClientData {
  email?: string;
  phone?: string;
  firstName?: string;
  lastName?: string;
}

interface ClientDataErrors {
  email?: string;
  phone?: string;
  firstName?: string;
  lastName?: string;
  general?: string; // np. "Wymagane jest podanie emaila lub telefonu"
}
```

### CategorySelector (Selektor kategorii)
Komponent do wyboru kategorii ticketa z listy dostępnych kategorii.

**Funkcjonalność:**
- Pobieranie listy dostępnych kategorii z API backendu
- Wyświetlanie listy kategorii (dropdown, radio buttons lub lista)
- Wybór kategorii (wymagane pole)
- Wyświetlanie opisu kategorii (jeśli dostępny)
- Wyświetlanie stanu ładowania podczas pobierania kategorii
- Obsługa błędów przy pobieraniu kategorii

**Props:**
- `selectedCategoryId?: string` - ID wybranej kategorii
- `onChange: (categoryId: string) => void` - funkcja wywoływana przy wyborze kategorii
- `error?: string` - opcjonalny komunikat błędu

**Interfejs kategorii:**
```typescript
interface TicketCategory {
  id: string;
  name: string;
  description?: string;
  defaultResolutionTimeMinutes: number;
}
```

### TicketDetailsForm (Formularz szczegółów ticketa)
Komponent do wprowadzania tytułu i opisu ticketa.

**Funkcjonalność:**
- Pole tytułu ticketa (opcjonalne, pole tekstowe)
- Pole opisu problemu/zgłoszenia (opcjonalne, pole tekstowe wieloliniowe)
- Licznik znaków dla pola opisu (opcjonalnie)
- Walidacja długości pól (jeśli wymagane)

**Props:**
- `title?: string` - tytuł ticketa
- `description?: string` - opis ticketa
- `onChange: (data: { title?: string; description?: string }) => void` - funkcja wywoływana przy zmianie danych
- `errors?: { title?: string; description?: string }` - opcjonalne błędy walidacji

### SubmitButton (Przycisk wysyłania)
Komponent przycisku do wysłania formularza.

**Funkcjonalność:**
- Wyświetlanie stanu ładowania podczas przetwarzania żądania
- Wyświetlanie tekstu przycisku (np. "Utwórz ticket", "Wysyłanie...")
- Dezaktywacja przycisku podczas przetwarzania lub gdy formularz jest nieprawidłowy
- Obsługa kliknięcia i wywołanie funkcji onSubmit z formularza

**Props:**
- `isLoading: boolean` - czy formularz jest w trakcie przetwarzania
- `isDisabled?: boolean` - czy przycisk powinien być wyłączony
- `onClick: () => void` - funkcja wywoływana przy kliknięciu

### ErrorDisplay (Wyświetlanie błędów)
Komponent do wyświetlania błędów walidacji i błędów z API.

**Funkcjonalność:**
- Wyświetlanie błędów walidacji pól formularza
- Wyświetlanie błędów z API (np. błąd sieci, błąd serwera)
- Wyświetlanie komunikatów błędów w czytelny sposób
- Możliwość zamknięcia/ukrycia błędów

**Props:**
- `errors: FormErrors` - obiekt z błędami do wyświetlenia
- `apiError?: string` - opcjonalny błąd z API

**Interfejs błędów:**
```typescript
interface FormErrors {
  client?: ClientDataErrors;
  category?: string;
  title?: string;
  description?: string;
  general?: string; // ogólny błąd formularza
}
```

### LoadingSpinner (Wskaźnik ładowania)
Komponent wyświetlający wskaźnik ładowania podczas przetwarzania żądań.

**Funkcjonalność:**
- Wyświetlanie animacji ładowania
- Możliwość wyświetlenia z komunikatem tekstowym

**Props:**
- `message?: string` - opcjonalny komunikat do wyświetlenia podczas ładowania

## Integracja z API

Moduł komunikuje się z backendem przez następujące endpointy:

### GET /api/ticket-categories
Pobranie listy dostępnych kategorii ticketów.

**Odpowiedź:**
```json
{
  "categories": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Sprzedaż",
      "description": "Kategoria dla ticketów związanych ze sprzedażą produktów i usług",
      "defaultResolutionTimeMinutes": 30
    },
    ...
  ]
}
```

### POST /api/tickets
Utworzenie nowego ticketa.

**Request body:**
```json
{
  "client": {
    "email": "jan.kowalski@example.com",
    "phone": "+48123456789",
    "firstName": "Jan",
    "lastName": "Kowalski"
  },
  "categoryId": "550e8400-e29b-41d4-a716-446655440001",
  "title": "Problem z połączeniem",
  "description": "Nie mogę połączyć się z internetem"
}
```

**Odpowiedź (sukces):**
```json
{
  "ticket": {
    "id": "550e8400-e29b-41d4-a716-446655440010",
    "clientId": "550e8400-e29b-41d4-a716-446655440001",
    "categoryId": "550e8400-e29b-41d4-a716-446655440001",
    "title": "Problem z połączeniem",
    "description": "Nie mogę połączyć się z internetem",
    "status": "awaiting_response",
    "createdAt": "2024-01-15T10:00:00Z"
  }
}
```

**Odpowiedź (błąd):**
```json
{
  "error": "Validation failed",
  "errors": {
    "categoryId": "Kategoria jest wymagana",
    "client.email": "Nieprawidłowy format email"
  }
}
```

## Walidacja

### Walidacja danych klienta:
- Email: format email (jeśli podany)
- Telefon: format numeru telefonu (jeśli podany, opcjonalnie z prefiksem kraju)
- Co najmniej jedno pole kontaktowe (email lub telefon) musi być wypełnione
- Imię i nazwisko: minimum 2 znaki, maksimum 100 znaków (jeśli podane)

### Walidacja kategorii:
- Kategoria jest wymagana
- Kategoria musi istnieć w systemie

### Walidacja tytułu i opisu:
- Tytuł: maksimum 255 znaków (jeśli podany)
- Opis: maksimum 5000 znaków (jeśli podany)

## Integracja z modułem ticket-chat

Po pomyślnym utworzeniu ticketa, moduł automatycznie przekierowuje użytkownika do modułu `ticket-chat` z ID nowo utworzonego ticketa. Przekierowanie może być realizowane przez:

- React Router: `navigate('/ticket-chat/:ticketId')`
- Lub przekazanie danych przez kontekst/state management

Moduł `ticket-chat` przejmuje dalszą komunikację z klientem, umożliwiając:
- Wyświetlanie wiadomości od pracowników (Server-Sent Events przez Mercure)
- Wysyłanie odpowiedzi od klienta
- Śledzenie statusu ticketa

## Wymogi dostępności

Moduł musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA), aby umożliwić korzystanie z niego użytkownikom z niepełnosprawnościami:

1. **Semantyczne HTML:**
   - Wszystkie pola formularza muszą mieć odpowiednie etykiety (`<label>`)
   - Użycie semantycznych elementów HTML (`<form>`, `<fieldset>`, `<legend>`)
   - Właściwa struktura nagłówków (`<h1>`, `<h2>`, etc.)

2. **Etykiety i opisy pól:**
   - Każde pole formularza musi mieć widoczną etykietę powiązaną z polem (`htmlFor` i `id`)
   - Pola wymagane powinny być oznaczone tekstem (np. "Email (wymagane)" lub asterisk z opisem)
   - Pola opcjonalne powinny być wyraźnie oznaczone (np. "Imię (opcjonalne)")
   - Komunikaty błędów walidacji muszą być powiązane z odpowiednimi polami (`aria-describedby`, `aria-invalid`)

3. **Nawigacja klawiaturą:**
   - Wszystkie elementy interaktywne muszą być dostępne za pomocą klawiatury (Tab, Enter, Space)
   - Logiczna kolejność tabulacji (od góry do dołu, od lewej do prawej)
   - Widoczny wskaźnik fokusa dla wszystkich elementów interaktywnych (min. 2px obramowanie)
   - Możliwość wysłania formularza klawiszem Enter

4. **ARIA atrybuty:**
   - `aria-required="true"` dla pól wymaganych
   - `aria-invalid="true"` dla pól z błędami walidacji
   - `aria-describedby` łączący pola z komunikatami błędów
   - `aria-live="polite"` dla dynamicznych komunikatów (np. stan ładowania, błędy)
   - `aria-busy="true"` dla formularza podczas przetwarzania

5. **Komunikaty błędów:**
   - Komunikaty błędów muszą być dostępne dla czytników ekranu
   - Błędy powinny być wyświetlane zarówno wizualnie, jak i tekstowo
   - Komunikaty błędów powinny być zrozumiałe i wskazywać sposób poprawy

6. **Kontrast kolorów:**
   - Minimalny kontrast tekstu do tła: 4.5:1 dla zwykłego tekstu, 3:1 dla dużego tekstu
   - Informacje nie mogą być przekazywane wyłącznie przez kolor (np. błędy powinny mieć ikonę lub tekst, nie tylko czerwony kolor)

7. **Stan ładowania:**
   - Wskaźnik ładowania powinien mieć tekstową alternatywę (`aria-label` lub tekst widoczny)
   - Komunikat o stanie przetwarzania powinien być dostępny dla czytników ekranu (`aria-live`)

8. **Responsywność:**
   - Formularz powinien działać poprawnie na urządzeniach mobilnych
   - Minimalny rozmiar obszarów klikalnych: 44x44px
   - Odpowiednie rozmiary czcionek (minimum 16px dla pól formularza na mobile)

9. **Struktura treści:**
   - Logiczna struktura nagłówków (h1 → h2 → h3)
   - Grupowanie powiązanych pól w `<fieldset>` z `<legend>`
   - Użycie list (`<ul>`, `<ol>`) dla list kategorii

10. **Focus management:**
    - Po wysłaniu formularza, fokus powinien zostać przeniesiony do odpowiedniego miejsca (np. komunikat sukcesu lub pierwsze pole z błędem)
    - Automatyczne przewijanie do błędów walidacji (z możliwością wyłączenia)

## Uwagi implementacyjne

1. **Klienci anonimowi:**
   - Klient może utworzyć ticket bez pełnych danych osobowych
   - Wystarczy podanie emaila lub telefonu
   - System automatycznie utworzy klienta anonimowego, jeśli nie ma pełnych danych

2. **Identyfikacja istniejącego klienta:**
   - System powinien sprawdzić, czy klient o danym emailu już istnieje
   - Jeśli klient istnieje, należy użyć istniejącego rekordu zamiast tworzyć nowy
   - Backend automatycznie obsługuje tę logikę

3. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy walidacji powinny być wyświetlane przy odpowiednich polach
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby

4. **UX:**
   - Formularz powinien być intuicyjny i łatwy w użyciu
   - Pola opcjonalne powinny być wyraźnie oznaczone
   - Komunikaty błędów powinny być pomocne i zrozumiałe
   - Formularz powinien być responsywny (działać na urządzeniach mobilnych)

5. **Bezpieczeństwo:**
   - Wszystkie dane są wysyłane przez HTTPS
   - Walidacja po stronie klienta nie zastępuje walidacji po stronie serwera
   - Klienci nie mają dostępu do danych innych klientów

6. **Performance:**
   - Lista kategorii może być cache'owana w pamięci komponentu
   - Formularz powinien być zoptymalizowany pod kątem szybkości działania
   - Lazy loading komponentów, jeśli moduł jest duży

