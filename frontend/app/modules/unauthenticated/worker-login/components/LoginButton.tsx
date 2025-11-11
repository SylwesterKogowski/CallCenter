import * as React from "react";

export interface LoginButtonProps {
  isLoading: boolean;
  isDisabled?: boolean;
  onClick?: () => void;
  children?: React.ReactNode;
}

export const LoginButton: React.FC<LoginButtonProps> = ({
  isLoading,
  isDisabled,
  onClick,
  children,
}) => {
  return (
    <button
      type="submit"
      disabled={isDisabled}
      onClick={onClick}
      className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-not-allowed disabled:bg-blue-400 dark:bg-blue-500 dark:hover:bg-blue-400 dark:focus:ring-blue-900 dark:disabled:bg-blue-700"
    >
      {isLoading ? (
        <>
          <span className="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
          <span>Logowanie...</span>
        </>
      ) : (
        children ?? "Zaloguj się"
      )}
    </button>
  );
};


