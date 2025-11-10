# Backend aplikacji Grafik Call Center

Backend projektu powstaje w Symfony (PHP) i korzysta z bazy danych MySQL. Kod znajduje się w katalogu `backend/src`, a konfiguracja narzędzi deweloperskich (PHP-CS-Fixer, PHPStan, PHPUnit) jest dostępna w plikach konfiguracyjnych w tym katalogu.

Warstwa HTTP udostępniana frontendowi jest zorganizowana w module `BackendForFrontend` (`backend/src/Modules/BackendForFrontend`). Odpowiada za mapowanie kontraktów API na fasady domenowe oraz posiada dedykowany katalog testów jednostkowych w `tests/Unit/Modules/BackendForFrontend`.

## Przydatne polecenia

- `./exec.sh composer run test` – uruchamia zestaw testów jednostkowych i integracyjnych  (PHPUnit) zdefiniowanych w `composer.json`.
- `./exec.sh composer run fix-styles` – formatuje kod zgodnie z regułami PHP-CS-Fixer.

Wszystkie powyższe polecenia należy uruchamiać z katalogu backend.
