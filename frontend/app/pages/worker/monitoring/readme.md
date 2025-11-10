# Strona monitoringu dla kierownika

## Opis strony

Strona monitoringu jest stroną dostępną wyłącznie dla zalogowanych pracowników z rolą managera w systemie Call Center. Strona wykorzystuje moduł `manager-monitoring`, który umożliwia kierownikowi monitorowanie stanu systemu Call Center w czasie rzeczywistym.

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`) i jest dostępna pod ścieżką `/worker/monitoring`. Strona jest dostępna wyłącznie dla zalogowanych pracowników z rolą managera.

## Funkcjonalność

Strona renderuje moduł `manager-monitoring`. Szczegółowy opis funkcjonalności modułu znajduje się w [dokumentacji modułu manager-monitoring](../../modules/manager/manager-monitoring/readme.md).

## Struktura strony

Strona składa się z głównego komponentu, który renderuje moduł `manager-monitoring` w kontekście layoutu pracowniczego.

### MonitoringPage (Główny komponent strony)
Główny komponent strony, który renderuje moduł `manager-monitoring`.

**Funkcjonalność:**
- Renderowanie modułu `manager-monitoring`
- Integracja z layoutem pracowniczym
- Obsługa routingu i nawigacji
- Sprawdzanie uprawnień managera

**Props:**
- Komponent nie przyjmuje żadnych props (dane managera są pobierane z kontekstu autentykacji)

## Integracja z modułem manager-monitoring

Strona integruje moduł `manager-monitoring` w następujący sposób:

1. **Import modułu:**
   ```typescript
   import { ManagerMonitoring } from '../../modules/manager/manager-monitoring';
   ```

2. **Renderowanie modułu:**
   - Moduł jest renderowany jako główna zawartość strony
   - Moduł automatycznie pobiera identyfikator zalogowanego managera z kontekstu autentykacji

3. **Szczegółowa dokumentacja modułu:**
   - [Dokumentacja modułu manager-monitoring](../../modules/manager/manager-monitoring/readme.md)

## Integracja z layoutem pracowniczym

Strona jest renderowana w layoutcie pracowniczym (`worker/layout`):

1. **Routing:**
   - Strona jest dostępna pod ścieżką `/worker/monitoring`
   - Layout renderuje stronę w sekcji głównej zawartości (outlet)
   - Dostęp jest ograniczony tylko dla pracowników z rolą managera

2. **Menu nawigacyjne:**
   - Strona jest dostępna w menu nawigacyjnym layoutu jako "Monitoring" lub "Monitorowanie systemu"
   - Link w menu prowadzi do `/worker/monitoring`
   - Opcja menu jest wyświetlana tylko dla managerów

3. **Szczegółowa dokumentacja layoutu:**
   - [Dokumentacja layoutu worker](../../worker/layout/readme.md)

## Routing

Strona powinna być dostępna pod następującą ścieżką:

- **Ścieżka:** `/worker/monitoring`
- **Warunki dostępu:** Strona jest dostępna wyłącznie dla zalogowanych pracowników z rolą managera
- **Przekierowania:**
  - Niezalogowani użytkownicy są automatycznie przekierowywani do strony logowania (`/`)
  - Pracownicy bez roli managera są przekierowywani do strony głównej (`/worker/schedule`)
  - Layout pracowniczy automatycznie obsługuje przekierowania

### Przykładowa konfiguracja React Router:

```typescript
<Route path="/worker" element={<WorkerLayout />}>
  <Route
    path="monitoring"
    element={
      <RequireManager>
        <MonitoringPage />
      </RequireManager>
    }
  />
</Route>
```

## Wymogi dostępności

Strona musi spełniać podstawowe wymogi dostępności (WCAG 2.1 Level A/AA). Szczegółowe wymogi dostępności są opisane w dokumentacji modułu `manager-monitoring`.

## Uwagi implementacyjne

1. **Autentykacja i autoryzacja:**
   - Strona wymaga zalogowania pracownika
   - Strona wymaga roli managera
   - Layout pracowniczy automatycznie obsługuje sprawdzanie autentykacji i autoryzacji
   - Identyfikator managera jest pobierany z kontekstu autentykacji

2. **Integracja z modułem:**
   - Strona jest prostym wrapperem dla modułu `manager-monitoring`
   - Wszystka logika biznesowa jest zawarta w module
   - Strona odpowiada tylko za renderowanie modułu w kontekście layoutu

3. **Responsywność:**
   - Moduł `manager-monitoring` jest responsywny
   - Layout pracowniczy zapewnia responsywny układ strony

4. **Testowanie:**
   - Testy jednostkowe dla komponentu strony (renderowanie modułu)
   - Testy autoryzacji (sprawdzanie dostępu tylko dla managerów)
   - Testy integracyjne dla procesu monitoringu
   - Testy dostępności (nawigacja klawiaturą, czytniki ekranu)

## Przykładowa struktura plików

```
frontend/app/pages/worker/monitoring/
├── readme.md (ten plik)
└── monitoring-page.tsx (główny komponent strony)
```

## Zależności

Strona wykorzystuje następujące moduły:

1. **Moduł manager-monitoring:**
   - Ścieżka: `frontend/app/modules/manager/manager-monitoring`
   - [Dokumentacja modułu](../../modules/manager/manager-monitoring/readme.md)

2. **Layout worker:**
   - Ścieżka: `frontend/app/pages/worker/layout`
   - [Dokumentacja layoutu](../../worker/layout/readme.md)

3. **React Router:**
   - Do nawigacji i routingu

