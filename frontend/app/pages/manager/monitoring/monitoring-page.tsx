import * as React from "react";
import { useNavigate } from "react-router";

import { ManagerMonitoring } from "~/modules/manager/manager-monitoring";
import { loadWorkerSession } from "~/modules/unauthenticated/worker-login/session";

export default function ManagerMonitoringPage() {
  const navigate = useNavigate();
  const session = React.useMemo(() => loadWorkerSession(), []);
  const worker = session?.worker;
  const isManager = Boolean(worker?.isManager);
  const managerId = worker?.id ?? null;

  const handleBackToDashboard = React.useCallback(() => {
    navigate("/worker");
  }, [navigate]);

  if (!isManager || !managerId) {
    return (
      <div className="mx-auto flex max-w-3xl flex-col gap-6 rounded-2xl border border-red-200 bg-red-50 px-6 py-8 text-center shadow-sm dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-100">
        <h1 className="text-2xl font-semibold tracking-tight">Brak uprawnień</h1>
        <p className="text-sm leading-relaxed text-red-700 dark:text-red-200">
          Potrzebujesz uprawnień managera, aby monitorować pracę zespołu. Jeśli uważasz, że to błąd,
          skontaktuj się z administratorem systemu.
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
          Monitoring zespołu
        </h1>
        <p className="max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          Śledź obciążenie pracowników, stan kolejek ticketów oraz zarządzaj automatycznym
          przypisywaniem zadań w czasie rzeczywistym. Wszystkie dane aktualizują się samoczynnie
          dzięki połączeniu SSE.
        </p>
      </header>

      <section className="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <ManagerMonitoring managerId={managerId} />
      </section>
    </div>
  );
}

