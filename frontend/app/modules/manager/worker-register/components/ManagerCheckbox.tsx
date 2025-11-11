import * as React from "react";

export interface ManagerCheckboxProps {
  isManager: boolean;
  onChange: (isManager: boolean) => void;
  disabled?: boolean;
}

export const ManagerCheckbox: React.FC<ManagerCheckboxProps> = ({
  isManager,
  onChange,
  disabled,
}) => {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
      <label className="flex items-start gap-3">
        <input
          type="checkbox"
          checked={isManager}
          onChange={(event) => onChange(event.target.checked)}
          disabled={disabled}
          className="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 transition focus:ring-blue-500 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-900 dark:text-blue-400"
        />
        <div className="space-y-1">
          <span className="text-sm font-medium text-slate-800 dark:text-slate-100">
            Nadaj uprawnienia managera
          </span>
          <p className="text-xs text-slate-600 dark:text-slate-300">
            Manager ma dostęp do panelu monitoringu, może planować grafiki oraz
            rejestrować nowych pracowników.
          </p>
        </div>
      </label>
    </div>
  );
};

ManagerCheckbox.displayName = "ManagerCheckbox";


