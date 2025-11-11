import * as React from "react";

import type { ScheduleTicket } from "~/api/worker/schedule";
import type { WorkerTicket } from "~/api/worker/phone";

import { WorkerPhoneReceive } from "../../worker-phone-receive/WorkerPhoneReceive";

const fallbackClient = {
  id: "unknown-client",
  name: "Nieznany klient",
};

const mapScheduleTicketToWorkerTicket = (
  ticket: ScheduleTicket,
): WorkerTicket | null => {
  if (!ticket) {
    return null;
  }

  const client = ticket.client ?? fallbackClient;

  const createdAt = ticket.scheduledDate
    ? new Date(`${ticket.scheduledDate}T00:00:00Z`).toISOString()
    : new Date().toISOString();

  return {
    id: ticket.id,
    title: ticket.title,
    category: ticket.category,
    status: ticket.status,
    client,
    createdAt,
    timeSpent: ticket.timeSpent,
  };
};

export interface AnswerPhoneButtonProps {
  workerId: string;
  previousActiveTicket?: ScheduleTicket | null;
  onCompleted?: () => void;
}

export const AnswerPhoneButton: React.FC<AnswerPhoneButtonProps> = ({
  workerId,
  previousActiveTicket,
  onCompleted,
}) => {
  const workerTicket = React.useMemo<WorkerTicket | null>(() => {
    if (!previousActiveTicket) {
      return null;
    }
    try {
      return mapScheduleTicketToWorkerTicket(previousActiveTicket);
    } catch {
      return null;
    }
  }, [previousActiveTicket]);

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
      <div className="mb-4 flex flex-col gap-2">
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Masz telefon od klienta?
        </h2>
        <p className="text-sm text-slate-600 dark:text-slate-400">
          W każdej chwili możesz rozpocząć obsługę połączenia. System automatycznie wstrzyma
          aktualny ticket i poprosi o przypisanie rozmowy do właściwego ticketa.
        </p>
      </div>
      <WorkerPhoneReceive
        workerId={workerId}
        previousActiveTicket={workerTicket}
        onCompleted={onCompleted}
      />
    </section>
  );
};


