# Strona ustawiania dostępności pracownika

## Opis strony

Strona ustawiania dostępności jest stroną dostępną dla zalogowanych pracowników w systemie Call Center. Strona wykorzystuje moduł `worker-availability`, który umożliwia pracownikowi deklarowanie swojej dostępności w systemie.

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`) i jest dostępna pod ścieżką `/worker/availability`. Strona jest dostępna dla wszystkich zalogowanych pracowników.

## Funkcjonalność

Strona renderuje moduł `worker-availability`. Szczegółowy opis funkcjonalności modułu znajduje się w [dokumentacji modułu worker-availability](../../modules/worker/worker-availability/readme.md).

## Struktura strony

Strona składa się z głównego komponentu, który renderuje moduł `worker-availability` w kontekście layoutu pracowniczego.

### AvailabilityPage (Główny komponent strony)
Główny komponent strony, który renderuje moduł `worker-availability`.

**Funkcjonalność:**
- Renderowanie modułu `worker-availability`
- Integracja z layoutem pracowniczym
- Obsługa routingu i nawigacji

**Props:**
- Komponent nie przyjmuje żadnych props (dane pracownika są pobierane z kontekstu autentykacji)

## Integracja z modułem worker-availability

Strona integruje moduł `worker-availability` w następujący sposób:

1. **Import modułu:**
   ```typescript
   import { WorkerAvailability } from '../../modules/worker/worker-availability';
   ```

2. **Renderowanie modułu:**
   - Moduł jest renderowany jako główna zawartość strony
   - Moduł automatycznie pobiera identyfikator zalogowanego pracownika z kontekstu autentykacji

3. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu worker-availability](../../modules/worker/worker-availability/readme.md)

## Integracja z layoutem pracowniczym

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`):

1. **Routing:**
   - Strona jest dostępna pod ścieżką `/worker/availability`
   - Layout renderuje stronę w sekcji głównej zawartości (outlet)

2. **Menu nawigacyjne:**
   - Strona jest dostępna w menu nawigacyjnym layoutu jako "Dostępność" lub "Moja dostępność"
   - Link w menu prowadzi do `/worker/availability`

3. **Szczegółowa dokumentacja layoutu:**
   - [Dokumentacja layoutu worker](../../worker/layout/readme.md)

## Routing

Strona powinna być dostępna pod następującą ścieżką:

- **Ścieżka:** `/worker/availability`
- **Warunki dostępu:** Strona jest dostępna wyłącznie dla zalogowanych pracowników
- **Przekierowania:**
  - Niezalogowani użytkownicy są automatycznie przekierowywani do strony logowania (`/`)
  - Layout pracowniczy automatycznie obsługuje przekierowania

### Przykładowa konfiguracja React Router:

```typescript
<Route path="/worker" element={<WorkerLayout />}>
  <Route path="availability" element={<AvailabilityPage />} />
</Route>
```

## Wymogi dostępności

Strona musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA). Szczegółowe wymogi dostępności są opisane w dokumentacji modułu `worker-availability`.

## Uwagi implementacyjne

1. **Autentykacja:**
   - Strona wymaga zalogowania pracownika
   - Layout pracowniczy automatycznie obsługuje sprawdzanie autentykacji
   - Identyfikator pracownika jest pobierany z kontekstu autentykacji

2. **Integracja z modułem:**
   - Strona jest prostym wrapperem dla modułu `worker-availability`
   - Wszystka logika biznesowa jest zawarta w module
   - Strona odpowiada tylko za renderowanie modułu w kontekście layoutu

3. **Responsywność:**
   - Moduł `worker-availability` jest responsywny
   - Layout pracowniczy zapewnia responsywny układ strony

4. **Testowanie:**
   - Testy jednostkowe dla komponentu strony (renderowanie modułu)
   - Testy integracyjne dla procesu ustawiania dostępności
   - Testy dostępności (nawigacja klawiaturą, czytniki ekranu)

## Przykładowa struktura plików

```
frontend/app/pages/worker/availability/
├── readme.md (ten plik)
└── availability-page.tsx (główny komponent strony)
```

## Zależności

Strona wykorzystuje następujące moduły:

1. **Moduł worker-availability:**
   - Ścieżka: `frontend/app/modules/worker/worker-availability`
   - [Dokumentacja modułu](../../modules/worker/worker-availability/readme.md)

2. **Layout worker:**
   - Ścieżka: `frontend/app/pages/worker/layout`
   - [Dokumentacja layoutu](../../worker/layout/readme.md)

3. **React Router:**
   - Do nawigacji i routingu

