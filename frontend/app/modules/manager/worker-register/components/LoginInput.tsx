import * as React from "react";

export interface LoginInputProps {
  login: string;
  onChange: (login: string) => void;
  error?: string;
  isDisabled?: boolean;
  autoFocus?: boolean;
  inputRef?: React.RefObject<HTMLInputElement>;
}

export const LoginInput: React.FC<LoginInputProps> = ({
  login,
  onChange,
  error,
  isDisabled,
  autoFocus = true,
  inputRef,
}) => {
  return (
    <div>
      <label
        className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200"
        htmlFor="worker-register-login"
      >
        Login pracownika
      </label>
      <input
        id="worker-register-login"
        ref={inputRef}
        name="login"
        type="text"
        autoComplete="username"
        value={login}
        autoFocus={autoFocus}
        disabled={isDisabled}
        placeholder="np. jan.kowalski"
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-base text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-blue-400 dark:focus:ring-blue-900"
      />
      {error ? (
        <p className="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
};

LoginInput.displayName = "LoginInput";


