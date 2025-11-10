# Strona początkowa dla gości

## Opis strony

Strona początkowa (landing page) jest główną stroną aplikacji Call Center, dostępną dla niezalogowanych użytkowników (gości). Strona łączy w sobie dwa główne moduły: moduł utworzenia nowego ticketa oraz moduł logowania się do systemu.

Strona jest przeznaczona dla dwóch grup użytkowników:
1. **Klienci** - mogą utworzyć nowy ticket bez konieczności logowania się
2. **Pracownicy** - mogą zalogować się do systemu, aby uzyskać dostęp do funkcjonalności dostępnych dla zalogowanych pracowników

Strona działa bez autentykacji - jest dostępna dla wszystkich użytkowników bez konieczności logowania się.

## Funkcjonalność

1. **Utworzenie nowego ticketa** - klienci mogą utworzyć nowy ticket w systemie Call Center poprzez moduł `ticket-add`
2. **Logowanie się do systemu** - pracownicy mogą zalogować się do systemu poprzez moduł `worker-login`
3. **Nawigacja między sekcjami** - przełączanie się między sekcją tworzenia ticketa a sekcją logowania
4. **Responsywny design** - strona działa poprawnie na urządzeniach mobilnych i desktopowych
5. **Wyświetlanie informacji o systemie** - opcjonalnie może zawierać informacje o firmie, kontaktach, itp.

## Struktura strony

Strona składa się z następujących sekcji:

### Header (Nagłówek)
Sekcja nagłówkowa strony zawierająca:
- Logo aplikacji/firmy
- Nazwa aplikacji/systemu
- Opcjonalnie: linki do dodatkowych informacji (o firmie, kontakt, pomoc)

### Main Content (Główna zawartość)
Główna sekcja strony zawierająca dwa moduły:

#### Sekcja utworzenia ticketa
Zawiera moduł `ticket-add`, który umożliwia klientom utworzenie nowego ticketa.

**Funkcjonalność:**
- Formularz do wprowadzenia danych klienta (email, telefon, imię, nazwisko - wszystkie pola opcjonalne)
- Wybór kategorii ticketa (wymagane)
- Wprowadzenie tytułu i opisu problemu (opcjonalne)
- Walidacja danych przed wysłaniem
- Utworzenie ticketa i przekierowanie do modułu `ticket-chat`

**Wizualizacja:**
- Może być wyświetlona jako karta/okienko na stronie
- Może być wyróżniona jako główna akcja (np. większy rozmiar, bardziej widoczna)
- Tytuł sekcji: np. "Zgłoś problem" lub "Utwórz zgłoszenie"

#### Sekcja logowania
Zawiera moduł `worker-login`, który umożliwia pracownikom zalogowanie się do systemu.

**Funkcjonalność:**
- Formularz logowania z polami na login i hasło
- Walidacja danych przed wysłaniem
- Weryfikacja tożsamości i utworzenie sesji
- Przekierowanie do strony `/worker` po pomyślnym zalogowaniu
- Obsługa błędów logowania

**Wizualizacja:**
- Może być wyświetlona jako karta/okienko na stronie
- Może być mniej wyróżniona niż sekcja tworzenia ticketa (jako akcja pomocnicza)
- Tytuł sekcji: np. "Logowanie dla pracowników" lub "Panel pracownika"

### Footer (Stopka)
Opcjonalna sekcja stopkowa zawierająca:
- Informacje o firmie
- Linki do dodatkowych zasobów
- Informacje prawne (polityka prywatności, regulamin)
- Informacje kontaktowe

## Układ strony

Strona może być zorganizowana w jeden z następujących sposobów:

### Wariant 1: Układ dwukolumnowy (desktop)
- Lewa kolumna: Sekcja utworzenia ticketa (większa, bardziej wyróżniona)
- Prawa kolumna: Sekcja logowania (mniejsza)
- Na urządzeniach mobilnych: kolumny układają się jedna pod drugą

### Wariant 2: Układ z zakładkami
- Główna zakładka: "Zgłoś problem" (zawiera moduł `ticket-add`)
- Druga zakładka: "Logowanie" (zawiera moduł `worker-login`)
- Użytkownik przełącza się między zakładkami

### Wariant 3: Układ z akordeonem
- Główna sekcja rozwinięta: Sekcja utworzenia ticketa
- Druga sekcja zwinięta: Sekcja logowania (możliwość rozwinięcia)
- Użytkownik może rozwijać/zwijać sekcje

### Wariant 4: Układ z przyciskiem przełączającym
- Domyślnie wyświetlana sekcja utworzenia ticketa
- Przycisk "Jestem pracownikiem" przełącza widok na sekcję logowania
- Przycisk "Zgłoś problem" wraca do sekcji tworzenia ticketa

## Podkomponenty

### LandingPage (Główny komponent strony)
Główny komponent strony początkowej, który zarządza układem i koordynuje wszystkie sekcje.

**Funkcjonalność:**
- Zarządzanie układem strony (wybór wariantu układu)
- Zarządzanie stanem aktywności sekcji (która sekcja jest aktualnie widoczna)
- Integracja modułu `ticket-add` i modułu `worker-login`
- Obsługa nawigacji między sekcjami
- Responsywny design (adaptacja do różnych rozmiarów ekranów)
- Wyświetlanie nagłówka i stopki

**Props:**
- `defaultSection?: 'ticket' | 'login'` - opcjonalna domyślna sekcja do wyświetlenia

**State:**
```typescript
interface LandingPageState {
  activeSection: 'ticket' | 'login';
  layout: 'columns' | 'tabs' | 'accordion' | 'toggle';
}
```

### HeaderSection (Sekcja nagłówkowa)
Komponent nagłówka strony.

**Funkcjonalność:**
- Wyświetlanie logo aplikacji
- Wyświetlanie nazwy aplikacji/systemu
- Opcjonalnie: linki do dodatkowych informacji
- Responsywny design

**Props:**
- `logo?: string` - opcjonalna ścieżka do logo
- `title?: string` - opcjonalny tytuł aplikacji

### TicketSection (Sekcja tworzenia ticketa)
Komponent opakowujący moduł `ticket-add`.

**Funkcjonalność:**
- Integracja modułu `ticket-add`
- Wyświetlanie sekcji w odpowiednim układzie (karta, zakładka, akordeon)
- Obsługa przekierowania do modułu `ticket-chat` po utworzeniu ticketa
- Wyświetlanie tytułu sekcji

**Props:**
- `onTicketCreated?: (ticketId: string) => void` - opcjonalna funkcja callback wywoływana po utworzeniu ticketa

### LoginSection (Sekcja logowania)
Komponent opakowujący moduł `worker-login`.

**Funkcjonalność:**
- Integracja modułu `worker-login`
- Wyświetlanie sekcji w odpowiednim układzie (karta, zakładka, akordeon)
- Obsługa przekierowania do strony `/worker` po zalogowaniu
- Wyświetlanie tytułu sekcji

**Props:**
- `onLoginSuccess?: (worker: Worker) => void` - opcjonalna funkcja callback wywoływana po pomyślnym zalogowaniu

### NavigationTabs (Zakładki nawigacyjne)
Komponent zakładek do przełączania między sekcjami (dla wariantu z zakładkami).

**Funkcjonalność:**
- Wyświetlanie zakładek "Zgłoś problem" i "Logowanie"
- Przełączanie między zakładkami
- Wyróżnienie aktywnej zakładki
- Responsywny design

**Props:**
- `activeTab: 'ticket' | 'login'` - aktywna zakładka
- `onTabChange: (tab: 'ticket' | 'login') => void` - funkcja wywoływana przy zmianie zakładki

### ToggleButton (Przycisk przełączający)
Komponent przycisku do przełączania między sekcjami (dla wariantu z przyciskiem przełączającym).

**Funkcjonalność:**
- Wyświetlanie przycisku do przełączenia widoku
- Zmiana tekstu przycisku w zależności od aktywnej sekcji
- Wizualne wyróżnienie przycisku

**Props:**
- `activeSection: 'ticket' | 'login'` - aktywna sekcja
- `onToggle: () => void` - funkcja wywoływana przy kliknięciu

### FooterSection (Sekcja stopkowa)
Komponent stopki strony.

**Funkcjonalność:**
- Wyświetlanie informacji o firmie
- Linki do dodatkowych zasobów
- Informacje prawne
- Informacje kontaktowe
- Responsywny design

**Props:**
- `companyInfo?: CompanyInfo` - opcjonalne informacje o firmie

**Interfejs:**
```typescript
interface CompanyInfo {
  name?: string;
  address?: string;
  phone?: string;
  email?: string;
  links?: FooterLink[];
}

interface FooterLink {
  label: string;
  url: string;
}
```

## Integracja z modułami

### Integracja z modułem ticket-add

Strona integruje moduł `ticket-add` w następujący sposób:

1. **Import modułu:**
   ```typescript
   import { TicketAddForm } from '../../modules/unauthenticated/ticket-add';
   ```

2. **Renderowanie modułu:**
   - Moduł jest renderowany w sekcji `TicketSection`
   - Moduł otrzymuje callback `onTicketCreated`, który obsługuje przekierowanie do modułu `ticket-chat`

3. **Obsługa utworzenia ticketa:**
   ```typescript
   const handleTicketCreated = (ticketId: string) => {
     navigate(`/ticket-chat/${ticketId}`);
   };
   ```

4. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu ticket-add](../../modules/unauthenticated/ticket-add/readme.md)

### Integracja z modułem worker-login

Strona integruje moduł `worker-login` w następujący sposób:

1. **Import modułu:**
   ```typescript
   import { WorkerLoginForm } from '../../modules/unauthenticated/worker-login';
   ```

2. **Renderowanie modułu:**
   - Moduł jest renderowany w sekcji `LoginSection`
   - Moduł automatycznie obsługuje przekierowanie do strony `/worker` po pomyślnym zalogowaniu

3. **Obsługa logowania:**
   - Moduł sam zarządza procesem logowania i przekierowaniem
   - Strona może opcjonalnie otrzymać callback `onLoginSuccess` do dodatkowych akcji

4. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu worker-login](../../modules/unauthenticated/worker-login/readme.md)

## Routing

Strona powinna być dostępna pod główną ścieżką aplikacji:

- **Ścieżka:** `/` (root)
- **Warunki dostępu:** Strona jest dostępna dla wszystkich użytkowników (bez autentykacji)
- **Przekierowania:**
  - Jeśli użytkownik jest już zalogowany jako pracownik, może być automatycznie przekierowany do `/worker`
  - Po pomyślnym utworzeniu ticketa, użytkownik jest przekierowywany do `/ticket-chat/:ticketId`
  - Po pomyślnym zalogowaniu, pracownik jest przekierowywany do `/worker`

## Wymogi dostępności

Strona musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA):

1. **Semantyczne HTML:**
   - Użycie semantycznych elementów HTML (`<header>`, `<main>`, `<section>`, `<footer>`)
   - Właściwa struktura nagłówków (`<h1>`, `<h2>`, etc.)
   - Logiczna struktura treści

2. **Nawigacja klawiaturą:**
   - Wszystkie elementy interaktywne muszą być dostępne za pomocą klawiatury (Tab, Enter, Space)
   - Logiczna kolejność tabulacji
   - Widoczny wskaźnik fokusa dla wszystkich elementów interaktywnych (min. 2px obramowanie)

3. **ARIA atrybuty:**
   - `aria-label` lub `aria-labelledby` dla sekcji
   - `role="navigation"` dla nawigacji między sekcjami
   - `aria-current="page"` dla aktywnej sekcji/zakładki
   - `aria-live="polite"` dla dynamicznych komunikatów

4. **Kontrast kolorów:**
   - Minimalny kontrast tekstu do tła: 4.5:1 dla zwykłego tekstu, 3:1 dla dużego tekstu
   - Informacje nie mogą być przekazywane wyłącznie przez kolor

5. **Responsywność:**
   - Strona powinna działać poprawnie na urządzeniach mobilnych
   - Minimalny rozmiar obszarów klikalnych: 44x44px
   - Odpowiednie rozmiary czcionek (minimum 16px dla pól formularza na mobile)

6. **Struktura treści:**
   - Logiczna struktura nagłówków (h1 → h2 → h3)
   - Każda sekcja powinna mieć odpowiedni nagłówek
   - Regiony ARIA dla głównych sekcji (`role="region"` z `aria-label`)

## Uwagi implementacyjne

1. **Wybór układu:**
   - Układ strony powinien być wybierany na podstawie wymagań UX i projektu
   - Układ dwukolumnowy jest dobry dla desktop, ale wymaga adaptacji na mobile
   - Układ z zakładkami jest uniwersalny i działa dobrze na wszystkich urządzeniach
   - Układ z akordeonem może być użyteczny, jeśli chcemy zaoszczędzić miejsce

2. **Responsywność:**
   - Strona musi być w pełni responsywna
   - Na urządzeniach mobilnych sekcje powinny być układane jedna pod drugą
   - Rozmiary czcionek i odstępy powinny być dostosowane do rozmiaru ekranu
   - Przyciski i pola formularza powinny mieć odpowiednie rozmiary na mobile (min. 44x44px)

3. **Performance:**
   - Moduły `ticket-add` i `worker-login` mogą być lazy loaded, jeśli są duże
   - Minimalizacja liczby re-renderów
   - Optymalizacja obrazów (logo, grafiki)
   - Lazy loading obrazów poniżej folda

4. **UX:**
   - Strona powinna być intuicyjna i łatwa w użyciu
   - Sekcja tworzenia ticketa powinna być bardziej wyróżniona (jako główna akcja)
   - Sekcja logowania powinna być łatwo dostępna, ale nie dominować
   - Komunikaty błędów powinny być pomocne i zrozumiałe
   - Wyświetlanie wskaźników ładowania podczas operacji

5. **Bezpieczeństwo:**
   - Wszystkie dane są wysyłane przez HTTPS
   - Walidacja po stronie klienta nie zastępuje walidacji po stronie serwera
   - Komunikaty błędów nie powinny ujawniać wrażliwych informacji

6. **Integracja z routingiem:**
   - Strona powinna być dostępna pod ścieżką `/`
   - Strona powinna sprawdzać, czy użytkownik jest już zalogowany (opcjonalne przekierowanie do `/worker`)
   - Po utworzeniu ticketa, przekierowanie do `/ticket-chat/:ticketId`
   - Po zalogowaniu, przekierowanie do `/worker`

7. **Testowanie:**
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu tworzenia ticketa i logowania
   - Testy responsywności (różne rozmiary ekranów)
   - Testy dostępności (nawigacja klawiaturą, czytniki ekranu)

8. **SEO (opcjonalnie):**
   - Jeśli strona ma być indeksowana przez wyszukiwarki, należy dodać odpowiednie meta tagi
   - Strukturalne dane (Schema.org) dla lepszego zrozumienia treści przez wyszukiwarki
   - Semantyczne HTML dla lepszego zrozumienia treści

## Przykładowa struktura plików

```
frontend/app/pages/unauthenticated/landing-page/
├── readme.md (ten plik)
├── landing-page.tsx (główny komponent strony)
├── components/
│   ├── header-section.tsx
│   ├── ticket-section.tsx
│   ├── login-section.tsx
│   ├── footer-section.tsx
│   ├── navigation-tabs.tsx
│   └── toggle-button.tsx
└── styles/
    └── landing-page.css (opcjonalnie, jeśli używamy CSS zamiast styled-components)
```

## Zależności

Strona wykorzystuje następujące moduły:

1. **Moduł ticket-add:**
   - Ścieżka: `frontend/app/modules/unauthenticated/ticket-add`
   - [Dokumentacja modułu](../../modules/unauthenticated/ticket-add/readme.md)

2. **Moduł worker-login:**
   - Ścieżka: `frontend/app/modules/unauthenticated/worker-login`
   - [Dokumentacja modułu](../../modules/unauthenticated/worker-login/readme.md)

3. **React Router:**
   - Do nawigacji między stronami
   - Do przekierowań po utworzeniu ticketa i zalogowaniu

4. **Biblioteki UI (opcjonalnie):**
   - Do komponentów UI (karty, przyciski, formularze)
   - Do responsywnego układu (np. CSS Grid, Flexbox, lub biblioteka UI)

