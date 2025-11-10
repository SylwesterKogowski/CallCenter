# Moduł logowania się do aplikacji przez pracowników

## Opis modułu

Moduł logowania się jest komponentem Reactowym, który umożliwia pracownikom zalogowanie się do systemu Call Center. Moduł zawiera formularz logowania z polami na login i hasło oraz przycisk do wysłania żądania autentykacji.

Moduł działa bez autentykacji - jest to strona logowania, która umożliwia pracownikom uzyskanie dostępu do systemu. Po pomyślnym zalogowaniu, pracownik jest przekierowywany do strony głównej `/worker`, gdzie może korzystać z funkcjonalności dostępnych dla zalogowanych pracowników.

## Funkcjonalność

1. **Wprowadzenie danych logowania** - formularz umożliwia wprowadzenie loginu i hasła pracownika
2. **Walidacja danych** - weryfikacja poprawności wprowadzonych danych przed wysłaniem
3. **Wysyłanie żądania logowania** - wysłanie żądania do API backendu w celu weryfikacji tożsamości
4. **Obsługa sesji** - po pomyślnym zalogowaniu, backend ustawia sesję (cookie, token JWT lub inny mechanizm)
5. **Przekierowanie po zalogowaniu** - po pomyślnym zalogowaniu, moduł przekierowuje pracownika do strony `/worker`
6. **Obsługa błędów** - wyświetlanie komunikatów błędów w przypadku nieprawidłowych danych logowania lub problemów z połączeniem

## Podkomponenty

### WorkerLoginForm (Główny komponent formularza)
Główny komponent formularza logowania, który zarządza stanem formularza i koordynuje wszystkie podkomponenty.

**Funkcjonalność:**
- Zarządzanie stanem formularza (login, hasło)
- Walidacja danych przed wysłaniem
- Obsługa wysyłania formularza do API
- Obsługa błędów z API
- Przekierowanie do strony `/worker` po pomyślnym zalogowaniu
- Wyświetlanie stanu ładowania podczas przetwarzania żądania
- Zarządzanie sesją użytkownika (zapisywanie informacji o zalogowanym pracowniku)

**Props:**
- `onLoginSuccess?: (worker: Worker) => void` - opcjonalna funkcja callback wywoływana po pomyślnym zalogowaniu

**Interfejsy:**
```typescript
interface Worker {
  id: string;
  login: string;
  createdAt: string;
}
```

### LoginInput (Pole wprowadzania loginu)
Komponent pola tekstowego do wprowadzania loginu pracownika.

**Funkcjonalność:**
- Pole tekstowe do wprowadzania loginu (input type="text")
- Walidacja formatu loginu (minimum 3 znaki, maksimum 255 znaków)
- Wyświetlanie komunikatów błędów walidacji
- Placeholder z przykładowym loginem
- Automatyczne fokusowanie przy załadowaniu strony (opcjonalnie)
- Obsługa klawiatury (Enter do przejścia do następnego pola lub wysłania formularza)

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
- Przycisk/przełącznik do pokazania/ukrycia hasła (opcjonalnie, ikona oka)
- Walidacja długości hasła (minimum 8 znaków)
- Wyświetlanie komunikatów błędów walidacji
- Placeholder z przykładowym tekstem
- Obsługa klawiatury (Enter do wysłania formularza)

**Props:**
- `password: string` - wartość hasła
- `onChange: (password: string) => void` - funkcja wywoływana przy zmianie hasła
- `error?: string` - opcjonalny komunikat błędu walidacji
- `isDisabled?: boolean` - czy pole powinno być wyłączone (np. podczas wysyłania formularza)
- `showPasswordToggle?: boolean` - czy wyświetlać przycisk do pokazania/ukrycia hasła (domyślnie true)

### LoginButton (Przycisk logowania)
Komponent przycisku do wysłania formularza logowania.

**Funkcjonalność:**
- Wyświetlanie stanu ładowania podczas przetwarzania żądania
- Wyświetlanie tekstu przycisku (np. "Zaloguj się", "Logowanie...")
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
- Wyświetlanie błędów z API (np. nieprawidłowy login/hasło, błąd sieci, błąd serwera)
- Wyświetlanie komunikatów błędów w czytelny sposób (np. czerwony alert)
- Możliwość zamknięcia/ukrycia błędów
- Różne style dla różnych typów błędów

**Props:**
- `errors: LoginErrors` - obiekt z błędami do wyświetlenia
- `apiError?: string` - opcjonalny błąd z API

**Interfejs błędów:**
```typescript
interface LoginErrors {
  login?: string;
  password?: string;
  general?: string; // ogólny błąd formularza (np. "Nieprawidłowy login lub hasło")
}
```

### LoadingSpinner (Wskaźnik ładowania)
Komponent wyświetlający wskaźnik ładowania podczas przetwarzania żądań.

**Funkcjonalność:**
- Wyświetlanie animacji ładowania
- Możliwość wyświetlenia z komunikatem tekstowym (np. "Logowanie...")

**Props:**
- `message?: string` - opcjonalny komunikat do wyświetlenia podczas ładowania

### LoginCard (Karta formularza logowania)
Komponent opakowujący formularz logowania w kartę/okienko.

**Funkcjonalność:**
- Wyświetlanie formularza logowania w czytelnej karcie/okienku
- Wyświetlanie tytułu formularza (np. "Logowanie do systemu")
- Wyświetlanie logo aplikacji (opcjonalnie)
- Responsywny design (działa na urządzeniach mobilnych)
- Centrowanie formularza na stronie

**Props:**
- `children: React.ReactNode` - zawartość karty (formularz logowania)
- `title?: string` - opcjonalny tytuł formularza

## Integracja z API

Moduł komunikuje się z backendem przez następujący endpoint:

### POST /api/auth/login
Weryfikacja tożsamości pracownika i utworzenie sesji.

**Request body:**
```json
{
  "login": "jan.kowalski",
  "password": "haslo123"
}
```

**Odpowiedź (sukces):**
```json
{
  "worker": {
    "id": "4f86c38b-7e90-4a4d-b1ac-53edbe17e743",
    "login": "jan.kowalski",
    "createdAt": "2024-01-15T10:00:00Z"
  },
  "session": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expiresAt": "2024-01-15T18:00:00Z"
  }
}
```

**Odpowiedź (błąd - nieprawidłowy login/hasło):**
```json
{
  "error": "Invalid credentials",
  "message": "Nieprawidłowy login lub hasło"
}
```

**Odpowiedź (błąd - walidacja):**
```json
{
  "error": "Validation failed",
  "errors": {
    "login": "Login jest wymagany",
    "password": "Hasło jest wymagane"
  }
}
```

**Uwagi:**
- Po pomyślnym zalogowaniu, backend ustawia cookie z tokenem sesji lub zwraca token JWT, który powinien być przechowywany (np. w localStorage lub cookie)
- Wszystkie kolejne żądania do API powinny zawierać token sesji w nagłówku Authorization lub jako cookie
- Token sesji powinien być automatycznie dołączany do żądań przez interceptor HTTP (np. axios interceptor)

## Walidacja

### Walidacja loginu:
- Login jest wymagany
- Minimum 3 znaki, maksimum 255 znaków
- Tylko litery, cyfry, kropki i podkreślenia (opcjonalnie)
- Walidacja po stronie klienta przed wysłaniem formularza

### Walidacja hasła:
- Hasło jest wymagane
- Minimum 8 znaków (walidacja po stronie klienta)
- Walidacja po stronie klienta przed wysłaniem formularza

### Walidacja po stronie serwera:
- Backend weryfikuje poprawność loginu i hasła
- Backend zwraca odpowiedni komunikat błędu w przypadku nieprawidłowych danych

## Przekierowanie po zalogowaniu

Po pomyślnym zalogowaniu, moduł przekierowuje pracownika do strony `/worker` używając React Router:

```typescript
navigate('/worker');
```

Przekierowanie następuje po:
1. Pomyślnym otrzymaniu odpowiedzi z API
2. Zapisaniu informacji o sesji (token, dane pracownika)
3. Zaktualizowaniu stanu aplikacji (np. kontekst autentykacji)

## Zarządzanie sesją

Moduł powinien zarządzać sesją użytkownika:

1. **Zapisywanie tokenu sesji:**
   - Token może być przechowywany w localStorage, sessionStorage lub cookie
   - Token powinien być automatycznie dołączany do kolejnych żądań HTTP

2. **Zapisywanie danych pracownika:**
   - Informacje o zalogowanym pracowniku mogą być przechowywane w kontekście React lub state management (Redux, Zustand)
   - Dane pracownika są używane do wyświetlania informacji w innych modułach

3. **Weryfikacja sesji:**
   - Moduł może sprawdzać, czy użytkownik jest już zalogowany (np. przy załadowaniu strony)
   - Jeśli użytkownik jest już zalogowany, może być automatycznie przekierowany do `/worker`

## Uwagi implementacyjne

1. **Bezpieczeństwo:**
   - Wszystkie dane są wysyłane przez HTTPS
   - Hasła nie są przechowywane w localStorage ani w żadnej innej formie po stronie klienta
   - Token sesji powinien być przechowywany bezpiecznie (httpOnly cookie jest preferowane)
   - Walidacja po stronie klienta nie zastępuje walidacji po stronie serwera
   - Komunikaty błędów nie powinny ujawniać, czy login istnieje w systemie (dla bezpieczeństwa, lepiej pokazać ogólny komunikat "Nieprawidłowy login lub hasło")

2. **UX:**
   - Formularz powinien być intuicyjny i łatwy w użyciu
   - Pola powinny być wyraźnie oznaczone
   - Komunikaty błędów powinny być pomocne i zrozumiałe
   - Formularz powinien być responsywny (działać na urządzeniach mobilnych)
   - Automatyczne fokusowanie na pole loginu przy załadowaniu strony
   - Możliwość wysłania formularza przez Enter w polu hasła
   - Wyświetlanie wskaźnika ładowania podczas logowania
   - Wyświetlanie komunikatu sukcesu przed przekierowaniem (opcjonalnie)

3. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy walidacji powinny być wyświetlane przy odpowiednich polach
   - Błędy sieci powinny być obsługiwane z możliwością ponowienia próby
   - Ogólny komunikat błędu dla nieprawidłowego loginu/hasła (bez ujawniania, które pole jest nieprawidłowe)

4. **Performance:**
   - Formularz powinien być zoptymalizowany pod kątem szybkości działania
   - Lazy loading komponentów, jeśli moduł jest duży
   - Minimalizacja liczby re-renderów

5. **Integracja z routingiem:**
   - Moduł powinien być dostępny pod ścieżką `/login` lub `/worker-login`
   - Moduł powinien być chroniony przed dostępem dla już zalogowanych użytkowników (przekierowanie do `/worker`)
   - Po wylogowaniu, użytkownik powinien być przekierowany do strony logowania

6. **Testowanie:**
   - Moduł powinien być testowalny (mockowanie API)
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu logowania
   - Testy walidacji formularza

7. **Dostępność (a11y):**
   - Formularz powinien być dostępny dla użytkowników korzystających z czytników ekranu
   - Właściwe etykiety dla pól formularza
   - Obsługa nawigacji klawiaturą
   - Komunikaty błędów powinny być powiązane z odpowiednimi polami

