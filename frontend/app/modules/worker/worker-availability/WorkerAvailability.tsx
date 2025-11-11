import * as React from "react";

import {
  type CopyWorkerAvailabilityResponse,
  type DayAvailability,
  type SaveWorkerAvailabilityResponse,
  type TimeSlot,
  type TimeSlotPayload,
  useCopyWorkerAvailabilityMutation,
  useDeleteWorkerTimeSlotMutation,
  useSaveWorkerAvailabilityMutation,
  useUpdateWorkerTimeSlotMutation,
  useWorkerAvailabilityQuery,
} from "~/api/worker/availability";

import { AvailabilityCalendar } from "./components/AvailabilityCalendar";
import { CopyAvailabilityButton } from "./components/CopyAvailabilityButton";
import { QuickTemplates } from "./components/QuickTemplates";
import { TimeSlotEditor } from "./components/TimeSlotEditor";
import type { TimeSlotTemplate } from "./components/QuickTemplates";
import type { ValidationError } from "./types";
import {
  buildEmptyDayAvailability,
  calculateTotalHours,
  sortDaysByDate,
  upsertDayAvailability,
  validateTimeSlots,
} from "./utils";

type ValidationErrorMap = Record<string, ValidationError[]>;

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

export interface WorkerAvailabilityProps {
  workerId: string;
}

export const WorkerAvailability: React.FC<WorkerAvailabilityProps> = ({ workerId }) => {
  void workerId;

  const [availability, setAvailability] = React.useState<DayAvailability[]>([]);
  const [selectedDay, setSelectedDay] = React.useState<string | null>(null);
  const [validationErrors, setValidationErrors] = React.useState<ValidationErrorMap>({});
  const [savingDay, setSavingDay] = React.useState<string | null>(null);
  const [removingTimeSlotId, setRemovingTimeSlotId] = React.useState<string | null>(null);
  const [isTemplateApplying, setIsTemplateApplying] = React.useState<boolean>(false);
  const [infoMessage, setInfoMessage] = React.useState<string | null>(null);
  const [errorMessage, setErrorMessage] = React.useState<string | null>(null);

  const availabilityQuery = useWorkerAvailabilityQuery({
    staleTime: 60_000,
  });

  React.useEffect(() => {
    if (!availabilityQuery.data?.availability) {
      return;
    }

    React.startTransition(() => {
      setAvailability(sortDaysByDate(availabilityQuery.data!.availability));
    });
  }, [availabilityQuery.data]);

  const saveAvailabilityMutation = useSaveWorkerAvailabilityMutation();
  const updateTimeSlotMutation = useUpdateWorkerTimeSlotMutation();
  const deleteTimeSlotMutation = useDeleteWorkerTimeSlotMutation();
  const copyAvailabilityMutation = useCopyWorkerAvailabilityMutation();

  const effectiveSelectedDay = React.useMemo(
    () => selectedDay ?? availability[0]?.date ?? null,
    [availability, selectedDay],
  );

  const selectedDayAvailability = React.useMemo<DayAvailability | null>(() => {
    if (!effectiveSelectedDay) {
      return null;
    }

    return availability.find((day) => day.date === effectiveSelectedDay) ?? null;
  }, [availability, effectiveSelectedDay]);

  const selectedDayErrors = effectiveSelectedDay ? validationErrors[effectiveSelectedDay] ?? [] : [];

  const assignValidationErrors = React.useCallback(
    (date: string, errorsForDay: ValidationError[]) => {
      setValidationErrors((current) => ({
        ...current,
        [date]: errorsForDay,
      }));
    },
    [],
  );

  const replaceDay = React.useCallback((nextDay: DayAvailability) => {
    setAvailability((current) => upsertDayAvailability(current, nextDay));
    assignValidationErrors(nextDay.date, []);
  }, [assignValidationErrors]);

  const ensureDayExists = React.useCallback(
    (date: string): DayAvailability => {
      const existing = availability.find((day) => day.date === date);
      if (existing) {
        return existing;
      }

      const empty = buildEmptyDayAvailability(date);
      setAvailability((current) => upsertDayAvailability(current, empty));
      return empty;
    },
    [availability],
  );

  const handleTimeSlotAdd = React.useCallback(
    async (date: string, payload: TimeSlotPayload): Promise<boolean> => {
      const day = ensureDayExists(date);
      const draftId = "__draft_new_slot__";
      const nextTimeSlotsForValidation: TimeSlot[] = [
        ...day.timeSlots,
        {
          id: draftId,
          ...payload,
        },
      ];

      const errors = validateTimeSlots(nextTimeSlotsForValidation).map((error) =>
        error.timeSlotId === draftId ? { ...error, timeSlotId: null } : error,
      );

      if (errors.length > 0) {
        assignValidationErrors(date, errors);
        setErrorMessage("Nie udało się dodać przedziału — popraw błędy walidacji.");
        return false;
      }

      setSavingDay(date);
      setErrorMessage(null);
      setInfoMessage(null);

      try {
        const response = await saveAvailabilityMutation.mutateAsync({
          date,
          timeSlots: [
            ...day.timeSlots.map(({ startTime, endTime }) => ({ startTime, endTime })),
            { startTime: payload.startTime, endTime: payload.endTime },
          ],
        });

        replaceDay(response);
        setInfoMessage("Dostępność została zaktualizowana.");
        return true;
      } catch (error) {
        assignValidationErrors(date, []);
        setErrorMessage(resolveErrorMessage(error));
        return false;
      } finally {
        setSavingDay(null);
      }
    },
    [
      ensureDayExists,
      assignValidationErrors,
      saveAvailabilityMutation,
      replaceDay,
    ],
  );

  const handleTimeSlotUpdate = React.useCallback(
    async (date: string, timeSlotId: string, payload: TimeSlotPayload): Promise<boolean> => {
      const day = ensureDayExists(date);
      const nextTimeSlots = day.timeSlots.map((slot) =>
        slot.id === timeSlotId ? { ...slot, ...payload } : slot,
      );

      const errors = validateTimeSlots(nextTimeSlots);

      if (errors.length > 0) {
        assignValidationErrors(date, errors);
        setErrorMessage("Nie udało się zapisać zmian — popraw błędy walidacji.");
        return false;
      }

      setSavingDay(date);
      setErrorMessage(null);
      setInfoMessage(null);

      try {
        await updateTimeSlotMutation.mutateAsync({
          date,
          timeSlotId,
          ...payload,
        });

        const updatedDay: DayAvailability = {
          ...day,
          timeSlots: nextTimeSlots,
          totalHours: calculateTotalHours(nextTimeSlots),
        };

        replaceDay(updatedDay);
        setInfoMessage("Przedział został zaktualizowany.");
        return true;
      } catch (error) {
        setErrorMessage(resolveErrorMessage(error));
        return false;
      } finally {
        setSavingDay(null);
      }
    },
    [ensureDayExists, assignValidationErrors, updateTimeSlotMutation, replaceDay],
  );

  const handleTimeSlotRemove = React.useCallback(
    async (date: string, timeSlotId: string): Promise<boolean> => {
      const day = ensureDayExists(date);

      setRemovingTimeSlotId(timeSlotId);
      setErrorMessage(null);
      setInfoMessage(null);

      try {
        await deleteTimeSlotMutation.mutateAsync({
          date,
          timeSlotId,
        });

        const nextTimeSlots = day.timeSlots.filter((slot) => slot.id !== timeSlotId);
        const updatedDay: DayAvailability = {
          ...day,
          timeSlots: nextTimeSlots,
          totalHours: calculateTotalHours(nextTimeSlots),
        };

        replaceDay(updatedDay);
        setInfoMessage("Przedział został usunięty.");
        return true;
      } catch (error) {
        setErrorMessage(resolveErrorMessage(error));
        return false;
      } finally {
        setRemovingTimeSlotId(null);
      }
    },
    [ensureDayExists, deleteTimeSlotMutation, replaceDay],
  );

  const handleCopyAvailability = React.useCallback(
    async (sourceDate: string, targetDates: string[], overwrite: boolean) => {
      setErrorMessage(null);
      setInfoMessage(null);

      try {
        const response: CopyWorkerAvailabilityResponse = await copyAvailabilityMutation.mutateAsync(
          {
            sourceDate,
            targetDates,
            overwrite,
          },
        );

        if (response.copied.length > 0) {
          setAvailability((current) => {
            let next = current;
            response.copied.forEach((day) => {
              next = upsertDayAvailability(next, day);
              assignValidationErrors(day.date, []);
            });
            return next;
          });
          setInfoMessage("Dostępność została skopiowana.");
        }

        if (response.skipped.length > 0) {
          setErrorMessage(
            `Nie zaktualizowano dni: ${response.skipped.join(", ")}. Włącz nadpisywanie, aby je zmienić.`,
          );
        }
      } catch (error) {
        setInfoMessage(null);
        setErrorMessage(resolveErrorMessage(error));
      }
    },
    [copyAvailabilityMutation, assignValidationErrors],
  );

  const handleTemplateApply = React.useCallback(
    async (template: TimeSlotTemplate, targetDates: string[]) => {
      if (targetDates.length === 0) {
        return;
      }

      setIsTemplateApplying(true);
      setErrorMessage(null);
      setInfoMessage(null);

      try {
        for (const date of targetDates) {
          const errors = validateTimeSlots(
            template.timeSlots.map((slot, index) => ({
              id: `template-${index}`,
              startTime: slot.startTime,
              endTime: slot.endTime,
            })),
          );

          if (errors.length > 0) {
            assignValidationErrors(date, errors);
            throw new Error("Szablon zawiera nieprawidłowe przedziały czasowe.");
          }

          const response: SaveWorkerAvailabilityResponse = await saveAvailabilityMutation.mutateAsync(
            {
              date,
              timeSlots: template.timeSlots,
            },
          );

          replaceDay(response);
        }

        setInfoMessage("Szablon został zastosowany.");
      } catch (error) {
        setErrorMessage(resolveErrorMessage(error));
      } finally {
        setIsTemplateApplying(false);
      }
    },
    [assignValidationErrors, saveAvailabilityMutation, replaceDay],
  );

  const allDates = React.useMemo(() => availability.map((day) => day.date), [availability]);
  const isLoading = availabilityQuery.isLoading;
  const hasError = availabilityQuery.isError;
  const isSelectedDaySaving =
    effectiveSelectedDay !== null &&
    savingDay === effectiveSelectedDay &&
    (saveAvailabilityMutation.isLoading || updateTimeSlotMutation.isLoading);
  const isRemovingTimeSlot =
    removingTimeSlotId !== null && deleteTimeSlotMutation.isLoading;

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900 dark:text-slate-100">
          Dostępność pracownika
        </h1>
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Zarządzaj swoją dostępnością w najbliższych 7 dniach. Dodawaj i edytuj przedziały
          czasowe, kopiuj dostępność pomiędzy dniami lub korzystaj z szybkich szablonów.
        </p>
      </header>

      <section className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              Nadchodzący tydzień
            </h2>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              Kliknij dzień, aby edytować szczegóły dostępności.
            </p>
          </div>

          <CopyAvailabilityButton
            days={availability}
            onCopy={handleCopyAvailability}
            isCopying={copyAvailabilityMutation.isLoading}
          />
        </div>

        <AvailabilityCalendar
          availability={availability}
          selectedDay={effectiveSelectedDay}
          onDaySelect={setSelectedDay}
          isLoading={isLoading}
        />
      </section>

      {effectiveSelectedDay && selectedDayAvailability ? (
        <TimeSlotEditor
          date={effectiveSelectedDay}
          timeSlots={selectedDayAvailability.timeSlots}
          validationErrors={selectedDayErrors}
          onTimeSlotAdd={(payload) => handleTimeSlotAdd(effectiveSelectedDay, payload)}
          onTimeSlotUpdate={(timeSlotId, payload) =>
            handleTimeSlotUpdate(effectiveSelectedDay, timeSlotId, payload)
          }
          onTimeSlotRemove={(timeSlotId) => handleTimeSlotRemove(effectiveSelectedDay, timeSlotId)}
          isSaving={isSelectedDaySaving}
          isRemoving={isRemovingTimeSlot}
        />
      ) : (
        <p className="rounded-md border border-dashed border-slate-300 p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
          Wybierz dzień, aby rozpocząć edycję dostępności.
        </p>
      )}

      <QuickTemplates
        selectedDate={effectiveSelectedDay}
        allDates={allDates}
        onTemplateApply={handleTemplateApply}
        isApplying={isTemplateApplying}
      />

      {infoMessage ? (
        <div className="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-900/40 dark:text-indigo-200">
          {infoMessage}
        </div>
      ) : null}

      {hasError ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300">
          <p className="font-semibold">Nie udało się pobrać dostępności.</p>
          <div className="mt-2 flex gap-2">
            <button
              type="button"
              className="rounded-md border border-red-200 bg-white px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300 dark:border-red-500/40 dark:bg-red-900 dark:text-red-200"
              onClick={() => availabilityQuery.refetch()}
            >
              Spróbuj ponownie
            </button>
          </div>
        </div>
      ) : null}

      {errorMessage ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300">
          {errorMessage}
        </div>
      ) : null}
    </div>
  );
};


