import * as React from "react";
import { Outlet, useLocation, useNavigate } from "react-router";
import { useMutation } from "@tanstack/react-query";

import { apiFetch, apiPaths, ApiError } from "~/api/http";
import { useWorkerScheduleQuery } from "~/api/worker/schedule";
import type { WorkerTicket } from "~/api/worker/phone";
import {
  clearWorkerSession,
  loadWorkerSession,
} from "~/modules/unauthenticated/worker-login/session";
import type { WorkerSession } from "~/modules/unauthenticated/worker-login/types";

import { Header } from "./components/header";
import { NavigationSidebar } from "./components/navigation-sidebar";
import { NavigationTopBar } from "./components/navigation-top-bar";
import { PhoneReceiveModal } from "~/modules/worker/worker-phone-receive/components/PhoneReceiveModal";
import type { WorkerSummary } from "./types";
import { MENU_ITEMS } from "./menu-items";

export interface WorkerLayoutProps {
  children?: React.ReactNode;
}

interface WorkerLayoutState {
  worker: WorkerSummary | null;
  isCheckingSession: boolean;
  isPhoneReceiveOpen: boolean;
  isSidebarOpen: boolean;
  authError: string | null;
}

type WorkerLayoutAction =
  | { type: "session-resolved"; worker: WorkerSummary }
  | { type: "session-expired"; message: string }
  | { type: "phone-receive-open" }
  | { type: "phone-receive-close" }
  | { type: "sidebar-open" }
  | { type: "sidebar-close" }
  | { type: "sidebar-toggle" };

const isSessionExpired = (expiresAt: string | null | undefined): boolean => {
  if (!expiresAt) {
    return false;
  }

  const expiresMillis = Date.parse(expiresAt);

  if (Number.isNaN(expiresMillis)) {
    return false;
  }

  return expiresMillis <= Date.now();
};

const createWorkerSummary = (session: WorkerSession): WorkerSummary => ({
  id: session.worker.id,
  login: session.worker.login,
  isManager: Boolean(
    (session.worker as WorkerSession["worker"] & { isManager?: boolean })
      .isManager,
  ),
});

const initialState: WorkerLayoutState = {
  worker: null,
  isCheckingSession: true,
  isPhoneReceiveOpen: false,
  isSidebarOpen: false,
  authError: null,
};

const reducer = (
  state: WorkerLayoutState,
  action: WorkerLayoutAction,
): WorkerLayoutState => {
  switch (action.type) {
    case "session-resolved":
      return {
        ...state,
        worker: action.worker,
        isCheckingSession: false,
        authError: null,
      };
    case "session-expired":
      return {
        ...state,
        worker: null,
        isCheckingSession: false,
        authError: action.message,
        isSidebarOpen: false,
        isPhoneReceiveOpen: false,
      };
    case "phone-receive-open":
      return {
        ...state,
        isPhoneReceiveOpen: true,
      };
    case "phone-receive-close":
      return {
        ...state,
        isPhoneReceiveOpen: false,
      };
    case "sidebar-open":
      return {
        ...state,
        isSidebarOpen: true,
      };
    case "sidebar-close":
      return {
        ...state,
        isSidebarOpen: false,
      };
    case "sidebar-toggle":
      return {
        ...state,
        isSidebarOpen: !state.isSidebarOpen,
      };
    default:
      return state;
  }
};

export const WorkerLayout: React.FC<WorkerLayoutProps> = ({ children }) => {
  const location = useLocation();
  const navigate = useNavigate();

  const [state, dispatch] = React.useReducer(reducer, initialState);
  const [logoutError, setLogoutError] = React.useState<string | null>(null);

  const handleNavigate = React.useCallback(
    (path: string) => {
      navigate(path);
    },
    [navigate],
  );

  React.useEffect(() => {
    let isMounted = true;
    const checkSession = () => {
      const session = loadWorkerSession();

      if (!session || isSessionExpired(session.expiresAt)) {
        clearWorkerSession();
        if (isMounted) {
          dispatch({
            type: "session-expired",
            message: "Twoja sesja wygasła. Zaloguj się ponownie.",
          });
        }
        navigate("/", { replace: true });
        return;
      }

      if (isMounted) {
        dispatch({
          type: "session-resolved",
          worker: createWorkerSummary(session),
        });
      }
    };

    checkSession();

    return () => {
      isMounted = false;
    };
  }, [navigate]);

  React.useEffect(() => {
    dispatch({ type: "sidebar-close" });
  }, [location.pathname]);

  const logoutMutation = useMutation({
    mutationFn: async () => {
      await apiFetch<void>({
        path: apiPaths.authLogout,
        method: "POST",
      });
    },
    onSuccess: () => {
      setLogoutError(null);
    },
    onError: (error: unknown) => {
      if (error instanceof ApiError) {
        setLogoutError(
          error.message || "Nie udało się wylogować. Spróbuj ponownie.",
        );
        return;
      }

      if (error instanceof Error) {
        setLogoutError(error.message);
        return;
      }

      setLogoutError("Nie udało się wylogować. Spróbuj ponownie.");
    },
    onSettled: () => {
      clearWorkerSession();
      navigate("/", { replace: true });
    },
  });

  const scheduleQuery = useWorkerScheduleQuery({
    enabled: Boolean(state.worker),
    staleTime: 60_000,
  });

  const previousActiveTicket = React.useMemo<WorkerTicket | null>(() => {
    const active = scheduleQuery.data?.activeTicket;

    if (!active || !active.client) {
      return null;
    }

    return {
      id: active.id,
      title: active.title,
      category: active.category,
      status: active.status,
      client: active.client,
      createdAt: active.scheduledDate,
      timeSpent: active.timeSpent,
    };
  }, [scheduleQuery.data?.activeTicket]);

  const handlePhoneReceiveOpen = React.useCallback(() => {
    dispatch({ type: "phone-receive-open" });
  }, []);

  const handlePhoneReceiveClose = React.useCallback(
    (completed: boolean) => {
      dispatch({ type: "phone-receive-close" });

      if (completed) {
        scheduleQuery.refetch().catch(() => {
          // Ignorujemy błąd odświeżenia – zostanie obsłużony przez mechanizmy Query.
        });
      }
    },
    [scheduleQuery],
  );

  const handleLogout = React.useCallback(() => {
    if (logoutMutation.isPending) {
      return;
    }

    logoutMutation.mutate();
  }, [logoutMutation]);

  const handleDismissLogoutError = React.useCallback(() => {
    setLogoutError(null);
  }, []);

  const mainContent = children ?? <Outlet />;
  const worker = state.worker;

  if (state.isCheckingSession) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50 dark:bg-slate-950">
        <div className="rounded-xl border border-slate-200 bg-white px-6 py-4 text-sm font-medium text-slate-600 shadow dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          Sprawdzamy stan sesji…
        </div>
      </div>
    );
  }

  if (!worker) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 text-center dark:bg-slate-950">
        <div className="max-w-md rounded-xl border border-slate-200 bg-white px-6 py-8 shadow dark:border-slate-800 dark:bg-slate-900">
          <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Brak dostępu
          </h1>
          <p className="mt-3 text-sm text-slate-600 dark:text-slate-400">
            {state.authError ??
              "Twoja sesja wygasła lub nie jesteś zalogowany. Zostaniesz przekierowany do strony logowania."}
          </p>
          <button
            type="button"
            onClick={() => navigate("/", { replace: true })}
            className="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
          >
            Przejdź do logowania
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      {state.isSidebarOpen ? (
        <div
          className="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"
          role="presentation"
          onClick={() => dispatch({ type: "sidebar-close" })}
        />
      ) : null}

      <NavigationSidebar
        items={MENU_ITEMS}
        currentPath={location.pathname}
        isManager={worker.isManager}
        onNavigate={handleNavigate}
        onLogout={handleLogout}
        isOpen={state.isSidebarOpen}
        onClose={() => dispatch({ type: "sidebar-close" })}
      />

      <div className="flex min-h-screen flex-1 flex-col">
        <Header
          worker={worker}
          onPhoneReceive={handlePhoneReceiveOpen}
          isPhoneReceiveDisabled={
            state.isPhoneReceiveOpen || logoutMutation.isPending
          }
          isPhoneReceiveActive={state.isPhoneReceiveOpen}
          onLogout={handleLogout}
          onToggleSidebar={() => dispatch({ type: "sidebar-toggle" })}
          isSidebarOpen={state.isSidebarOpen}
          extraContent={
            <div className="w-full lg:hidden">
              <NavigationTopBar
                items={MENU_ITEMS}
                currentPath={location.pathname}
                isManager={worker.isManager}
                onNavigate={handleNavigate}
              />
            </div>
          }
        />

        <main
          className="flex flex-1 flex-col gap-4 px-4 py-6 lg:px-8"
          role="main"
        >
          {logoutError ? (
            <div
              className="flex items-start justify-between gap-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-900/40 dark:text-amber-100"
              role="status"
            >
              <span>{logoutError}</span>
              <button
                type="button"
                onClick={handleDismissLogoutError}
                className="rounded-lg border border-amber-400 px-3 py-1 text-xs font-semibold transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:border-amber-500/60 dark:hover:bg-amber-800/40"
              >
                Zamknij
              </button>
            </div>
          ) : null}

          {scheduleQuery.error ? (
            <div className="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-200">
              Nie udało się pobrać bieżącego grafiku. Spróbuj odświeżyć stronę.
            </div>
          ) : null}

          {mainContent}
        </main>
      </div>

      <PhoneReceiveModal
        isOpen={state.isPhoneReceiveOpen}
        onClose={handlePhoneReceiveClose}
        workerId={worker.id}
        previousActiveTicket={previousActiveTicket}
      />
    </div>
  );
};


