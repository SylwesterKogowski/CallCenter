import * as React from "react";

interface TicketNotesEditorProps {
  notes: string;
  onChange: (value: string) => void;
  onSave: () => Promise<void> | void;
  disabled?: boolean;
  isSaving?: boolean;
}

export const TicketNotesEditor: React.FC<TicketNotesEditorProps> = ({
  notes,
  onChange,
  onSave,
  disabled = false,
  isSaving = false,
}) => {
  const handleSave = React.useCallback(async () => {
    if (disabled) {
      return;
    }

    await onSave();
  }, [disabled, onSave]);

  const isSaveDisabled = disabled || isSaving || notes.trim().length === 0;

  return (
    <div className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/50">
      <div className="flex flex-col gap-1">
        <span className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Notatki do rozmowy
        </span>
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Notatki są zapisywane w tickecie i będą widoczne dla zespołu.
        </p>
      </div>
      <textarea
        value={notes}
        onChange={(event) => {
          onChange(event.target.value);
        }}
        disabled={disabled}
        rows={6}
        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
        aria-label="Notatki do połączenia"
        placeholder="Dodaj najważniejsze informacje z rozmowy..."
      />
      <div className="flex items-center justify-between">
        <span className="text-xs text-slate-500 dark:text-slate-400">{notes.length} znaków</span>
        <button
          type="button"
          onClick={handleSave}
          disabled={isSaveDisabled}
          className={[
            "rounded-lg border border-blue-500 px-4 py-2 text-sm font-semibold text-blue-600 transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-blue-400 dark:text-blue-300",
            isSaveDisabled ? "pointer-events-none opacity-60" : "",
          ].join(" ")}
        >
          {isSaving ? "Zapisuję notatkę…" : "Zapisz notatkę"}
        </button>
      </div>
    </div>
  );
};


