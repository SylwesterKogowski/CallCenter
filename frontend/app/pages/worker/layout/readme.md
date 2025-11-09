# Layout dla zalogowanego pracownika

## Opis layoutu

Layout dla zalogowanego pracownika jest komponentem Reactowym, który stanowi wspólny szablon dla wszystkich stron dostępnych dla zalogowanych pracowników w systemie Call Center. Layout zapewnia spójną strukturę nawigacji i interfejsu użytkownika dla wszystkich modułów pracowniczych.

Layout składa się z menu nawigacyjnego, nagłówka z przyciskiem 'odbieram telefon' oraz głównej sekcji zawartości, która jest wypełniana aktualnie wybraną stroną. Layout jest dostępny wyłącznie dla zalogowanych pracowników i automatycznie przekierowuje niezalogowanych użytkowników do strony logowania.

## Funkcjonalność

1. **Menu nawigacyjne** - menu boczne lub górne z linkami do wszystkich dostępnych modułów pracowniczych
2. **Nagłówek** - sekcja nagłówkowa zawierająca przycisk 'odbieram telefon' oraz opcjonalnie informacje o zalogowanym pracowniku
3. **Główna sekcja zawartości** - obszar renderujący aktualnie wybraną stronę (outlet dla React Router)
4. **Warunkowe wyświetlanie menu** - opcje menu dostępne tylko dla managera (np. dodawanie pracowników)
5. **Ochrona dostępu** - automatyczne przekierowanie niezalogowanych użytkowników do strony logowania
6. **Responsywny design** - layout działa poprawnie na urządzeniach mobilnych i desktopowych

## Struktura layoutu

Layout składa się z następujących sekcji:

### NavigationMenu (Menu nawigacyjne)
Menu boczne lub górne zawierające linki do wszystkich dostępnych modułów pracowniczych.

**Funkcjonalność:**
- Wyświetlanie linków do modułów:
  - Grafika pracownika (`/worker/schedule`)
  - Planowanie ticketów (`/worker/planning`)
  - Ustawianie dostępności (`/worker/availability`)
  - Monitoring (`/worker/monitoring`) - tylko dla managera
  - Dodawanie pracowników (`/worker/register`) - tylko dla managera
- Wyróżnienie aktywnej strony w menu
- Warunkowe wyświetlanie opcji menu (np. opcje managera tylko dla managerów)
- Responsywny design (menu może być zwijane na urządzeniach mobilnych)
- Możliwość wylogowania się

**Props:**
- `currentPath: string` - aktualna ścieżka URL (dla wyróżnienia aktywnej strony)
- `isManager: boolean` - czy zalogowany pracownik jest managerem
- `onLogout: () => void` - funkcja wywoływana przy wylogowaniu

**Interfejsy:**
```typescript
interface MenuItem {
  label: string;
  path: string;
  icon?: React.ReactNode;
  requiresManager?: boolean; // czy opcja jest dostępna tylko dla managera
}

interface NavigationMenuProps {
  currentPath: string;
  isManager: boolean;
  onLogout: () => void;
}
```

### Header (Nagłówek)
Sekcja nagłówkowa zawierająca przycisk 'odbieram telefon' oraz opcjonalnie informacje o zalogowanym pracowniku.

**Funkcjonalność:**
- Wyświetlanie przycisku 'odbieram telefon' z modułu `worker-phone-receive
- Wyświetlanie informacji o zalogowanym pracowniku (login, rola)
- Możliwość wylogowania się (opcjonalnie)
- Responsywny design

**Props:**
- `worker: Worker` - dane zalogowanego pracownika
- `onPhoneReceive: () => void` - funkcja wywoływana przy kliknięciu przycisku 'odbieram telefon'
- `onLogout?: () => void` - opcjonalna funkcja wylogowania

**Interfejsy:**
```typescript
interface Worker {
  id: string;
  login: string;
  isManager: boolean;
}

interface HeaderProps {
  worker: Worker;
  onPhoneReceive: () => void;
  onLogout?: () => void;
}
```

### MainContent (Główna sekcja zawartości)
Obszar renderujący aktualnie wybraną stronę poprzez React Router outlet.

**Funkcjonalność:**
- Renderowanie aktualnie wybranej strony (outlet dla React Router)
- Zarządzanie przejściami między stronami
- Wyświetlanie wskaźników ładowania podczas przejść
- Obsługa błędów routingu

**Props:**
- `children?: React.ReactNode` - opcjonalne dzieci (dla alternatywnego podejścia bez outlet)

## Podkomponenty

### WorkerLayout (Główny komponent layoutu)
Główny komponent layoutu, który zarządza strukturą i koordynuje wszystkie sekcje.

**Funkcjonalność:**
- Zarządzanie strukturą layoutu (menu, nagłówek, zawartość)
- Sprawdzanie autentykacji użytkownika
- Pobieranie danych zalogowanego pracownika
- Zarządzanie stanem otwarcia modułu 'odbieram telefon'
- Obsługa wylogowania
- Integracja z React Router (outlet)
- Warunkowe wyświetlanie opcji menu dla managera

**Props:**
- `children?: React.ReactNode` - opcjonalne dzieci (jeśli nie używamy outlet)

**State:**
```typescript
interface WorkerLayoutState {
  worker: Worker | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  isPhoneReceiveOpen: boolean;
  error: string | null;
}
```

### NavigationSidebar (Menu boczne)
Komponent menu bocznego z linkami nawigacyjnymi.

**Funkcjonalność:**
- Wyświetlanie menu bocznego z linkami do modułów
- Wyróżnienie aktywnej strony
- Warunkowe wyświetlanie opcji (np. tylko dla managera)
- Zwijanie/rozwijanie menu na urządzeniach mobilnych
- Wyświetlanie ikon przy linkach (opcjonalnie)
- Przycisk wylogowania

**Props:**
- `items: MenuItem[]` - lista elementów menu
- `currentPath: string` - aktualna ścieżka URL
- `isCollapsed?: boolean` - czy menu jest zwinięte (na mobile)
- `onItemClick: (path: string) => void` - funkcja wywoływana przy kliknięciu elementu menu
- `onLogout: () => void` - funkcja wylogowania

### NavigationTopBar (Menu górne)
Alternatywny komponent menu górnego (dla innego wariantu układu).

**Funkcjonalność:**
- Wyświetlanie menu górnego z linkami do modułów
- Wyróżnienie aktywnej strony
- Warunkowe wyświetlanie opcji (np. tylko dla managera)
- Responsywny design (menu może być zwijane na mobile)
- Wyświetlanie ikon przy linkach (opcjonalnie)

**Props:**
- `items: MenuItem[]` - lista elementów menu
- `currentPath: string` - aktualna ścieżka URL
- `onItemClick: (path: string) => void` - funkcja wywoływana przy kliknięciu elementu menu

### PhoneReceiveButton (Przycisk odbierania telefonu)
Komponent przycisku 'odbieram telefon' z modułu `worker-phone-receive`.

**Funkcjonalność:**
- Wyświetlanie przycisku 'odbieram telefon' w nagłówku
- Uruchamianie modułu 'odbieram telefon' po kliknięciu
- Wizualne wyróżnienie jako głównej akcji
- Wyświetlanie wskaźnika aktywności (np. jeśli telefon jest w trakcie)
- Możliwość wyłączenia przycisku (np. jeśli pracownik już odbiera telefon)

**Props:**
- `onClick: () => void` - funkcja wywoływana przy kliknięciu
- `isDisabled?: boolean` - czy przycisk powinien być wyłączony
- `isActive?: boolean` - czy telefon jest aktualnie odbierany

**Uwaga:** Komponent wykorzystuje moduł `worker-phone-receive`. Szczegółowa dokumentacja modułu: [Dokumentacja modułu worker-phone-receive](../../modules/worker/worker-phone-receive/readme.md)

### WorkerInfo (Informacje o pracowniku)
Komponent wyświetlający informacje o zalogowanym pracowniku w nagłówku.

**Funkcjonalność:**
- Wyświetlanie loginu pracownika
- Wyświetlanie roli (pracownik/manager)
- Opcjonalnie: avatar pracownika
- Menu rozwijane z opcjami (wylogowanie, ustawienia)

**Props:**
- `worker: Worker` - dane zalogowanego pracownika
- `onLogout?: () => void` - opcjonalna funkcja wylogowania

## Integracja z modułami

### Integracja z modułem worker-phone-receive

Layout integruje moduł `worker-phone-receive` w następujący sposób:

1. **Import modułu:**
   ```typescript
   import { PhoneReceiveButton } from '../../modules/worker/worker-phone-receive';
   ```

2. **Renderowanie przycisku:**
   - Przycisk jest renderowany w sekcji nagłówkowej
   - Przycisk uruchamia okienko modalne z obsługą odbierania telefonu

3. **Obsługa otwarcia modułu:**
   ```typescript
   const handlePhoneReceive = () => {
     setPhoneReceiveOpen(true);
   };
   ```

4. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu worker-phone-receive](../../modules/worker/worker-phone-receive/readme.md)

### Integracja z modułem worker-schedule

Layout renderuje moduł `worker-schedule` pod ścieżką `/worker/schedule`:

1. **Routing:**
   - Moduł jest dostępny pod ścieżką `/worker/schedule`
   - Layout renderuje moduł w sekcji głównej zawartości (outlet)

2. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu worker-schedule](../../modules/worker/worker-schedule/readme.md)

### Integracja z modułem ticket-planning

Layout renderuje moduł `ticket-planning` pod ścieżką `/worker/planning`:

1. **Routing:**
   - Moduł jest dostępny pod ścieżką `/worker/planning`
   - Layout renderuje moduł w sekcji głównej zawartości (outlet)

2. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu ticket-planning](../../modules/worker/ticket-planning/readme.md)

### Integracja z modułem worker-availability

Layout renderuje moduł `worker-availability` pod ścieżką `/worker/availability`:

1. **Routing:**
   - Moduł jest dostępny pod ścieżką `/worker/availability`
   - Layout renderuje moduł w sekcji głównej zawartości (outlet)

2. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu worker-availability](../../modules/worker/worker-availability/readme.md)

### Integracja z modułem manager-monitoring

Layout renderuje moduł `manager-monitoring` pod ścieżką `/worker/monitoring` (tylko dla managera):

1. **Routing:**
   - Moduł jest dostępny pod ścieżką `/worker/monitoring`
   - Layout renderuje moduł w sekcji głównej zawartości (outlet)
   - Dostęp jest ograniczony tylko dla pracowników z rolą managera

2. **Warunkowe wyświetlanie w menu:**
   - Opcja menu "Monitoring" jest wyświetlana tylko dla managerów
   - Layout sprawdza rolę pracownika przed wyświetleniem opcji menu

3. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu manager-monitoring](../../modules/manager/manager-monitoring/readme.md)

### Integracja z modułem worker-register

Layout renderuje moduł `worker-register` pod ścieżką `/worker/register` (tylko dla managera):

1. **Routing:**
   - Moduł jest dostępny pod ścieżką `/worker/register`
   - Layout renderuje moduł w sekcji głównej zawartości (outlet)
   - Dostęp jest ograniczony tylko dla pracowników z rolą managera

2. **Warunkowe wyświetlanie w menu:**
   - Opcja menu "Dodawanie pracowników" jest wyświetlana tylko dla managerów
   - Layout sprawdza rolę pracownika przed wyświetleniem opcji menu

3. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu worker-register](../../modules/manager/worker-register/readme.md)

## Routing

Layout jest używany jako wrapper dla wszystkich stron pracowniczych:

### Struktura routingu:

```
/worker
  ├── /schedule (grafika pracownika)
  ├── /planning (planowanie ticketów)
  ├── /availability (ustawianie dostępności)
  └── /register (dodawanie pracowników - tylko dla managera)
```

### Warunki dostępu:

- **Wymagana autentykacja:** Wszystkie strony pod layoutem wymagają zalogowania
- **Przekierowanie:** Niezalogowani użytkownicy są automatycznie przekierowywani do `/` (strona logowania)
- **Ograniczenia roli:** Niektóre strony (np. `/worker/monitoring`, `/worker/register`) są dostępne tylko dla managerów


### Przykładowa konfiguracja React Router:

```typescript
<Route path="/worker" element={<WorkerLayout />}>
  <Route index element={<Navigate to="/worker/schedule" replace />} />
  <Route path="schedule" element={<WorkerSchedule />} />
  <Route path="planning" element={<TicketPlanning />} />
  <Route path="availability" element={<WorkerAvailability />} />
  <Route
    path="monitoring" 
    element={
      <RequireManager>
        <ManagerMonitoring />
      </RequireManager>
    } 
  />
  <Route 
    path="register" 
    element={
      <RequireManager>
        <WorkerRegister />
      </RequireManager>
    } 
  />
</Route>
```

## Menu nawigacyjne

### Elementy menu:

1. **Grafika** (`/worker/schedule`)
   - Etykieta: "Grafik" lub "Mój grafik"
   - Ikona: kalendarz (opcjonalnie)
   - Dostępność: dla wszystkich pracowników

2. **Planowanie** (`/worker/planning`)
   - Etykieta: "Planowanie" lub "Planowanie ticketów"
   - Ikona: lista zadań (opcjonalnie)
   - Dostępność: dla wszystkich pracowników

3. **Dostępność** (`/worker/availability`)
   - Etykieta: "Dostępność" lub "Moja dostępność"
   - Ikona: zegar (opcjonalnie)
   - Dostępność: dla wszystkich pracowników

4. **Monitoring** (`/worker/monitoring`)
   - Etykieta: "Monitoring" lub "Monitorowanie systemu"
   - Ikona: wykres/dashboard (opcjonalnie)
   - Dostępność: tylko dla managerów (`requiresManager: true`)

5. **Dodawanie pracowników** (`/worker/register`)
   - Etykieta: "Dodaj pracownika" lub "Rejestracja pracownika"
   - Ikona: użytkownik plus (opcjonalnie)
   - Dostępność: tylko dla managerów (`requiresManager: true`)

### Wyróżnienie aktywnej strony:

- Aktywna strona w menu powinna być wyróżniona wizualnie (np. inny kolor tła, podkreślenie, ikona)
- Można użyć `aria-current="page"` dla dostępności

## Nagłówek

### Przycisk 'odbieram telefon':

- **Pozycja:** W nagłówku, wyróżniony jako główna akcja
- **Wygląd:** Duży, widoczny przycisk (np. czerwony, z ikoną telefonu)
- **Funkcjonalność:** Uruchamia moduł `worker-phone-receive` (okienko modalne)
- **Dostępność:** Zawsze widoczny dla zalogowanych pracowników
- **Status:** Może wyświetlać wskaźnik aktywności, jeśli telefon jest w trakcie

### Informacje o pracowniku:

- **Login:** Wyświetlanie loginu zalogowanego pracownika
- **Rola:** Opcjonalnie wyświetlanie roli (pracownik/manager)
- **Menu rozwijane:** Opcjonalnie menu z opcjami (wylogowanie, ustawienia)

## Wymogi dostępności

Layout musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA):

1. **Semantyczne HTML:**
   - Użycie semantycznych elementów HTML (`<nav>`, `<header>`, `<main>`, `<aside>`)
   - Właściwa struktura nagłówków
   - Logiczna struktura treści

2. **Nawigacja klawiaturą:**
   - Wszystkie elementy interaktywne muszą być dostępne za pomocą klawiatury (Tab, Enter, Space)
   - Logiczna kolejność tabulacji
   - Widoczny wskaźnik fokusa dla wszystkich elementów interaktywnych (min. 2px obramowanie)
   - Możliwość zamknięcia menu rozwijanego klawiszem Escape

3. **ARIA atrybuty:**
   - `aria-label` lub `aria-labelledby` dla sekcji nawigacji
   - `role="navigation"` dla menu nawigacyjnego
   - `aria-current="page"` dla aktywnej strony w menu
   - `aria-expanded` dla zwijanych sekcji menu (na mobile)
   - `aria-live="polite"` dla dynamicznych komunikatów

4. **Kontrast kolorów:**
   - Minimalny kontrast tekstu do tła: 4.5:1 dla zwykłego tekstu, 3:1 dla dużego tekstu
   - Informacje nie mogą być przekazywane wyłącznie przez kolor

5. **Responsywność:**
   - Layout powinien działać poprawnie na urządzeniach mobilnych
   - Minimalny rozmiar obszarów klikalnych: 44x44px
   - Menu powinno być dostępne na mobile (np. hamburger menu)

6. **Struktura treści:**
   - Logiczna struktura nagłówków (h1 → h2 → h3)
   - Każda sekcja powinna mieć odpowiedni nagłówek
   - Regiony ARIA dla głównych sekcji (`role="region"` z `aria-label`)

## Uwagi implementacyjne

1. **Wybór układu menu:**
   - Menu boczne jest dobry dla desktop, ale wymaga adaptacji na mobile (hamburger menu)
   - Menu górne jest uniwersalne i działa dobrze na wszystkich urządzeniach
   - Wybór zależy od wymagań UX i projektu

2. **Responsywność:**
   - Layout musi być w pełni responsywny
   - Na urządzeniach mobilnych menu może być zwijane (hamburger menu)
   - Nagłówek powinien być zawsze widoczny
   - Główna sekcja zawartości powinna zajmować dostępną przestrzeń

3. **Performance:**
   - Moduły mogą być lazy loaded, jeśli są duże
   - Minimalizacja liczby re-renderów
   - Optymalizacja renderowania menu (tylko widoczne elementy)

4. **UX:**
   - Layout powinien być intuicyjny i łatwy w użyciu
   - Przycisk 'odbieram telefon' powinien być wyraźnie widoczny
   - Menu powinno być łatwo dostępne
   - Wyróżnienie aktywnej strony w menu
   - Płynne przejścia między stronami

5. **Bezpieczeństwo:**
   - Wszystkie strony pod layoutem wymagają autentykacji
   - Sprawdzanie autentykacji przy każdym renderowaniu
   - Automatyczne przekierowanie niezalogowanych użytkowników
   - Sprawdzanie roli dla stron wymagających uprawnień managera

6. **Integracja z routingiem:**
   - Layout powinien być używany jako wrapper dla wszystkich stron pracowniczych
   - Użycie React Router outlet do renderowania aktualnej strony
   - Obsługa błędów routingu (404, brak uprawnień)

7. **Testowanie:**
   - Testy jednostkowe dla logiki komponentów
   - Testy integracyjne dla procesu nawigacji
   - Testy responsywności (różne rozmiary ekranów)
   - Testy dostępności (nawigacja klawiaturą, czytniki ekranu)
   - Testy autentykacji (przekierowanie niezalogowanych użytkowników)

8. **Zarządzanie stanem:**
   - Stan autentykacji powinien być zarządzany centralnie (np. Context API, Redux)
   - Stan otwarcia modułu 'odbieram telefon' może być lokalny lub globalny
   - Synchronizacja stanu między komponentami

9. **Obsługa błędów:**
   - Wszystkie błędy z API powinny być wyświetlane w czytelny sposób
   - Błędy autentykacji powinny powodować przekierowanie do strony logowania
   - Błędy routingu powinny być obsługiwane (404, brak uprawnień)

10. **Integracja z modułem 'odbieram telefon':**
    - Moduł może być renderowany jako modal/dialog (bez zmiany URL)
    - Lub jako osobna strona pod ścieżką `/worker/phone-receive`
    - Layout powinien zarządzać stanem otwarcia modułu

## Przykładowa struktura plików

```
frontend/app/pages/worker/layout/
├── readme.md (ten plik)
├── worker-layout.tsx (główny komponent layoutu)
├── components/
│   ├── navigation-sidebar.tsx
│   ├── navigation-top-bar.tsx
│   ├── header.tsx
│   ├── phone-receive-button.tsx
│   └── worker-info.tsx
└── styles/
    └── worker-layout.css (opcjonalnie, jeśli używamy CSS zamiast styled-components)
```

## Zależności

Layout wykorzystuje następujące moduły:

1. **Moduł worker-phone-receive:**
   - Ścieżka: `frontend/app/modules/worker/worker-phone-receive`
   - [Dokumentacja modułu](../../modules/worker/worker-phone-receive/readme.md)

2. **Moduł worker-schedule:**
   - Ścieżka: `frontend/app/modules/worker/worker-schedule`
   - [Dokumentacja modułu](../../modules/worker/worker-schedule/readme.md)

3. **Moduł ticket-planning:**
   - Ścieżka: `frontend/app/modules/worker/ticket-planning`
   - [Dokumentacja modułu](../../modules/worker/ticket-planning/readme.md)

4. **Moduł worker-availability:**
   - Ścieżka: `frontend/app/modules/worker/worker-availability`
   - [Dokumentacja modułu](../../modules/worker/worker-availability/readme.md)

5. **Moduł manager-monitoring:**
   - Ścieżka: `frontend/app/modules/manager/manager-monitoring`
   - [Dokumentacja modułu](../../modules/manager/manager-monitoring/readme.md)

6. **Moduł worker-register:**
   - Ścieżka: `frontend/app/modules/manager/worker-register`
   - [Dokumentacja modułu](../../modules/manager/worker-register/readme.md)

7. **React Router:**
   - Do nawigacji między stronami
   - Do renderowania aktualnej strony (outlet)

8. **Biblioteki UI (opcjonalnie):**
   - Do komponentów UI (menu, przyciski, modale)
   - Do responsywnego układu (np. CSS Grid, Flexbox, lub biblioteka UI)
