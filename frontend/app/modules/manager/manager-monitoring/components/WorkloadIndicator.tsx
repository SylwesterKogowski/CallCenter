import * as React from "react";

import type { WorkloadLevel } from "~/api/manager";

import { clamp, formatPercentage } from "../utils";

const workloadColors: Record<WorkloadLevel, string> = {
  low: "bg-green-500",
  normal: "bg-yellow-400",
  high: "bg-orange-500",
  critical: "bg-red-600",
};

const workloadLabels: Record<WorkloadLevel, string> = {
  low: "Niskie obciążenie",
  normal: "Normalne obciążenie",
  high: "Wysokie obciążenie",
  critical: "Krytyczne obciążenie",
};

export interface WorkloadIndicatorProps {
  workloadLevel: WorkloadLevel;
  timeSpent: number;
  timePlanned: number;
  showPercentage?: boolean;
}

export const WorkloadIndicator: React.FC<WorkloadIndicatorProps> = ({
  workloadLevel,
  timeSpent,
  timePlanned,
  showPercentage = true,
}) => {
  const percentage = React.useMemo(() => {
    if (!Number.isFinite(timeSpent) || !Number.isFinite(timePlanned) || timePlanned <= 0) {
      return 0;
    }
    return clamp((timeSpent / timePlanned) * 100, 0, 200);
  }, [timeSpent, timePlanned]);

  return (
    <div className="space-y-1">
      <div className="h-2.5 w-full rounded-full bg-gray-200">
        <div
          className={`h-2.5 rounded-full ${workloadColors[workloadLevel]}`}
          style={{ width: `${percentage}%`, maxWidth: "100%" }}
          role="progressbar"
          aria-valuenow={Math.round(percentage)}
          aria-valuemin={0}
          aria-valuemax={200}
          aria-label={workloadLabels[workloadLevel]}
        />
      </div>
      <div className="flex items-center justify-between text-xs text-gray-600">
        <span>{workloadLabels[workloadLevel]}</span>
        {showPercentage ? <span>{formatPercentage(percentage)}</span> : null}
      </div>
    </div>
  );
};


