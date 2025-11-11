import * as React from "react";

import { formatDateLabel } from "../utils";

export interface DateSelectorProps {
  selectedDate: string;
  minDate?: string;
  maxDate?: string;
  onDateChange: (value: string) => void;
  isDisabled?: boolean;
}

const shiftDate = (current: string, offset: number) => {
  const base = new Date(current);
  if (Number.isNaN(base.getTime())) {
    return current;
  }
  base.setDate(base.getDate() + offset);
  const year = base.getFullYear();
  const month = `${base.getMonth() + 1}`.padStart(2, "0");
  const day = `${base.getDate()}`.padStart(2, "0");
  return `${year}-${month}-${day}`;
};

export const DateSelector: React.FC<DateSelectorProps> = ({
  selectedDate,
  minDate,
  maxDate,
  onDateChange,
  isDisabled = false,
}) => {
  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    onDateChange(event.target.value);
  };

  const handleShift = (offset: number) => {
    onDateChange(shiftDate(selectedDate, offset));
  };

  return (
    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
      <div>
        <p className="text-sm font-medium text-gray-600">Wybrany dzień</p>
        <p className="text-lg font-semibold text-gray-900">{formatDateLabel(selectedDate)}</p>
      </div>
      <div className="flex flex-1 items-center gap-2">
        <button
          type="button"
          className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
          onClick={() => handleShift(-1)}
          disabled={isDisabled}
        >
          Poprzedni dzień
        </button>
        <input
          type="date"
          className="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          value={selectedDate}
          onChange={handleChange}
          min={minDate}
          max={maxDate}
          disabled={isDisabled}
          aria-label="Wybierz dzień monitoringu"
        />
        <button
          type="button"
          className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
          onClick={() => handleShift(1)}
          disabled={isDisabled || (maxDate !== undefined && selectedDate >= maxDate)}
        >
          Następny dzień
        </button>
      </div>
    </div>
  );
};


