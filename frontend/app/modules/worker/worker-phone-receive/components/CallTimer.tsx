import * as React from "react";

export interface CallTimerProps {
  startTime: Date | null;
  isActive: boolean;
  onDurationChange?: (seconds: number) => void;
}

const formatDuration = (seconds: number): string => {
  const clamped = Math.max(0, seconds);
  const hours = Math.floor(clamped / 3600);
  const minutes = Math.floor((clamped % 3600) / 60);
  const sec = clamped % 60;

  const twoDigits = (value: number) => value.toString().padStart(2, "0");

  if (hours > 0) {
    return `${twoDigits(hours)}:${twoDigits(minutes)}:${twoDigits(sec)}`;
  }

  return `${twoDigits(minutes)}:${twoDigits(sec)}`;
};

export const CallTimer: React.FC<CallTimerProps> = ({ startTime, isActive, onDurationChange }) => {
  const [duration, dispatchDuration] = React.useReducer(
    (_: number, nextDuration: number) => nextDuration,
    0,
  );

  React.useEffect(() => {
    if (!isActive || !startTime) {
      dispatchDuration(0);
      onDurationChange?.(0);
      return;
    }

    const updateDuration = () => {
      const diff = Math.floor((Date.now() - startTime.getTime()) / 1000);
      dispatchDuration(diff);
      onDurationChange?.(diff);
    };

    updateDuration();

    const intervalId = window.setInterval(updateDuration, 1000);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [isActive, onDurationChange, startTime]);

  return (
    <div className="flex flex-col items-center gap-1 rounded-xl bg-slate-900 px-4 py-3 text-white shadow-lg dark:bg-slate-800">
      <span className="text-xs uppercase tracking-wide text-slate-300">Czas połączenia</span>
      <span className="text-3xl font-mono font-semibold">{formatDuration(duration)}</span>
    </div>
  );
};


