# Strona grafika pracownika

## Opis strony

Strona grafika pracownika jest głównym centrum pracy dla zalogowanych pracowników w systemie Call Center. Strona wykorzystuje moduł `worker-schedule`, który umożliwia pracownikowi zarządzanie ticketami przypisanymi do niego oraz wybór aktywnego ticketa, nad którym aktualnie pracuje.

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`) i jest dostępna pod ścieżką `/worker` lub `/worker/schedule`. Strona jest dostępna dla wszystkich zalogowanych pracowników i jest pierwszą stroną, którą zobaczy pracownik po zalogowaniu.

## Funkcjonalność

Strona renderuje moduł `worker-schedule`. Szczegółowy opis funkcjonalności modułu znajduje się w [dokumentacji modułu worker-schedule](../../modules/worker/worker-schedule/readme.md).

## Struktura strony

Strona składa się z głównego komponentu, który renderuje moduł `worker-schedule` w kontekście layoutu pracowniczego.

### SchedulePage (Główny komponent strony)
Główny komponent strony, który renderuje moduł `worker-schedule`.

**Funkcjonalność:**
- Renderowanie modułu `worker-schedule`
- Integracja z layoutem pracowniczym
- Obsługa routingu i nawigacji

**Props:**
- Komponent nie przyjmuje żadnych props (dane pracownika są pobierane z kontekstu autentykacji)

## Integracja z modułem worker-schedule

Strona integruje moduł `worker-schedule` w następujący sposób:

1. **Import modułu:**
   ```typescript
   import { WorkerSchedule } from '../../modules/worker/worker-schedule';
   ```

2. **Renderowanie modułu:**
   - Moduł jest renderowany jako główna zawartość strony
   - Moduł automatycznie pobiera identyfikator zalogowanego pracownika z kontekstu autentykacji

3. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu worker-schedule](../../modules/worker/worker-schedule/readme.md)

## Integracja z layoutem pracowniczym

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`):

1. **Routing:**
   - Strona jest dostępna pod ścieżką `/worker` (strona główna) lub `/worker/schedule`
   - Layout renderuje stronę w sekcji głównej zawartości (outlet)

2. **Menu nawigacyjne:**
   - Strona jest dostępna w menu nawigacyjnym layoutu jako "Grafik" lub "Mój grafik"
   - Link w menu prowadzi do `/worker` lub `/worker/schedule`

3. **Szczegółowa dokumentacja layoutu:**
   - [Dokumentacja layoutu worker](../../worker/layout/readme.md)

## Routing

Strona powinna być dostępna pod następującymi ścieżkami:

- **Ścieżka główna:** `/worker` (strona domyślna po zalogowaniu)
- **Ścieżka alternatywna:** `/worker/schedule`
- **Warunki dostępu:** Strona jest dostępna wyłącznie dla zalogowanych pracowników
- **Przekierowania:**
  - Niezalogowani użytkownicy są automatycznie przekierowywani do strony logowania (`/`)
  - Layout pracowniczy automatycznie obsługuje przekierowania

### Przykładowa konfiguracja React Router:

```typescript
<Route path="/worker" element={<WorkerLayout />}>
  <Route index element={<SchedulePage />} />
  <Route path="schedule" element={<SchedulePage />} />
</Route>
```

## Wymogi dostępności

Strona musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA). Szczegółowe wymogi dostępności są opisane w dokumentacji modułu `worker-schedule`.

## Uwagi implementacyjne

1. **Autentykacja:**
   - Strona wymaga zalogowania pracownika
   - Layout pracowniczy automatycznie obsługuje sprawdzanie autentykacji
   - Identyfikator pracownika jest pobierany z kontekstu autentykacji

2. **Integracja z modułem:**
   - Strona jest prostym wrapperem dla modułu `worker-schedule`
   - Wszystka logika biznesowa jest zawarta w module
   - Strona odpowiada tylko za renderowanie modułu w kontekście layoutu

3. **Responsywność:**
   - Moduł `worker-schedule` jest responsywny
   - Layout pracowniczy zapewnia responsywny układ strony

4. **Synchronizacja w czasie rzeczywistym:**
   - Moduł wykorzystuje Server-Sent Events (SSE) do odbierania zmian w planingu
   - Strona powinna zapewniać stabilne połączenie SSE przez cały czas działania

5. **Testowanie:**
   - Testy jednostkowe dla komponentu strony (renderowanie modułu)
   - Testy integracyjne dla procesu zarządzania grafikiem
   - Testy dostępności (nawigacja klawiaturą, czytniki ekranu)

## Przykładowa struktura plików

```
frontend/app/pages/worker/schedule/
├── readme.md (ten plik)
└── schedule-page.tsx (główny komponent strony)
```

## Zależności

Strona wykorzystuje następujące moduły:

1. **Moduł worker-schedule:**
   - Ścieżka: `frontend/app/modules/worker/worker-schedule`
   - [Dokumentacja modułu](../../modules/worker/worker-schedule/readme.md)

2. **Layout worker:**
   - Ścieżka: `frontend/app/pages/worker/layout`
   - [Dokumentacja layoutu](../../worker/layout/readme.md)

3. **React Router:**
   - Do nawigacji i routingu

