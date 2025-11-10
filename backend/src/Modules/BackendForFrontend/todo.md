# Lista zadań dla AI – moduł `BackendForFrontend`

Dokument opisuje zadania, które należy wykonać w katalogu `backend/src/Modules/BackendForFrontend`, aby dostarczyć warstwę kontrolerów HTTP zgodną z modułami frontendowymi (`frontend/app/api/**`, `frontend/app/modules/**/readme.md`) oraz fasadami serwisowymi backendu (`backend/src/Modules/*/readme.md`).

## Kontekst i zależności

- Kontrolery w module `BackendForFrontend` mają być cienką warstwą HTTP dla fasad domenowych opisanych w innych modułach (`Authentication`, `Authorization`, `Clients`, `TicketCategories`, `Tickets`, `WorkerAvailability`, `WorkerSchedule`).
- Wszystkie endpointy, które mają zostać wystawione, są zmapowane w `frontend/app/api/http.ts` (`apiPaths`) i są używane przez hooki TanStack Query w `frontend/app/api/**`.
- Szczegółowe wymagania interfejsów użytkownika i oczekiwanych kontraktów znajdują się w `frontend/app/modules/**/readme.md` (np. `worker-phone-receive`, `worker-availability`, `worker-schedule`, `manager-monitoring`, `unauthenticated/ticket-add`, `unauthenticated/ticket-chat`).
- Backend działa w Symfony – zachowaj konwencję namespace’ów `App\Modules\BackendForFrontend\...` oraz wykorzystaj atrybuty routingu Symfony.
- Jeśli trzeba będzie coś zmienić w modułach backendowych lub front-endowych, oznacz to w odpowiednich plikach readme.md w katalogach tych modułów.
- Jeśli będą potrzebne jakieś interfejsy lub DTO - stwórz je.

## Zadania (wykonuj w podanej kolejności)

1. [x] **Przygotowanie struktury katalogów modułu**
   - Bezpośrednio w katalogu `BackendForFrontend/` utwórz foldery odpowiadające kontekstom: `Auth/`, `Manager/`, `TicketCategories/`, `Public/Tickets/`, `Shared/`, `Worker/Availability/`, `Worker/Clients/`, `Worker/Phone/`, `Worker/Planning/`, `Worker/Schedule/`, `Worker/Tickets/`.
   - Do przechowywania DTO, transformerów, walidatorów itp. dodawaj podkatalogi w obrębie danego kontekstu (np. `Worker/Planning/Dto/`, `Shared/Response/`).
   - Nie twórz plików `__init__.php`. PSR-4 autoloading zapewnij przez zgodność namespace’ów (np. `App\Modules\BackendForFrontend\Worker\Planning\AssignTicketController`).
   - Dodaj krótki dokument `readme.md` (lub zaktualizuj obecny README) z opisem konwencji nazewniczej i miejscem na klasy pomocnicze.

2. [x] **Warstwa wspólna BackendForFrontend**
   - Zaimplementuj bazową klasę kontrolera (np. `AbstractJsonController`) w katalogu `Shared/`, która standaryzuje odpowiedzi JSON, obsługę wyjątków domenowych oraz walidację danych wejściowych (np. przy użyciu Symfony Validator lub własnych reguł).
   - Przygotuj serwis `AuthenticatedWorkerProvider` odpowiedzialny za pobieranie aktualnie zalogowanego pracownika (np. z sesji / tokenu). Udokumentuj w komentarzu jak ma być wstrzykiwany (Security, session).
   - Zaprojektuj mapowanie wyjątków domenowych (np. brak zasobu, brak uprawnień, walidacja) na kody HTTP zgodnie z oczekiwaniami frontendu (hooki zakładają JSON z `message`).

3. [x] **Kontrolery autentykacji i sesji (`Auth`)**
   - Endpoint `POST /api/auth/register`:
     - Waliduj payload `login`, `password`, `categoryIds`, `isManager`.
     - Użyj `AuthenticationService::registerWorker` do utworzenia pracownika.
     - Zweryfikuj `categoryIds` przez `TicketCategoryService` i przypisz je przez `AuthorizationService::assignCategoriesToWorker`.
     - Jeśli `isManager`, wywołaj `AuthorizationService::setManagerRole`.
     - Zwróć strukturę z `worker` i `categories` zgodną z `frontend/app/api/auth.ts`.
   - Endpoint `POST /api/auth/login`:
     - Użyj `AuthenticationService::authenticateWorker`.
     - W przypadku sukcesu zainicjuj sesję (lub wygeneruj token) i zwróć dane zgodne z `LoginResponse`.
     - Zadbaj o bezpieczne hashowanie haseł (bcrypt) i obsłuż błędne dane logowania kodem 401.
   - Dodaj zadanie dla ewentualnego endpointu `POST /api/auth/logout` (jeśli potrzebny w przyszłości) – pozostaw TODO w README.

4. [x] **Kontrolery menedżerskie (`Manager`)**
   - `GET /api/manager/monitoring`: Zbuduj odpowiedź z sekcjami `summary`, `workerStats`, `queueStats`, `autoAssignmentSettings` na podstawie danych z `WorkerScheduleService`, `TicketsService`, `WorkerAvailabilityService`, `AuthorizationService`. Skorzystaj z opisów w `frontend/app/modules/manager/manager-monitoring/readme.md`.
   - `PUT /api/manager/auto-assignment`: Aktualizuj konfigurację automatycznego przypisywania (może wykorzystywać dedykowany serwis w `WorkerSchedule`). W odpowiedzi zwróć aktualne ustawienia i timestamp `updatedAt`.
   - `POST /api/manager/auto-assignment/trigger`: Uruchom algorytm automatycznego przypisywania na wskazaną datę – opieraj się na `WorkerScheduleService::autoAssignTicketsToAllWorkers`.
   - `GET /events/manager/monitoring/{managerId}` (SSE): Zaplanuj kontroler/event stream dostarczający zdarzenia `worker_stats_updated`, `queue_stats_updated`, `ticket_added`, `ticket_status_changed`. Upewnij się, że autoryzacja sprawdza czy `managerId` należy do aktualnie zalogowanego managera.

5. [x] **Kontrolery kategorii (`TicketCategories`)**
   - `GET /api/ticket-categories`: Wystaw listę kategorii na podstawie `TicketCategoryService::getAllCategories()`, transformując na format używany przez `frontend/app/api/ticket-categories.ts`.

6. [x] **Kontrolery ticketów publicznych (`Public/Tickets`)**
   - `POST /api/tickets`: Obsłuż formularz zgłoszenia (moduł `unauthenticated/ticket-add`). Logika:
     - Znajdź/utwórz klienta przez `ClientService` (obsługa klientów anonimowych).
     - Utwórz ticket poprzez `TicketService::createTicket`.
     - Zwróć `ticket` zgodnie z `CreateTicketResponse`.
   - `GET /api/tickets/{ticketId}`: Zwróć szczegóły ticketa (klient, kategoria, status) – wykorzystaj `TicketService::getTicketById`.
   - `POST /api/tickets/{ticketId}/messages`: Dodaj wiadomość do ticketa (np. czat z klientem). Zaplanuj serwis (jeśli brak) w module Tickets i zwróć `message`.

7. [x] **Kontrolery obszaru pracownika – dostępności (`Worker/Availability`)**
   - `GET /api/worker/availability`: Zwróć najnowszą dostępność pracownika (7 dni) z `WorkerAvailabilityService::getWorkerAvailabilityForWeek`.
   - `POST /api/worker/availability/{date}`: Zapisz sloty dostępności dla dnia – użyj `addWorkerAvailability` / `updateWorkerAvailability` zależnie od istniejących wpisów.
   - `PUT /api/worker/availability/{date}/time-slots/{id}` i `DELETE ...`: Obsłuż modyfikację i usuwanie slotów.
   - `POST /api/worker/availability/copy`: Skopiuj dostępności na inne dni, respektując flagę `overwrite`. Zwróć listę `copied` i `skipped`.
   - Zapewnij walidację formatów czasu (`HH:mm`) oraz logikę, że slot nie przekracza jednego dnia (zgodnie z wymaganiami modułu WorkerAvailability).

8. [x] **Kontrolery obszaru pracownika – planowanie (`Worker/Planning`)**
   - `GET /api/worker/tickets/backlog`: Zwróć backlog ticketów możliwych do zaplanowania, filtrując po kategoriach i uprawnieniach pracownika.
   - `GET /api/worker/schedule/week`: Zwróć zaplanowany tydzień (`WorkerScheduleService::getWorkerScheduleForWeek`), łącząc z informacją o dostępności.
   - `GET /api/worker/schedule/predictions`: Zwróć prognozy (`WorkerScheduleService::calculatePredictedTicketCount` + dane o dostępności i efektywności).
   - `POST /api/worker/schedule/assign` / `DELETE /api/worker/schedule/assign`: Obsłuż ręczne przypisywanie i usuwanie ticketów z grafika (wykorzystaj `assignTicketToWorker`, `removeTicketFromSchedule`).
   - `POST /api/worker/schedule/auto-assign`: Uruchom auto-przydział dla konkretnego pracownika (opcjonalnie filtr kategorii).
   - Dbaj o spójność odpowiedzi z hookami w `frontend/app/api/worker/planning.ts` i readme modułów `worker/ticket-planning`.

9. [x] **Kontrolery obszaru pracownika – bieżący grafik i status (`Worker/Schedule`)**
   - `GET /api/worker/schedule`: Zwróć bieżący grafik (ostatnie dni + aktywny ticket). Dane potrzebne w module `worker-schedule` frontendowym.
   - `GET /api/worker/work-status`: Oblicz status obciążenia dziennego (`WorkerScheduleService::getWorkerScheduleStatistics`, `TicketsService` do sum czasu).
   - `POST /api/worker/tickets/{ticketId}/status`: Aktualizacja statusu ticketa (wykorzystaj `TicketService::updateTicketStatus` oraz walidację uprawnień).
   - `POST /api/worker/tickets/{ticketId}/time`: Dodawanie czasu pracy lub rozmowy (dedykowana metoda serwisu do rejestracji czasu), aktualizacja `timeSpent`.
   - `POST /api/worker/tickets/{ticketId}/notes`: Dodawanie notatek (deleguj do `TicketService::addTicketNote`).

10. [x] **Kontrolery obszaru pracownika – obsługa telefonu (`Worker/Phone`)**
   - Endpointy `POST /api/worker/phone/receive` oraz `POST /api/worker/phone/end` są dostępne w `WorkerPhoneController`.
   - Wspierają je klasy `StartPhoneCallRequest`, `EndPhoneCallRequest` oraz serwis kontraktowy `WorkerPhoneServiceInterface` odpowiedzialny za integrację z fasadami domenowymi.
   - TODO: dostarczyć implementację serwisu w module domenowym (np. Tickets/WorkerSchedule) tak, aby obsłużyć zamykanie wpisów czasu i rejestrację połączeń telefonicznych zgodnie z wymaganiami modułu frontendowego.

11. [x] **Kontrolery obszaru pracownika – ticket i klient helpery (`Worker/Tickets`, `Worker/Clients`)**
    - `GET /api/worker/tickets/search`: Udostępnij wyszukiwanie ticketów z filtrami (status, kategoria, zapytanie). Uwzględnij uprawnienia z `AuthorizationService`.
    - `POST /api/worker/tickets`: Tworzenie ticketa wewnątrz przepływu rozmowy (re-use logiki z publicznego endpointu, ale z dodatkowym kontekstem pracownika).
    - `GET /api/worker/clients/search`: Wyszukiwanie klientów (email, telefon) z `ClientService`.

12. [x] **Walidacja i autoryzacja**
    - Każdy endpoint musi potwierdzać, że zalogowany pracownik ma dostęp do kategorii / roli wymaganej przez operację (np. tylko manager może używać `/api/manager/**`).
    - Dodaj middleware / atrybuty sprawdzające role (`isManager`) przed wejściem do kontrolera.
    - W przypadku braku uprawnień zwracaj status 403 z komunikatem `{"message": "Brak uprawnień"}`.

13. [x] **Testy i dokumentacja**
   - Utworzono strukturę katalogów testowych w `tests/Unit/Modules/BackendForFrontend/` (m.in. `Auth/`, `Manager/`, `Public/Tickets/`, `Worker/**`) wraz z klasami testowymi zawierającymi TODO scenariuszy.
   - Przygotowano wspólną klasę bazową `BackendForFrontendTestCase` z mockami usług domenowych, helperami żądań i fixture'ami zalogowanych pracowników.
   - Każdy kontroler posiada listę scenariuszy testowych (walidacja, brak uprawnień, sukces) opisanych jako komentarze TODO.
   - Dodano dokument `tests/Unit/Modules/BackendForFrontend/readme.md` z konwencjami i instrukcją uruchamiania testów.
   - Zaktualizowano `backend/readme.md` o informację o warstwie HTTP BackendForFrontend i dedykowanym katalogu testów.

14. [x] **Konfiguracja usług Symfony**
    - Dodaj aliasy w `services.yaml` mapujące utworzone interfejsy (`AuthenticationServiceInterface`, `AuthorizationServiceInterface`, `TicketCategoryServiceInterface`, itp.) na istniejące fasady domenowe.
    - Upewnij się, że wszystkie kontrolery w `BackendForFrontend` są autokonfigurowane jako serwisy (`autowire`, `autoconfigure`, `public: false`).
    - Skonfiguruj dostęp do sesji w kernelu (framework.session) tak, aby `AuthenticatedWorkerProvider` miał zawsze dostęp do `SessionInterface`.

15. [x] **Routing i integracja HTTP**
    - Zweryfikuj, że atrybutowe trasy kontrolerów są zarejestrowane (np. przez `controllers:` w `config/routes.yaml`).
    - Dla endpointów SSE (`/events/manager/monitoring/{managerId}`) skonfiguruj odpowiednie nagłówki cache/timeout.
    - Dodaj wpisy w `frontend/app/api/http.ts` jeśli pojawią się nowe ścieżki (np. `logout`, ewentualne helpery) i zsynchronizuj je z backendem.

## Materiały odniesienia

- `frontend/app/api/http.ts` – mapa ścieżek API.
- `frontend/app/api/*.ts` – kontrakty JSON (request/response).
- `frontend/app/modules/**/readme.md` – przepływy biznesowe i wymagania UX.
- `backend/src/Modules/*/readme.md` – opis fasad serwisowych, z których należy korzystać.

Aktualizuj listę zadań wraz z postępami, aby zachować spójność między frontem a backendem.


