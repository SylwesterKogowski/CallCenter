import * as React from "react";

export interface RegisterButtonProps {
  isLoading: boolean;
  isDisabled?: boolean;
  children: React.ReactNode;
}

export const RegisterButton: React.FC<RegisterButtonProps> = ({
  isLoading,
  isDisabled,
  children,
}) => {
  return (
    <button
      type="submit"
      disabled={isDisabled}
      className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 disabled:cursor-not-allowed disabled:bg-blue-400 dark:bg-blue-500 dark:hover:bg-blue-400 dark:focus-visible:ring-blue-300"
    >
      {isLoading ? (
        <span className="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
      ) : null}
      <span>{isLoading ? "Rejestrujemy..." : children}</span>
    </button>
  );
};

RegisterButton.displayName = "RegisterButton";


