import * as React from "react";
import { useQueryClient } from "@tanstack/react-query";

import type { TicketStatus } from "~/api/types";
import {
  type ScheduleDay,
  type ScheduleTicket,
  useAddScheduleTicketNoteMutation,
  useAddTicketTimeMutation,
  useUpdateTicketStatusMutation,
  useWorkerScheduleQuery,
  useWorkerWorkStatusQuery,
  workerScheduleKey,
  workerWorkStatusKey,
} from "~/api/worker/schedule";

import { AnswerPhoneButton } from "./components/AnswerPhoneButton";
import { ActiveTicketSection } from "./components/ActiveTicketSection";
import { ScheduleCalendar } from "./components/ScheduleCalendar";
import { WorkerScheduleSSEConnection } from "./components/SSEConnection";
import { TicketList } from "./components/TicketList";
import { TimeTracker } from "./components/TimeTracker";
import { WorkStatusBar } from "./components/WorkStatusBar";

const resolveErrorMessage = (error: unknown): string => {
  if (!error) {
    return "Wystąpił nieoczekiwany błąd.";
  }
  if (error instanceof Error) {
    return error.message;
  }
  if (typeof error === "string") {
    return error;
  }
  return "Wystąpił nieoczekiwany błąd.";
};

const formatMinutes = (totalMinutes: number): string => {
  if (!Number.isFinite(totalMinutes) || totalMinutes <= 0) {
    return "0 min";
  }

  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  if (hours > 0) {
    return minutes > 0 ? `${hours}h ${minutes} min` : `${hours}h`;
  }

  return `${minutes} min`;
};

const findScheduleDay = (schedule: ScheduleDay[], date: string | null): ScheduleDay | null => {
  if (!date) {
    return null;
  }
  return schedule.find((day) => day.date === date) ?? null;
};

const toInfoText = (schedule: ScheduleDay[]): string => {
  if (schedule.length === 0) {
    return "Nie masz zaplanowanych ticketów.";
  }
  if (schedule.length === 1) {
    return "Wyświetlamy zaplanowane tickety na najbliższy dzień Twojej dostępności.";
  }
  return "Wybierz dzień, aby zobaczyć zaplanowane tickety.";
};

export interface WorkerScheduleProps {
  workerId: string;
}

export const WorkerSchedule: React.FC<WorkerScheduleProps> = ({ workerId }) => {
  const queryClient = useQueryClient();

  const [selectedDate, setSelectedDate] = React.useState<string | null>(null);
  const [infoMessage, setInfoMessage] = React.useState<string | null>(null);
  const [errorMessage, setErrorMessage] = React.useState<string | null>(null);
  const [connectionError, setConnectionError] = React.useState<string | null>(null);
  const [pendingTicketId, setPendingTicketId] = React.useState<string | null>(null);
  const [addingNoteTicketId, setAddingNoteTicketId] = React.useState<string | null>(null);
  const [addingTimeTicketId, setAddingTimeTicketId] = React.useState<string | null>(null);
  const [trackingStart, setTrackingStart] = React.useState<number | null>(null);

  const scheduleQuery = useWorkerScheduleQuery({
    staleTime: 30_000,
  });
  const workStatusQuery = useWorkerWorkStatusQuery({
    staleTime: 30_000,
  });
  const updateTicketStatusMutation = useUpdateTicketStatusMutation();
  const addTicketTimeMutation = useAddTicketTimeMutation();
  const addTicketNoteMutation = useAddScheduleTicketNoteMutation();

  const schedule = scheduleQuery.data?.schedule ?? [];
  const activeTicket = scheduleQuery.data?.activeTicket ?? null;

  const todayStats = workStatusQuery.data?.todayStats ?? null;
  const workStatus = workStatusQuery.data?.status ?? null;

  const selectedDay = React.useMemo(
    () => findScheduleDay(schedule, selectedDate),
    [schedule, selectedDate],
  );

  React.useEffect(() => {
    React.startTransition(() => {
      if (schedule.length === 0) {
        setSelectedDate(null);
        return;
      }

      setSelectedDate((current) => {
        if (!current) {
          return schedule[0]?.date ?? null;
        }

        const stillExists = schedule.some((day) => day.date === current);
        return stillExists ? current : schedule[0]?.date ?? null;
      });
    });
  }, [schedule]);

  React.useEffect(() => {
    React.startTransition(() => {
      if (activeTicket?.status === "in_progress") {
        setTrackingStart(Date.now());
      } else {
        setTrackingStart(null);
      }
    });
  }, [activeTicket?.id, activeTicket?.status]);

  const invalidateSchedule = React.useCallback(() => {
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: workerScheduleKey }),
      queryClient.invalidateQueries({ queryKey: workerWorkStatusKey }),
    ]);
  }, [queryClient]);

  const handleTicketStatusChange = React.useCallback(
    async (ticketId: string, status: TicketStatus) => {
      setPendingTicketId(ticketId);
      setErrorMessage(null);
      setInfoMessage(null);
      try {
        await updateTicketStatusMutation.mutateAsync({
          ticketId,
          status,
        });
        await invalidateSchedule();
        setInfoMessage("Status ticketa został zaktualizowany.");
      } catch (error) {
        setErrorMessage(resolveErrorMessage(error));
      } finally {
        setPendingTicketId(null);
      }
    },
    [updateTicketStatusMutation, invalidateSchedule],
  );

  const handleTicketSelect = React.useCallback(
    async (ticket: ScheduleTicket) => {
      if (activeTicket?.id === ticket.id) {
        return;
      }

      setPendingTicketId(ticket.id);
      setErrorMessage(null);
      setInfoMessage(null);

      try {
        if (activeTicket && activeTicket.status === "in_progress") {
          await updateTicketStatusMutation.mutateAsync({
            ticketId: activeTicket.id,
            status: "waiting",
          });
        }

        await updateTicketStatusMutation.mutateAsync({
          ticketId: ticket.id,
          status: "in_progress",
        });

        await invalidateSchedule();
        setInfoMessage(`Ticket "${ticket.title}" został ustawiony jako aktywny.`);
      } catch (error) {
        setErrorMessage(resolveErrorMessage(error));
      } finally {
        setPendingTicketId(null);
      }
    },
    [activeTicket, updateTicketStatusMutation, invalidateSchedule],
  );

  const handleStopWork = React.useCallback(async () => {
    if (!activeTicket) {
      return;
    }
    await handleTicketStatusChange(activeTicket.id, "waiting");
  }, [activeTicket, handleTicketStatusChange]);

  const handleTimeAdd = React.useCallback(
    async (ticketId: string, minutes: number, type: "phone_call" | "work") => {
      if (!Number.isFinite(minutes) || minutes <= 0) {
        setErrorMessage("Podaj liczbę minut większą od zera.");
        return false;
      }

      setAddingTimeTicketId(ticketId);
      setErrorMessage(null);
      setInfoMessage(null);
      try {
        await addTicketTimeMutation.mutateAsync({
          ticketId,
          minutes,
          type,
        });
        await invalidateSchedule();
        setInfoMessage("Czas został dodany do ticketa.");
        return true;
      } catch (error) {
        setErrorMessage(resolveErrorMessage(error));
        return false;
      } finally {
        setAddingTimeTicketId(null);
      }
    },
    [addTicketTimeMutation, invalidateSchedule],
  );

  const handleNoteAdd = React.useCallback(
    async (ticketId: string, content: string) => {
      if (content.trim().length === 0) {
        setErrorMessage("Notatka nie może być pusta.");
        return false;
      }
      setAddingNoteTicketId(ticketId);
      setErrorMessage(null);
      setInfoMessage(null);

      try {
        await addTicketNoteMutation.mutateAsync({
          ticketId,
          content,
        });
        await invalidateSchedule();
        setInfoMessage("Notatka została dodana.");
        return true;
      } catch (error) {
        setErrorMessage(resolveErrorMessage(error));
        return false;
      } finally {
        setAddingNoteTicketId(null);
      }
    },
    [addTicketNoteMutation, invalidateSchedule],
  );

  const handleSseUpdate = React.useCallback(() => {
    setConnectionError(null);
    void invalidateSchedule().catch((error) => {
      setErrorMessage(resolveErrorMessage(error));
    });
  }, [invalidateSchedule]);

  const handleSseError = React.useCallback((error: Error) => {
    setConnectionError(error.message || "Połączenie SSE zostało przerwane.");
  }, []);

  const onPhoneCompleted = React.useCallback(() => {
    void invalidateSchedule().catch((error) => {
      setErrorMessage(resolveErrorMessage(error));
    });
  }, [invalidateSchedule]);

  const infoText = React.useMemo(() => toInfoText(schedule), [schedule]);

  return (
    <div className="flex flex-col gap-6" data-testid="worker-schedule">
      <WorkerScheduleSSEConnection
        workerId={workerId}
        onScheduleUpdate={handleSseUpdate}
        onError={handleSseError}
      />

      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900 dark:text-slate-100">
          Twój grafik
        </h1>
        <p className="text-sm text-slate-600 dark:text-slate-300">{infoText}</p>
      </header>

      <WorkStatusBar
        workStatus={workStatus}
        todayStats={todayStats}
        isLoading={workStatusQuery.isLoading}
        isError={workStatusQuery.isError}
      />

      <section className="grid gap-6 lg:grid-cols-[2fr,3fr]">
        <ScheduleCalendar
          schedule={schedule}
          selectedDate={selectedDate}
          onSelectDate={setSelectedDate}
          activeTicketId={activeTicket?.id ?? null}
          isLoading={scheduleQuery.isLoading}
        />

        <TicketList
          tickets={selectedDay?.tickets ?? []}
          activeTicketId={activeTicket?.id ?? null}
          onTicketSelect={handleTicketSelect}
          onTicketPause={(ticketId) => handleTicketStatusChange(ticketId, "waiting")}
          pendingTicketId={pendingTicketId}
          isLoading={scheduleQuery.isLoading}
        />
      </section>

      <section className="grid gap-6 lg:grid-cols-[3fr,2fr]">
        <ActiveTicketSection
          ticket={activeTicket}
          onStopWork={handleStopWork}
          onNoteAdd={handleNoteAdd}
          isAddingNote={addingNoteTicketId === activeTicket?.id}
          isChangingStatus={pendingTicketId === activeTicket?.id}
          formatMinutes={formatMinutes}
        />

        <TimeTracker
          ticket={activeTicket}
          trackingStart={trackingStart}
          onTimeAdd={handleTimeAdd}
          isAddingTime={addingTimeTicketId === activeTicket?.id}
          formatMinutes={formatMinutes}
        />
      </section>

      <AnswerPhoneButton workerId={workerId} previousActiveTicket={activeTicket} onCompleted={onPhoneCompleted} />

      {connectionError ? (
        <div className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-900/40 dark:text-amber-100">
          {connectionError}{" "}
          <button
            type="button"
            className="font-medium underline decoration-dotted underline-offset-4"
            onClick={() => handleSseUpdate()}
          >
            Odśwież dane
          </button>
        </div>
      ) : null}

      {scheduleQuery.isError ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300">
          <p className="font-semibold">Nie udało się pobrać grafika.</p>
          <button
            type="button"
            className="mt-2 rounded-md border border-red-200 bg-white px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300 dark:border-red-500/40 dark:bg-red-900 dark:text-red-200"
            onClick={() => scheduleQuery.refetch()}
          >
            Spróbuj ponownie
          </button>
        </div>
      ) : null}

      {errorMessage ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300">
          {errorMessage}
        </div>
      ) : null}

      {infoMessage ? (
        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-200">
          {infoMessage}
        </div>
      ) : null}
    </div>
  );
};


