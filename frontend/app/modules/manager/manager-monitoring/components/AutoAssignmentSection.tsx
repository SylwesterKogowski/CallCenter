import * as React from "react";

import type { AutoAssignmentSettings, TriggerAutoAssignmentResponse } from "~/api/manager";

import { formatDateTime } from "../utils";
import { AutoAssignmentToggle } from "./AutoAssignmentToggle";

export interface AutoAssignmentSectionProps {
  settings: AutoAssignmentSettings | null;
  onToggle: (enabled: boolean) => void;
  onManualTrigger: () => void;
  isUpdating: boolean;
  isTriggering: boolean;
  lastTriggerResult?: TriggerAutoAssignmentResponse | null;
  error?: string | null;
}

export const AutoAssignmentSection: React.FC<AutoAssignmentSectionProps> = ({
  settings,
  onToggle,
  onManualTrigger,
  isUpdating,
  isTriggering,
  lastTriggerResult,
  error,
}) => {
  return (
    <section className="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-semibold text-gray-900">Automatyczne przypisywanie</h2>
          <p className="text-sm text-gray-500">
            Włącz lub wyłącz automatyczne przypisywanie ticketów do pracowników.
          </p>
        </div>
        <AutoAssignmentToggle enabled={settings?.enabled ?? false} onToggle={onToggle} isLoading={isUpdating} />
      </header>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-md border border-blue-100 bg-blue-50 px-4 py-3">
          <h3 className="text-sm font-semibold text-blue-900">Ostatnie uruchomienie</h3>
          <p className="text-sm text-blue-800">
            {settings?.lastRun ? formatDateTime(settings.lastRun) : "Brak danych o ostatnim uruchomieniu"}
          </p>
          <p className="mt-2 text-sm text-blue-800">
            Przydzielone tickety: <span className="font-semibold">{settings?.ticketsAssigned ?? 0}</span>
          </p>
        </div>
        <div className="rounded-md border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-800">
          <h3 className="font-semibold text-green-900">Aktywne reguły</h3>
          <ul className="mt-2 list-disc space-y-1 pl-5">
            <li>
              Uwzględnia efektywność:{" "}
              <strong>{settings?.settings.considerEfficiency ? "tak" : "nie"}</strong>
            </li>
            <li>
              Uwzględnia dostępność:{" "}
              <strong>{settings?.settings.considerAvailability ? "tak" : "nie"}</strong>
            </li>
            <li>
              Limit ticketów na pracownika:{" "}
              <strong>{settings?.settings.maxTicketsPerWorker ?? "—"}</strong>
            </li>
          </ul>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
          onClick={onManualTrigger}
          disabled={isTriggering || !settings?.enabled}
        >
          {isTriggering ? "Uruchamianie..." : "Ręcznie uruchom przypisywanie"}
        </button>
        <span className="text-sm text-gray-500">
          System przypisze tickety zgodnie z aktywnymi regułami obciążenia.
        </span>
      </div>

      {lastTriggerResult ? (
        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          <p className="font-semibold">{lastTriggerResult.message}</p>
          <p className="mt-1">
            Liczba przypisanych ticketów: <strong>{lastTriggerResult.ticketsAssigned}</strong>
          </p>
          <p className="mt-1">
            Zakończono o: <strong>{formatDateTime(lastTriggerResult.completedAt)}</strong>
          </p>
          {lastTriggerResult.assignedTo.length > 0 ? (
            <ul className="mt-2 list-disc space-y-1 pl-5">
              {lastTriggerResult.assignedTo.map((item) => (
                <li key={item.workerId}>
                  {item.workerId}: {item.ticketsCount} ticketów
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}

      {error ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}
    </section>
  );
};


