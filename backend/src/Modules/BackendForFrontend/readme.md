## Struktura Modułu BackendForFrontend

Przestrzeń nazw `App\Modules\BackendForFrontend` grupuje cienkie kontrolery HTTP przekierowujące
żądania frontendowe do fasad domenowych zlokalizowanych w innych modułach backendu. Moduł jest
uporządkowany według ograniczonych kontekstów, aby utrzymać spójność endpointów z modułami frontendu.

- `Auth/` – kontrolery autentykacji i sesji.
- `Manager/` – panele menedżerskie, monitoring i endpointy automatycznego przydzielania.
- `TicketCategories/` – endpointy katalogowe eksponowane do UI.
- `Public/Tickets/` – endpointy do zgłaszania ticketów bez autoryzacji i czatu.
- `Shared/` – bazowe kontrolery, pomocniki DTO, transformatory, walidatory oraz wspólna
  infrastruktura (np. `AbstractJsonController`, mapery żądań, providerzy autoryzacji).
- `Worker/` – endpointy dla pracowników podzielone na dedykowane subkonteksty:
  - `Availability/`
  - `Clients/`
  - `Phone/`
  - `Planning/`
  - `Schedule/`
  - `Tickets/`

### Konwencje Nazewnictwa

- Kontrolery znajdują się bezpośrednio w folderze kontekstu i kończą się sufiksem `Controller`
  (np. `WorkerScheduleController`).
- DTO, odpowiedzi i transformatory znajdują się w podkatalogach `Dto/`, `Response/` lub `Transformer/`
  wewnątrz danego kontekstu.
- Zasady walidacji żądań powinny być kapsułowane w dedykowanych klasach (np.
  `RequestValidator`) w katalogu `Validator/`.
- Wspólne wyjątki odwzorowywane na statusy HTTP należą do `Shared/Exception/`.

### Przyszłe Pomocniki

Wykorzystuj `Shared/` do:
- Wspólnych abstrakcji HTTP (`AbstractJsonController`, budowniczy odpowiedzi).
- Pomocników autoryzacji (`AuthenticatedWorkerProvider`).
- Mapowania wyjątków na odpowiedzi HTTP.
- Bazowych walidatorów lub traitów dzielonych między kontrolerami.

Testy jednostkowe dla tego modułu znajdują się w `tests/Unit/Modules/BackendForFrontend/`
i odwzorowują strukturę przestrzeni nazw produkcyjnych.

### TODO

- [ ] Zweryfikować, czy endpoint `POST /api/auth/logout` powinien dodatkowo unieważniać tokeny sesyjne przechowywane poza PHP (np. Redis), jeśli taka infrastruktura zostanie dodana.
- [ ] Zintegrować `WorkerPhoneServiceInterface` z implementacją domenową rejestrującą rozmowy telefoniczne (Tickets / WorkerSchedule), aby zakończyć aktywne wpisy czasu i automatycznie przypisywać tickety do grafika.

