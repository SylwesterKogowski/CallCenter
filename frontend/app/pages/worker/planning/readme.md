# Strona planowania ticketów

## Opis strony

Strona planowania ticketów jest stroną dostępną dla zalogowanych pracowników w systemie Call Center. Strona wykorzystuje moduł `ticket-planning`, który umożliwia pracownikowi planowanie i przypisywanie ticketów z backlogu na najbliższy tydzień.

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`) i jest dostępna pod ścieżką `/worker/planning`. Strona jest dostępna dla wszystkich zalogowanych pracowników.

## Funkcjonalność

Strona renderuje moduł `ticket-planning`. Szczegółowy opis funkcjonalności modułu znajduje się w [dokumentacji modułu ticket-planning](../../modules/worker/ticket-planning/readme.md).

## Struktura strony

Strona składa się z głównego komponentu, który renderuje moduł `ticket-planning` w kontekście layoutu pracowniczego.

### PlanningPage (Główny komponent strony)
Główny komponent strony, który renderuje moduł `ticket-planning`.

**Funkcjonalność:**
- Renderowanie modułu `ticket-planning`
- Integracja z layoutem pracowniczym
- Obsługa routingu i nawigacji

**Props:**
- Komponent nie przyjmuje żadnych props (dane pracownika są pobierane z kontekstu autentykacji)

## Integracja z modułem ticket-planning

Strona integruje moduł `ticket-planning` w następujący sposób:

1. **Import modułu:**
   ```typescript
   import { TicketPlanning } from '../../modules/worker/ticket-planning';
   ```

2. **Renderowanie modułu:**
   - Moduł jest renderowany jako główna zawartość strony
   - Moduł automatycznie pobiera identyfikator zalogowanego pracownika z kontekstu autentykacji

3. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu ticket-planning](../../modules/worker/ticket-planning/readme.md)

## Integracja z layoutem pracowniczym

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`):

1. **Routing:**
   - Strona jest dostępna pod ścieżką `/worker/planning`
   - Layout renderuje stronę w sekcji głównej zawartości (outlet)

2. **Menu nawigacyjne:**
   - Strona jest dostępna w menu nawigacyjnym layoutu jako "Planowanie" lub "Planowanie ticketów"
   - Link w menu prowadzi do `/worker/planning`

3. **Szczegółowa dokumentacja layoutu:**
   - [Dokumentacja layoutu worker](../../worker/layout/readme.md)

## Routing

Strona powinna być dostępna pod następującą ścieżką:

- **Ścieżka:** `/worker/planning`
- **Warunki dostępu:** Strona jest dostępna wyłącznie dla zalogowanych pracowników
- **Przekierowania:**
  - Niezalogowani użytkownicy są automatycznie przekierowywani do strony logowania (`/`)
  - Layout pracowniczy automatycznie obsługuje przekierowania

### Przykładowa konfiguracja React Router:

```typescript
<Route path="/worker" element={<WorkerLayout />}>
  <Route path="planning" element={<PlanningPage />} />
</Route>
```

## Wymogi dostępności

Strona musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA). Szczegółowe wymogi dostępności są opisane w dokumentacji modułu `ticket-planning`.

## Uwagi implementacyjne

1. **Autentykacja:**
   - Strona wymaga zalogowania pracownika
   - Layout pracowniczy automatycznie obsługuje sprawdzanie autentykacji
   - Identyfikator pracownika jest pobierany z kontekstu autentykacji

2. **Integracja z modułem:**
   - Strona jest prostym wrapperem dla modułu `ticket-planning`
   - Wszystka logika biznesowa jest zawarta w module
   - Strona odpowiada tylko za renderowanie modułu w kontekście layoutu

3. **Responsywność:**
   - Moduł `ticket-planning` jest responsywny
   - Layout pracowniczy zapewnia responsywny układ strony

4. **Testowanie:**
   - Testy jednostkowe dla komponentu strony (renderowanie modułu)
   - Testy integracyjne dla procesu planowania ticketów
   - Testy dostępności (nawigacja klawiaturą, czytniki ekranu)

## Przykładowa struktura plików

```
frontend/app/pages/worker/planning/
├── readme.md (ten plik)
└── planning-page.tsx (główny komponent strony)
```

## Zależności

Strona wykorzystuje następujące moduły:

1. **Moduł ticket-planning:**
   - Ścieżka: `frontend/app/modules/worker/ticket-planning`
   - [Dokumentacja modułu](../../modules/worker/ticket-planning/readme.md)

2. **Layout worker:**
   - Ścieżka: `frontend/app/pages/worker/layout`
   - [Dokumentacja layoutu](../../worker/layout/readme.md)

3. **React Router:**
   - Do nawigacji i routingu

