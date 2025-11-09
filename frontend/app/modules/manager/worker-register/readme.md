# Moduł dodawania pracowników

## Opis modułu

Moduł dodawania pracowników jest komponentem Reactowym, który umożliwia managerowi zarejestrowanie nowego pracownika w systemie Call Center. Moduł zawiera wyłącznie formularz rejestracji pracownika. Layout strony (tytuł, header, nawigacja) jest obsługiwany przez inny komponent.

Moduł jest dostępny wyłącznie dla zalogowanych pracowników z rolą managera. Manager może utworzyć nowe konto pracownicze z loginem i hasłem oraz przypisać pracownikowi uprawnienia do wybranych kategorii ticketów (kolejek tematycznych).

## Funkcjonalność

1. **Formularz rejestracji** - formularz rejestracji pracownika zawierający:
   - Pole loginu pracownika (wymagane)
   - Pole hasła pracownika (wymagane)
   - Pole potwierdzenia hasła (wymagane)
   - Lista checkboxów z kategoriami ticketów do przypisania uprawnień
   - Checkbox do oznaczenia pracownika jako managera (opcjonalnie)
2. **Walidacja danych** - weryfikacja poprawności wprowadzonych danych przed wysłaniem
3. **Wysyłanie żądania rejestracji** - wysłanie żądania do API backendu w celu utworzenia nowego konta pracowniczego
4. **Obsługa błędów** - wyświetlanie komunikatów błędów w przypadku nieprawidłowych danych lub problemów z połączeniem
5. **Komunikat sukcesu** - wyświetlanie komunikatu sukcesu po pomyślnej rejestracji z możliwością rejestracji kolejnego pracownika

## Podkomponenty

### WorkerRegisterForm (Główny komponent formularza)
Główny komponent formularza rejestracji, który zarządza stanem formularza i koordynuje wszystkie podkomponenty. Jest to główny eksportowany komponent modułu.

**Funkcjonalność:**
- Zarządzanie stanem formularza (login, hasło, potwierdzenie hasła, wybrane kategorie, rola managera)
- Walidacja danych przed wysłaniem
- Obsługa wysyłania formularza do API
- Obsługa błędów z API
- Wyświetlanie stanu ładowania podczas przetwarzania żądania
- Resetowanie formularza po pomyślnej rejestracji
- Wyświetlanie komunikatu sukcesu po pomyślnej rejestracji

**Props:**
- `onWorkerRegistered?: (worker: Worker) => void` - opcjonalna funkcja callback wywoływana po pomyślnej rejestracji

**State:**
```typescript
interface WorkerRegisterFormState {
  login: string;
  password: string;
  confirmPassword: string;
  selectedCategories: string[]; // ID wybranych kategorii
  isManager: boolean;
  isLoading: boolean;
  errors: FormErrors;
  apiError: string | null;
  isSuccess: boolean;
  registeredWorker: Worker | null;
}
```

**Interfejsy:**
```typescript
interface WorkerRegisterData {
  login: string;
  password: string;
  categoryIds: string[];
  isManager: boolean;
}

interface Worker {
  id: string;
  login: string;
  isManager: boolean;
  createdAt: string;
}
```

### LoginInput (Pole wprowadzania loginu)
Komponent pola tekstowego do wprowadzania loginu pracownika.

**Funkcjonalność:**
- Pole tekstowe do wprowadzania loginu (input type="text")
- Walidacja formatu loginu (minimum 3 znaki, maksimum 255 znaków)
- Walidacja unikalności loginu (sprawdzenie po stronie serwera)
- Wyświetlanie komunikatów błędów walidacji
- Placeholder z przykładowym loginem
- Automatyczne fokusowanie przy załadowaniu komponentu

**Props:**
- `login: string` - wartość loginu
- `onChange: (login: string) => void` - funkcja wywoływana przy zmianie loginu
- `error?: string` - opcjonalny komunikat błędu walidacji
- `isDisabled?: boolean` - czy pole powinno być wyłączone (np. podczas wysyłania formularza)
- `autoFocus?: boolean` - czy pole powinno automatycznie otrzymać fokus

### PasswordInput (Pole wprowadzania hasła)
Komponent pola tekstowego do wprowadzania hasła pracownika.

**Funkcjonalność:**
- Pole tekstowe do wprowadzania hasła (input type="password")
- Przycisk/przełącznik do pokazania/ukrycia hasła (ikona oka)
- Walidacja długości hasła (minimum 8 znaków)
- Walidacja siły hasła (opcjonalnie - wymaganie małych i dużych liter, cyfr, znaków specjalnych)
- Wyświetlanie komunikatów błędów walidacji
- Placeholder z przykładowym tekstem

**Props:**
- `password: string` - wartość hasła
- `onChange: (password: string) => void` - funkcja wywoływana przy zmianie hasła
- `error?: string` - opcjonalny komunikat błędu walidacji
- `isDisabled?: boolean` - czy pole powinno być wyłączone
- `showPasswordToggle?: boolean` - czy wyświetlać przycisk do pokazania/ukrycia hasła (domyślnie true)

### ConfirmPasswordInput (Pole potwierdzenia hasła)
Komponent pola tekstowego do potwierdzenia hasła pracownika.

**Funkcjonalność:**
- Pole tekstowe do wprowadzania potwierdzenia hasła (input type="password")
- Przycisk/przełącznik do pokazania/ukrycia hasła (ikona oka)
- Walidacja zgodności z hasłem (hasło i potwierdzenie muszą być identyczne)
- Wyświetlanie komunikatów błędów walidacji
- Placeholder z przykładowym tekstem

**Props:**
- `password: string` - wartość hasła (do porównania)
- `confirmPassword: string` - wartość potwierdzenia hasła
- `onChange: (confirmPassword: string) => void` - funkcja wywoływana przy zmianie potwierdzenia hasła
- `error?: string` - opcjonalny komunikat błędu walidacji
- `isDisabled?: boolean` - czy pole powinno być wyłączone
- `showPasswordToggle?: boolean` - czy wyświetlać przycisk do pokazania/ukrycia hasła

### CategoryCheckboxList (Lista checkboxów kategorii)
Komponent wyświetlający listę kategorii ticketów z checkboxami do wyboru uprawnień.

**Funkcjonalność:**
- Pobieranie listy dostępnych kategorii z API backendu
- Wyświetlanie listy kategorii z checkboxami
- Wybór wielu kategorii (checkboxy)
- Wyświetlanie opisu kategorii (jeśli dostępny)
- Wyświetlanie domyślnego czasu rozwiązania ticketa per kategoria (opcjonalnie)
- Możliwość zaznaczenia wszystkich kategorii (opcjonalnie - przycisk "Zaznacz wszystkie")
- Wyświetlanie stanu ładowania podczas pobierania kategorii
- Obsługa błędów przy pobieraniu kategorii
- Filtrowanie kategorii (opcjonalnie - pole wyszukiwania)

**Props:**
- `selectedCategoryIds: string[]` - lista ID wybranych kategorii
- `onChange: (categoryIds: string[]) => void` - funkcja wywoływana przy zmianie wyboru kategorii
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

### ManagerCheckbox (Checkbox roli managera)
Komponent checkboxa do oznaczenia pracownika jako managera.

**Funkcjonalność:**
- Checkbox do oznaczenia, czy pracownik ma być managerem
- Wyświetlanie opisu roli managera (np. "Pracownik z uprawnieniami managera")
- Wyświetlanie ostrzeżenia o uprawnieniach managera (opcjonalnie)

**Props:**
- `isManager: boolean` - czy pracownik ma być managerem
- `onChange: (isManager: boolean) => void` - funkcja wywoływana przy zmianie
- `disabled?: boolean` - czy checkbox powinien być wyłączony

### RegisterButton (Przycisk rejestracji)
Komponent przycisku do wysłania formularza rejestracji.

**Funkcjonalność:**
- Wyświetlanie stanu ładowania podczas przetwarzania żądania
- Wyświetlanie tekstu przycisku (np. "Zarejestruj pracownika", "Rejestracja...")
- Dezaktywacja przycisku podczas przetwarzania lub gdy formularz jest nieprawidłowy
- Obsługa kliknięcia i wywołanie funkcji onSubmit z formularza
- Wizualne wyróżnienie przycisku jako głównej akcji

**Props:**
- `isLoading: boolean` - czy formularz jest w trakcie przetwarzania
- `isDisabled?: boolean` - czy przycisk powinien być wyłączony
- `onClick: () => void` - funkcja wywoływana przy kliknięciu

### ErrorDisplay (Wyświetlanie błędów)
Komponent do wyświetlania błędów walidacji i błędów z API.

**Funkcjonalność:**
- Wyświetlanie błędów walidacji pól formularza
- Wyświetlanie błędów z API (np. login już istnieje, błąd sieci, błąd serwera)
- Wyświetlanie komunikatów błędów w czytelny sposób (np. czerwony alert)
- Możliwość zamknięcia/ukrycia błędów
- Różne style dla różnych typów błędów

**Props:**
- `errors: FormErrors` - obiekt z błędami do wyświetlenia
- `apiError?: string` - opcjonalny błąd z API

**Interfejs błędów:**
```typescript
interface FormErrors {
  login?: string;
  password?: string;
  confirmPassword?: string;
  categories?: string;
  general?: string; // ogólny błąd formularza
}
```

### SuccessMessage (Komunikat sukcesu)
Komponent wyświetlający komunikat sukcesu po pomyślnej rejestracji pracownika.

**Funkcjonalność:**
- Wyświetlanie komunikatu sukcesu (np. "Pracownik został pomyślnie zarejestrowany")
- Wyświetlanie informacji o zarejestrowanym pracowniku (login)
- Przycisk do rejestracji kolejnego pracownika (resetowanie formularza)

**Props:**
- `worker: Worker` - dane zarejestrowanego pracownika
- `onRegisterAnother?: () => void` - opcjonalna funkcja wywoływana przy kliknięciu "Zarejestruj kolejnego pracownika"

### LoadingSpinner (Wskaźnik ładowania)
Komponent wyświetlający wskaźnik ładowania podczas przetwarzania żądań.

**Funkcjonalność:**
- Wyświetlanie animacji ładowania
- Możliwość wyświetlenia z komunikatem tekstowym (np. "Rejestracja pracownika...")

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
    {
      "id": "550e8400-e29b-41d4-a716-446655440002",
      "name": "Wsparcie techniczne",
      "description": "Kategoria dla ticketów związanych z problemami technicznymi",
      "defaultResolutionTimeMinutes": 45
    }
  ]
}
```

### POST /api/auth/register
Rejestracja nowego pracownika w systemie.

**Request body:**
```json
{
  "login": "jan.kowalski",
  "password": "haslo123",
  "categoryIds": [
    "550e8400-e29b-41d4-a716-446655440001",
    "550e8400-e29b-41d4-a716-446655440002"
  ],
  "isManager": false
}
```

**Odpowiedź (sukces):**
```json
{
  "worker": {
    "id": "4f86c38b-7e90-4a4d-b1ac-53edbe17e743",
    "login": "jan.kowalski",
    "isManager": false,
    "createdAt": "2024-01-15T10:00:00Z"
  },
  "categories": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Sprzedaż"
    },
    {
      "id": "550e8400-e29b-41d4-a716-446655440002",
      "name": "Wsparcie techniczne"
    }
  ]
}
```

**Odpowiedź (błąd - login już istnieje):**
```json
{
  "error": "Validation failed",
  "message": "Login już istnieje w systemie",
  "errors": {
    "login": "Login już istnieje w systemie"
  }
}
```

**Odpowiedź (błąd - walidacja):**
```json
{
  "error": "Validation failed",
  "errors": {
    "login": "Login jest wymagany",
    "password": "Hasło jest wymagane",
    "categoryIds": "Musisz wybrać co najmniej jedną kategorię"
  }
}
```

**Uwagi:**
- Endpoint wymaga autoryzacji (tylko manager może rejestrować pracowników)
- Backend automatycznie hashuje hasło przed zapisaniem w bazie danych
- Backend przypisuje wybrane kategorie do nowego pracownika

## Walidacja

### Walidacja loginu:
- Login jest wymagany
- Minimum 3 znaki, maksimum 255 znaków
- Tylko litery, cyfry, kropki i podkreślenia (opcjonalnie)
- Login musi być unikalny w systemie (sprawdzenie po stronie serwera)
- Walidacja po stronie klienta przed wysłaniem formularza

### Walidacja hasła:
- Hasło jest wymagane
- Minimum 8 znaków (walidacja po stronie klienta)
- Opcjonalnie: wymaganie małych i dużych liter, cyfr, znaków specjalnych
- Walidacja po stronie klienta przed wysłaniem formularza

### Walidacja potwierdzenia hasła:
- Potwierdzenie hasła jest wymagane
- Hasło i potwierdzenie hasła muszą być identyczne
- Walidacja po stronie klienta przed wysłaniem formularza

### Walidacja kategorii:
- Co najmniej jedna kategoria musi być wybrana
- Wszystkie wybrane kategorie muszą istnieć w systemie

### Walidacja po stronie serwera:
- Backend weryfikuje wszystkie dane przed utworzeniem konta
- Backend sprawdza unikalność loginu
- Backend weryfikuje poprawność formatu hasła
- Backend zwraca odpowiedni komunikat błędu w przypadku nieprawidłowych danych

## Zarządzanie stanem formularza

Formularz zarządza następującymi stanami:

1. **Stan formularza** - wartości pól formularza (login, hasło, potwierdzenie hasła, wybrane kategorie, rola managera)
2. **Stan ładowania** - informacja o trwających żądaniach API (pobieranie kategorii, rejestracja pracownika)
3. **Błędy walidacji** - komunikaty błędów walidacji pól formularza
4. **Błędy API** - komunikaty błędów z serwera
5. **Komunikat sukcesu** - informacja o pomyślnej rejestracji
6. **Zarejestrowany pracownik** - dane zarejestrowanego pracownika (po pomyślnej rejestracji)

## Integracja z layoutem strony

Moduł jest komponentem formularza, który jest używany przez komponent strony/layoutu. Komponent strony jest odpowiedzialny za:
- Wyświetlanie tytułu strony
- Obsługę nawigacji (przycisk powrotu, breadcrumbs)
- Sprawdzanie autoryzacji (route guard)
- Layout strony (header, footer, sidebar jeśli istnieją)
- Routing i nawigację po rejestracji



## Uwagi implementacyjne

1. **Autoryzacja:**
   - Wszystkie żądania do API powinny zawierać token autoryzacji managera
   - Sprawdzanie autoryzacji i przekierowania są obsługiwane przez komponent strony/layoutu, nie przez moduł formularza

2. **Bezpieczeństwo:**
   - Wszystkie dane są wysyłane przez HTTPS
   - Hasła nie są przechowywane w localStorage ani w żadnej innej formie po stronie klienta
   - Hasła są hashowane po stronie serwera przed zapisaniem
   - Walidacja po stronie klienta nie zastępuje walidacji po stronie serwera
   - Komunikaty błędów nie powinny ujawniać, czy login istnieje w systemie (dla bezpieczeństwa, lepiej pokazać ogólny komunikat)

3. **UX:**
   - Formularz powinien być intuicyjny i łatwy w użyciu
   - Pola powinny być wyraźnie oznaczone
   - Komunikaty błędów powinny być pomocne i zrozumiałe
   - Formularz powinien być responsywny (działać na urządzeniach mobilnych)
   - Automatyczne fokusowanie na pole loginu przy załadowaniu komponentu
   - Możliwość wysłania formularza przez Enter
   - Wyświetlanie wskaźnika ładowania podczas rejestracji
   - Wyświetlanie komunikatu sukcesu po pomyślnej rejestracji
   - Resetowanie formularza po pomyślnej rejestracji (jeśli użytkownik wybierze opcję rejestracji kolejnego pracownika)

4. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy walidacji powinny być wyświetlane przy odpowiednich polach
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby
   - Błąd "login już istnieje" powinien być wyświetlany przy polu loginu

5. **Performance:**
   - Lista kategorii może być cache'owana w pamięci komponentu
   - Formularz powinien być zoptymalizowany pod kątem szybkości działania
   - Lazy loading komponentów, jeśli moduł jest duży
   - Minimalizacja liczby re-renderów

6. **Integracja z routingiem:**
   - Moduł jest komponentem formularza, który jest używany przez komponent strony
   - Routing i ochrona przed nieautoryzowanym dostępem są obsługiwane przez komponent strony/layoutu
   - Moduł może być użyty w różnych kontekstach (strona rejestracji, modal, itp.)
   - Po pomyślnej rejestracji moduł wywołuje callback `onWorkerRegistered`, a nawigacja jest obsługiwana przez komponent strony

7. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API)
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu rejestracji
   - Testy walidacji formularza
   - Testy dla obsługi błędów API

8. **Dostępność (a11y):**
   - Formularz powinien być dostępny dla użytkowników korzystających z czytników ekranu
   - Właściwe etykiety dla pól formularza
   - Obsługa nawigacji klawiaturą
   - Komunikaty błędów powinny być powiązane z odpowiednimi polami
   - Formularz powinien mieć właściwą strukturę semantyczną (form, fieldset, legend)
   - Formularz powinien być dostępny pod kątem dostępności (ARIA atrybuty)

9. **Resetowanie formularza:**
    - Formularz powinien być resetowany po pomyślnej rejestracji (jeśli użytkownik wybierze opcję rejestracji kolejnego pracownika)
    - Wszystkie pola powinny być wyczyszczone
    - Wszystkie błędy powinny być wyczyszczone
    - Stan sukcesu powinien być zresetowany

