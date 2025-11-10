import * as React from "react";

export interface SubmitButtonProps {
  isLoading: boolean;
  isDisabled?: boolean;
  children?: React.ReactNode;
}

export const SubmitButton: React.FC<SubmitButtonProps> = ({
  isLoading,
  isDisabled,
  children,
}) => {
  const label = isLoading ? "Wysylamy dane..." : children ?? "Utworz ticket";

  return (
    <button
      type="submit"
      className="inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
      disabled={isDisabled ?? isLoading}
      aria-busy={isLoading}
    >
      {label}
    </button>
  );
};


