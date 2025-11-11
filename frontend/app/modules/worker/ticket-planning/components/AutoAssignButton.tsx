import * as React from "react";

import type { DayPrediction } from "~/api/worker/planning";

export interface AutoAssignButtonProps {
  predictions: DayPrediction[];
  isLoading: boolean;
  disabled?: boolean;
  onAutoAssign: () => void;
}

const totalPredictedTickets = (predictions: DayPrediction[]) =>
  predictions.reduce((acc, prediction) => acc + prediction.predictedTicketCount, 0);

export const AutoAssignButton: React.FC<AutoAssignButtonProps> = ({
  predictions,
  isLoading,
  disabled,
  onAutoAssign,
}) => {
  const predictedTotal = totalPredictedTickets(predictions);

  const handleClick = React.useCallback(() => {
    const shouldProceed =
      typeof window !== "undefined"
        ? window.confirm(
            "Czy na pewno chcesz automatycznie przypisać tickety na podstawie przewidywań?",
          )
        : true;

    if (shouldProceed) {
      onAutoAssign();
    }
  }, [onAutoAssign]);

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={disabled || isLoading}
      className="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
    >
      {isLoading ? (
        <span className="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
      ) : null}
      <span>Automatyczne przypisanie</span>
      {predictedTotal > 0 ? (
        <span className="rounded-md bg-emerald-700/60 px-2 py-0.5 text-xs font-medium">
          {predictedTotal} przewidywane
        </span>
      ) : null}
    </button>
  );
};


