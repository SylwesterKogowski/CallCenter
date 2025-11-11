import * as React from "react";

import type { TimeSlot, TimeSlotPayload } from "~/api/worker/availability";

import type { ValidationError } from "../types";
import { TimeSlotForm } from "./TimeSlotForm";
import { TimeSlotList } from "./TimeSlotList";
import { AvailabilityTimeline } from "./AvailabilityTimeline";

export interface TimeSlotEditorProps {
  date: string;
  timeSlots: TimeSlot[];
  validationErrors: ValidationError[];
  onTimeSlotAdd: (timeSlot: TimeSlotPayload) => Promise<boolean> | boolean;
  onTimeSlotUpdate: (timeSlotId: string, timeSlot: TimeSlotPayload) => Promise<boolean> | boolean;
  onTimeSlotRemove: (timeSlotId: string) => Promise<boolean> | boolean;
  isSaving?: boolean;
  isRemoving?: boolean;
}

type EditorMode = "idle" | "creating" | "editing";

export const TimeSlotEditor: React.FC<TimeSlotEditorProps> = ({
  date,
  timeSlots,
  validationErrors,
  onTimeSlotAdd,
  onTimeSlotUpdate,
  onTimeSlotRemove,
  isSaving = false,
  isRemoving = false,
}) => {
  const [mode, setMode] = React.useState<EditorMode>("idle");
  const [editingTimeSlotId, setEditingTimeSlotId] = React.useState<string | null>(null);

  const editingTimeSlot = React.useMemo(
    () => timeSlots.find((slot) => slot.id === editingTimeSlotId) ?? null,
    [editingTimeSlotId, timeSlots],
  );

  const handleAddClick = () => {
    setMode("creating");
    setEditingTimeSlotId(null);
  };

  const handleEditClick = (timeSlotId: string) => {
    setMode("editing");
    setEditingTimeSlotId(timeSlotId);
  };

  const handleCancel = () => {
    setMode("idle");
    setEditingTimeSlotId(null);
  };

  const formErrors = React.useMemo(() => {
    if (mode === "creating") {
      return validationErrors.filter((error) => error.timeSlotId === null);
    }
    if (mode === "editing" && editingTimeSlotId) {
      return validationErrors.filter((error) => error.timeSlotId === editingTimeSlotId || error.timeSlotId === null);
    }
    return [];
  }, [editingTimeSlotId, mode, validationErrors]);

  const exitEditor = React.useCallback(() => {
    setMode("idle");
    setEditingTimeSlotId(null);
  }, []);

  const handleFormSave = React.useCallback(
    async (payload: TimeSlotPayload) => {
      if (mode === "creating") {
        const success = await onTimeSlotAdd(payload);
        if (success) {
          exitEditor();
        }
        return;
      }

      if (mode === "editing" && editingTimeSlotId) {
        const success = await onTimeSlotUpdate(editingTimeSlotId, payload);
        if (success) {
          exitEditor();
        }
      }
    },
    [editingTimeSlotId, exitEditor, mode, onTimeSlotAdd, onTimeSlotUpdate],
  );

  const handleRemove = React.useCallback(
    async (timeSlotId: string) => {
      const shouldRemove = window.confirm("Czy na pewno chcesz usunąć ten przedział czasowy?");
      if (!shouldRemove) {
        return;
      }

      const success = await onTimeSlotRemove(timeSlotId);
      if (success && editingTimeSlotId === timeSlotId) {
        exitEditor();
      }
    },
    [editingTimeSlotId, exitEditor, onTimeSlotRemove],
  );

  const formKey = mode === "editing" ? editingTimeSlotId ?? "edit" : "create";

  return (
    <section className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
      <header className="flex flex-col gap-1">
        <p className="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-300">
          Edycja dostępności
        </p>
        <h2 className="text-xl font-semibold text-slate-900 dark:text-slate-100">
          {date} — {timeSlots.length} przedziałów
        </h2>
      </header>

      <TimeSlotList
        timeSlots={timeSlots}
        validationErrors={validationErrors}
        onEdit={handleEditClick}
        onRemove={handleRemove}
        isRemoving={isRemoving}
      />

      <AvailabilityTimeline timeSlots={timeSlots} validationErrors={validationErrors} />

      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          onClick={handleAddClick}
          className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
          disabled={mode === "creating" && !isSaving}
        >
          Dodaj przedział
        </button>

        {mode === "idle" ? (
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Wybierz istniejący przedział, aby go edytować lub usuń, jeśli nie jest już potrzebny.
          </p>
        ) : null}
      </div>

      {mode !== "idle" ? (
        <TimeSlotForm
          key={formKey}
          timeSlot={
            mode === "editing" && editingTimeSlot
              ? { startTime: editingTimeSlot.startTime, endTime: editingTimeSlot.endTime }
              : null
          }
          onSave={handleFormSave}
          onCancel={handleCancel}
          errors={formErrors}
          isSubmitting={isSaving}
        />
      ) : null}
    </section>
  );
};


