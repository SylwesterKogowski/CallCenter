import * as React from "react";

import type { TicketStatus } from "~/api/types";
import {
  type CreateWorkerTicketResponse,
  type StartPhoneCallResponse,
  type WorkerTicket,
  type WorkerTicketSearchResult,
  useAddWorkerTicketNoteMutation,
  useCreateWorkerTicketMutation,
  useEndPhoneCallMutation,
  useStartPhoneCallMutation,
  useWorkerClientSearchQuery,
  useWorkerTicketSearchQuery,
} from "~/api/worker/phone";

import { CallTimer } from "./CallTimer";
import { EndCallButton } from "./EndCallButton";
import type { NewTicketData } from "./TicketCreateForm";
import { TicketCreateForm } from "./TicketCreateForm";
import { TicketDisplay } from "./TicketDisplay";
import { TicketNotesEditor } from "./TicketNotesEditor";
import { TicketSearch } from "./TicketSearch";

interface PhoneReceiveModalProps {
  isOpen: boolean;
  onClose: (completed: boolean) => void;
  workerId: string;
  previousActiveTicket: WorkerTicket | null;
}

const resolveErrorMessage = (error: unknown): string => {
  if (!error) {
    return "Wystąpił nieznany błąd.";
  }
  if (error instanceof Error) {
    return error.message;
  }
  if (typeof error === "string") {
    return error;
  }
  return "Wystąpił nieznany błąd.";
};

export const PhoneReceiveModal: React.FC<PhoneReceiveModalProps> = ({
  isOpen,
  onClose,
  workerId,
  previousActiveTicket,
}) => {
  const modalRef = React.useRef<HTMLDivElement>(null);
  const [callData, setCallData] = React.useState<StartPhoneCallResponse | null>(null);
  const [callStartTime, setCallStartTime] = React.useState<Date | null>(null);
  const [callDuration, setCallDuration] = React.useState(0);
  const [selectedTicket, setSelectedTicket] = React.useState<WorkerTicket | null>(null);
  const [notes, setNotes] = React.useState("");
  const [notesError, setNotesError] = React.useState<string | null>(null);
  const [endCallError, setEndCallError] = React.useState<string | null>(null);
  const [activeView, setActiveView] = React.useState<"search" | "create">("search");
  const [searchQuery, setSearchQuery] = React.useState("");
  const [categoryFilter, setCategoryFilter] = React.useState("");
  const [statusFilter, setStatusFilter] = React.useState<TicketStatus | "">("");
  const [ticketSearchError, setTicketSearchError] = React.useState<string | null>(null);
  const [ticketCreateError, setTicketCreateError] = React.useState<string | null>(null);
  const [clientSearchTerm, setClientSearchTerm] = React.useState("");

  const resetState = React.useCallback(() => {
    setCallData(null);
    setCallStartTime(null);
    setCallDuration(0);
    setSelectedTicket(null);
    setNotes("");
    setNotesError(null);
    setEndCallError(null);
    setActiveView("search");
    setSearchQuery("");
    setCategoryFilter("");
    setStatusFilter("");
    setTicketSearchError(null);
    setTicketCreateError(null);
    setClientSearchTerm("");
  }, []);

  React.useEffect(() => {
    if (!isOpen) {
      return;
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        handleClose(false);
      }
    };

    document.addEventListener("keydown", handleKeyDown);

    return () => {
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [isOpen]);

  React.useEffect(() => {
    if (!isOpen) {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [isOpen]);

  React.useEffect(() => {
    if (!isOpen || !modalRef.current) {
      return;
    }

    modalRef.current.focus();
  }, [isOpen]);

  const startPhoneCallMutation = useStartPhoneCallMutation({
    onSuccess: (response) => {
      setCallData(response);
      setCallStartTime(new Date(response.startTime ?? new Date().toISOString()));
      setEndCallError(null);
    },
    onError: (error) => {
      setCallData(null);
      setCallStartTime(null);
      setEndCallError(resolveErrorMessage(error));
    },
  });

  React.useEffect(() => {
    if (!isOpen) {
      startPhoneCallMutation.reset();
      return;
    }

    if (!startPhoneCallMutation.isIdle || callData) {
      return;
    }

    startPhoneCallMutation.mutate({
      workerId,
    });
  }, [callData, isOpen, resetState, startPhoneCallMutation, workerId]);

  const ticketSearchQueryResult = useWorkerTicketSearchQuery(
    {
      query: searchQuery.trim() || undefined,
      categoryId: categoryFilter.trim() || undefined,
      status: statusFilter || undefined,
      limit: 10,
    },
    {
      enabled: isOpen,
      staleTime: 30_000,
      suspense: false,
      retry: 1,
      onError: (error) => {
        setTicketSearchError(resolveErrorMessage(error));
      },
      onSuccess: () => {
        setTicketSearchError(null);
      },
    },
  );

  const clientSearchQueryResult = useWorkerClientSearchQuery(
    {
      query: clientSearchTerm.trim() || undefined,
      limit: 10,
    },
    {
      enabled: isOpen && clientSearchTerm.trim().length > 0,
      staleTime: 30_000,
    },
  );

  const createTicketMutation = useCreateWorkerTicketMutation({
    onSuccess: (response: CreateWorkerTicketResponse) => {
      setSelectedTicket(response.ticket);
      setActiveView("search");
      setTicketCreateError(null);
      ticketSearchQueryResult.refetch().catch(() => {
        // ignore
      });
    },
    onError: (error) => {
      setTicketCreateError(resolveErrorMessage(error));
    },
  });

  const addNoteMutation = useAddWorkerTicketNoteMutation({
    onSuccess: () => {
      setNotesError(null);
    },
    onError: (error) => {
      setNotesError(resolveErrorMessage(error));
    },
  });

  const endCallMutation = useEndPhoneCallMutation({
    onSuccess: () => {
      handleClose(true);
    },
    onError: (error) => {
      setEndCallError(resolveErrorMessage(error));
    },
  });

  const handleTicketSelect = React.useCallback((ticket: WorkerTicketSearchResult) => {
    setSelectedTicket({
      ...ticket,
    });
  }, []);

  const handleTicketCreate = React.useCallback(
    async (payload: NewTicketData) => {
      await createTicketMutation.mutateAsync({
        title: payload.title,
        categoryId: payload.categoryId,
        clientId: payload.clientId ?? null,
        clientData: payload.clientData ?? null,
      });
    },
    [createTicketMutation],
  );

  const handleNoteSave = React.useCallback(async () => {
    if (!selectedTicket) {
      setNotesError("Wybierz ticket przed zapisaniem notatki.");
      return;
    }

    if (!notes.trim()) {
      setNotesError("Notatka nie może być pusta.");
      return;
    }

    await addNoteMutation.mutateAsync({
      ticketId: selectedTicket.id,
      content: notes.trim(),
    });
  }, [addNoteMutation, notes, selectedTicket]);

  const handleEndCall = React.useCallback(() => {
    if (!callData || !callStartTime) {
      setEndCallError("Połączenie jeszcze się nie rozpoczęło.");
      return;
    }

    const end = new Date();
    const duration = Math.max(callDuration, Math.floor((end.getTime() - callStartTime.getTime()) / 1000));

    endCallMutation.mutate({
      callId: callData.callId,
      ticketId: selectedTicket?.id ?? null,
      duration,
      notes: notes.trim(),
      startTime: callStartTime.toISOString(),
      endTime: end.toISOString(),
    });
  }, [callData, callDuration, callStartTime, endCallMutation, notes, selectedTicket]);

  const handleClose = React.useCallback(
    (completed: boolean) => {
      resetState();
      onClose(completed);
    },
    [onClose, resetState],
  );

  const handleCancel = React.useCallback(() => {
    handleClose(false);
  }, [handleClose]);

  if (!isOpen) {
    return null;
  }

  const isLoadingCall = startPhoneCallMutation.isLoading && !callData;
  const pausedTickets = callData?.pausedTickets ?? [];

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
      role="presentation"
      onClick={() => handleClose(false)}
    >
      <div
        ref={modalRef}
        className="relative flex max-h-[95vh] w-full max-w-5xl flex-col gap-6 overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200 focus:outline-none dark:bg-slate-900 dark:ring-slate-700"
        role="dialog"
        aria-modal="true"
        aria-label="Obsługa odbierania telefonu"
        tabIndex={-1}
        onClick={(event) => event.stopPropagation()}
      >
        <button
          type="button"
          onClick={handleCancel}
          className="absolute right-4 top-4 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
        >
          Zamknij
        </button>

        <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-col gap-1">
            <h1 className="text-2xl font-semibold text-slate-900 dark:text-slate-100">
              Odbieranie telefonu
            </h1>
            <p className="text-sm text-slate-600 dark:text-slate-300">
              Zarejestruj połączenie i przypisz je do odpowiedniego ticketa. Po zakończeniu połączenia dane zostaną zapisane automatycznie.
            </p>
          </div>
          <CallTimer
            startTime={callStartTime}
            isActive={Boolean(callData)}
            onDurationChange={setCallDuration}
          />
        </header>

        {isLoadingCall ? (
          <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
            Inicjalizuję połączenie…
          </div>
        ) : null}

        {endCallError ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-200">
            {endCallError}
          </div>
        ) : null}

        {pausedTickets.length > 0 ? (
          <section className="flex flex-col gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-900/40 dark:text-amber-100">
            <h2 className="text-base font-semibold">Wstrzymane tickety</h2>
            <ul className="list-disc pl-4">
              {pausedTickets.map((ticket) => (
                <li key={ticket.ticketId}>
                  Ticket {ticket.ticketId}: {ticket.previousStatus} → {ticket.newStatus}
                </li>
              ))}
            </ul>
            {previousActiveTicket ? (
              <p className="text-xs opacity-80">
                Po zakończeniu połączenia, jeśli nie wybierzesz nowego ticketa, zostanie wznowiony ticket {previousActiveTicket.title}.
              </p>
            ) : null}
          </section>
        ) : null}

        <TicketDisplay
          ticket={selectedTicket}
          onTicketChange={() => setActiveView("search")}
          onTicketCreate={() => setActiveView("create")}
        />

        <nav className="flex gap-2">
          <button
            type="button"
            onClick={() => setActiveView("search")}
            className={[
              "rounded-xl px-4 py-2 text-sm font-semibold transition",
              activeView === "search"
                ? "bg-blue-600 text-white"
                : "bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300",
            ].join(" ")}
          >
            Wyszukaj ticket
          </button>
          <button
            type="button"
            onClick={() => setActiveView("create")}
            className={[
              "rounded-xl px-4 py-2 text-sm font-semibold transition",
              activeView === "create"
                ? "bg-emerald-500 text-white"
                : "bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300",
            ].join(" ")}
          >
            Utwórz ticket
          </button>
        </nav>

        {activeView === "search" ? (
          <TicketSearch
            query={searchQuery}
            categoryId={categoryFilter}
            status={statusFilter}
            onQueryChange={setSearchQuery}
            onCategoryChange={setCategoryFilter}
            onStatusChange={setStatusFilter}
            results={ticketSearchQueryResult.data?.tickets ?? []}
            isLoading={ticketSearchQueryResult.isLoading}
            errorMessage={ticketSearchError}
            onRetry={() => ticketSearchQueryResult.refetch()}
            onTicketSelect={handleTicketSelect}
            excludeTicketId={selectedTicket?.id}
          />
        ) : (
          <TicketCreateForm
            onTicketCreate={handleTicketCreate}
            onCancel={() => setActiveView("search")}
            workerId={workerId}
            clientSearchResults={clientSearchQueryResult.data?.clients ?? []}
            onClientSearch={setClientSearchTerm}
            isSubmitting={createTicketMutation.isLoading}
            errorMessage={ticketCreateError}
          />
        )}

        <TicketNotesEditor
          notes={notes}
          onChange={setNotes}
          onSave={handleNoteSave}
          disabled={!selectedTicket}
          isSaving={addNoteMutation.isLoading}
        />

        {notesError ? (
          <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-200">
            {notesError}
          </div>
        ) : null}

        <div className="flex flex-col gap-3">
          <EndCallButton
            onEndCall={handleEndCall}
            isLoading={endCallMutation.isLoading}
            callDuration={callDuration}
            hasSelectedTicket={Boolean(selectedTicket)}
            disabled={!callData}
          />
          <button
            type="button"
            onClick={handleCancel}
            className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-400 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            Anuluj połączenie
          </button>
        </div>
      </div>
    </div>
  );
};


