import * as React from "react";
import { useNavigate } from "react-router";

import { WorkerRegisterForm } from "~/modules/manager/worker-register";
import { loadWorkerSession } from "~/modules/unauthenticated/worker-login/session";

export default function WorkerRegistrationPage() {
  const navigate = useNavigate();
  const session = React.useMemo(() => loadWorkerSession(), []);
  const isManager = Boolean(session?.worker.isManager);

  const handleBackToDashboard = React.useCallback(() => {
    navigate("/worker");
  }, [navigate]);

  if (!isManager) {
    return (
      <div className="mx-auto flex max-w-3xl flex-col gap-6 rounded-2xl border border-red-200 bg-red-50 px-6 py-8 text-center shadow-sm dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-100">
        <h1 className="text-2xl font-semibold tracking-tight">Brak uprawnień</h1>
        <p className="text-sm leading-relaxed text-red-700 dark:text-red-200">
          Potrzebujesz uprawnień managera, aby rejestrować nowych pracowników. Jeśli
          uważasz, że to błąd, skontaktuj się z administratorem systemu.
        </p>
        <div className="flex justify-center">
          <button
            type="button"
            onClick={handleBackToDashboard}
            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
          >
            Wróć do panelu
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-10">
      <header className="space-y-3 rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <span className="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">
          Panel managera
        </span>
        <h1 className="text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
          Rejestracja nowego pracownika
        </h1>
        <p className="max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          Utwórz konto pracownika, zdefiniuj jego hasło i przypisz kategorie ticketów,
          do których powinien mieć dostęp. W każdej chwili możesz zarejestrować kolejnego
          pracownika – formularz automatycznie resetuje się po pomyślnej rejestracji.
        </p>
      </header>

      <div className="grid gap-6 lg:grid-cols-[2fr,1fr]">
        <section className="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <WorkerRegisterForm />
        </section>

        <aside className="space-y-4 rounded-2xl border border-slate-200 bg-white px-6 py-6 text-sm text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
            Wskazówki dla managera
          </h2>
          <ul className="list-disc space-y-2 pl-4 text-left marker:text-blue-600 dark:marker:text-blue-300">
            <li>
              Login powinien być unikalny w systemie i zawierać od 3 do 255 znaków (litery,
              cyfry, kropki lub podkreślenia).
            </li>
            <li>
              Hasło musi mieć co najmniej 8 znaków. Po utworzeniu konta przekaż dane
              logowania nowemu pracownikowi bezpiecznym kanałem.
            </li>
            <li>
              Przypisz kategorie ticketów, aby pracownik otrzymywał odpowiednie zgłoszenia.
              Możesz zaznaczyć wiele kategorii jednocześnie.
            </li>
            <li>
              Jeśli nowy pracownik ma zarządzać zespołem, zaznacz opcję nadania uprawnień
              managera.
            </li>
          </ul>
          <div className="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200">
            Wszystkie działania rejestracji są logowane. W razie potrzeby możesz skontaktować
            się z zespołem bezpieczeństwa, aby uzyskać historię zmian.
          </div>
        </aside>
      </div>
    </div>
  );
}

