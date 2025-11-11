import * as React from "react";

interface PhoneReceiveButtonProps {
  onClick: () => void;
  isDisabled?: boolean;
  isActive?: boolean;
}

export const PhoneReceiveButton: React.FC<PhoneReceiveButtonProps> = ({
  onClick,
  isDisabled = false,
  isActive = false,
}) => {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={isDisabled}
      className={[
        "flex w-full items-center justify-center gap-3 rounded-2xl border px-6 py-2 text-lg font-semibold transition",
        isActive
          ? "border-emerald-500 bg-emerald-500 text-white shadow-lg"
          : "border-blue-500 bg-blue-600 text-white shadow-lg hover:bg-blue-500",
        isDisabled ? "pointer-events-none opacity-60" : "",
      ].join(" ")}
      aria-pressed={isActive}
    >
      {isActive ? "Połączenie w toku…" : "Odbieram telefon"}
    </button>
  );
};


