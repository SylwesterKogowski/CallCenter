import * as React from "react";

import { PhoneReceiveButton } from "./phone-receive-button";
import { WorkerInfo } from "./worker-info";
import type { WorkerSummary } from "../types";

interface HeaderProps {
  worker: WorkerSummary;
  onPhoneReceive: () => void;
  isPhoneReceiveDisabled?: boolean;
  isPhoneReceiveActive?: boolean;
  onLogout?: () => void;
  onToggleSidebar?: () => void;
  isSidebarOpen?: boolean;
  extraContent?: React.ReactNode;
}

export const Header: React.FC<HeaderProps> = ({
  worker,
  onPhoneReceive,
  isPhoneReceiveDisabled = false,
  isPhoneReceiveActive = false,
  onLogout,
  onToggleSidebar,
  isSidebarOpen = false,
  extraContent,
}) => {
  return (
    <header
      className="sticky top-0 z-30 flex flex-col gap-4 border-b border-slate-200 bg-white/95 px-4 py-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95 lg:px-8"
      role="banner"
    >
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={onToggleSidebar}
            className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
            aria-label={`${isSidebarOpen ? "Zamknij" : "Otwórz"} menu nawigacji`}
          >
            {isSidebarOpen ? "Zamknij" : "Menu"}
          </button>

          <div>
            <h1 className="text-xl font-semibold text-slate-900 dark:text-slate-100">
              Panel pracownika
            </h1>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Zarządzaj ticketami, dostępnością oraz monitoruj zgłoszenia klientów.
            </p>
          </div>
        </div>

        <WorkerInfo worker={worker} onLogout={onLogout} />
      </div>

      <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
        <PhoneReceiveButton
          onClick={onPhoneReceive}
          isDisabled={isPhoneReceiveDisabled}
          isActive={isPhoneReceiveActive}
        />
        {extraContent}
      </div>
    </header>
  );
};


