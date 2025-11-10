# Przegląd hooków API

Ten katalog zawiera współdzielone narzędzia do komunikacji z API oraz haki TanStack Query, które odwzorowują endpointy opisane w README modułów funkcjonalnych. Wszystkie żądania korzystają z `VITE_API_URL` jako adresu bazowego i domyślnie wysyłają ciasteczka (`credentials: "include"`), aby obsługiwać backendy oparte o sesje.

## Struktura

- `http.ts` – niskopoziomowy helper HTTP (`apiFetch`) z typowanymi błędami i centralną mapą ścieżek backendu.
- `react-query.ts` – współdzielone aliasy typów dla opcji zapytań i mutacji.
- `types.ts` – wspólne typy domenowe wykorzystywane w hakach (tickety, kategorie, klienci, notatki).
- `ticket-categories.ts` – hook zapytania pobierający listę kolejek/kategorii.
- `auth.ts` – mutacje do rejestracji i logowania pracowników.
- `tickets.ts` – operacje tworzenia ticketa i czatu używane w modułach bez autoryzacji.
- `manager.ts` – zapytania i mutacje panelu monitoringu (w tym sterowanie auto-przydziałem).
- `worker/availability.ts` – operacje CRUD na dostępnościach pracownika.
- `worker/planning.ts` – hooki dla backlogu, tygodniowego grafiku i auto-przydziału ticketów.
- `worker/schedule.ts` – zapytania grafiku pracownika oraz mutacje statusów/czasu/notatek.
- `worker/phone.ts` – wsparcie dla przepływu „odbieram telefon” (cykl życia połączenia, wyszukiwanie ticketów/klientów).

## Użycie

Owiń aplikację w `QueryClientProvider` (zob. dokumentacja TanStack Query), a następnie wywołuj hooki wewnątrz komponentów React:

```tsx
import { useManagerMonitoringQuery } from "~/app/api/manager";

function ManagerMonitoringPage({ date }: { date: string }) {
  const { data, isPending, error } = useManagerMonitoringQuery({ date });

  if (isPending) return <Spinner />;
  if (error) return <ErrorState message={error.message} />;

  return <MonitoringDashboard summary={data.summary} />;
}
```

Mutacje udostępniają standardowe metody TanStack Query i automatycznie odświeżają powiązane zapytania:

```tsx
import { useAssignTicketMutation } from "~/app/api/worker/planning";

function AssignButton({ ticketId, date }: { ticketId: string; date: string }) {
  const { mutate, isPending } = useAssignTicketMutation();

  return (
    <button onClick={() => mutate({ ticketId, date })} disabled={isPending}>
      {isPending ? "Przypisywanie..." : "Przypisz ticket"}
    </button>
  );
}
```

Opcjonalny argument `options` dostępny w każdym hooku pozwala nadpisać ustawienia TanStack Query, np. czas ważności danych czy strategię ponawiania zapytań.


