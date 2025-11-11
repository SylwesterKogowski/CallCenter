import * as React from "react";

interface EndCallButtonProps {
  onEndCall: () => void;
  isLoading: boolean;
  callDuration: number;
  hasSelectedTicket: boolean;
  disabled?: boolean;
}

export const EndCallButton: React.FC<EndCallButtonProps> = ({
  onEndCall,
  isLoading,
  callDuration,
  hasSelectedTicket,
  disabled = false,
}) => {
  const isDisabled = disabled || isLoading || callDuration === 0;

  return (
    <button
      type="button"
      onClick={onEndCall}
      disabled={isDisabled}
      className={[
        "relative flex w-full items-center justify-center gap-2 rounded-xl border px-4 py-3 text-lg font-semibold transition",
        hasSelectedTicket
          ? "border-emerald-500 bg-emerald-500 text-white hover:bg-emerald-400"
          : "border-amber-500 bg-amber-500 text-white hover:bg-amber-400",
        isDisabled ? "pointer-events-none opacity-60" : "",
      ].join(" ")}
    >
      {isLoading ? "Zapisuję połączenie…" : "Zakończyłem połączenie"}
    </button>
  );
};


