import * as React from "react";

interface PasswordInputBaseProps {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  isDisabled?: boolean;
  autoComplete?: string;
  showPasswordToggle?: boolean;
  isInvalid?: boolean;
}

const PasswordInputBase: React.FC<PasswordInputBaseProps> = ({
  id,
  label,
  value,
  onChange,
  error,
  isDisabled,
  autoComplete,
  showPasswordToggle = true,
  isInvalid,
}) => {
  const [isVisible, setIsVisible] = React.useState(false);

  const toggleVisibility = () => {
    setIsVisible((current) => !current);
  };

  return (
    <div>
      <label
        htmlFor={id}
        className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200"
      >
        {label}
      </label>
      <div className="relative">
        <input
          id={id}
          type={isVisible ? "text" : "password"}
          value={value}
          autoComplete={autoComplete}
          disabled={isDisabled}
          placeholder="co najmniej 8 znaków"
          aria-invalid={isInvalid ? "true" : "false"}
          onChange={(event) => onChange(event.target.value)}
          className="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-base text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-blue-400 dark:focus:ring-blue-900"
        />
        {showPasswordToggle ? (
          <button
            type="button"
            onClick={toggleVisibility}
            className="absolute inset-y-0 right-0 flex items-center px-3 text-sm font-medium text-blue-600 transition hover:text-blue-500 focus:outline-none disabled:cursor-not-allowed dark:text-blue-300"
            disabled={isDisabled}
            aria-label={isVisible ? "Ukryj hasło" : "Pokaż hasło"}
          >
            {isVisible ? "Ukryj" : "Pokaż"}
          </button>
        ) : null}
      </div>
      {error ? (
        <p className="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
};

export interface PasswordInputProps {
  password: string;
  onChange: (value: string) => void;
  error?: string;
  isDisabled?: boolean;
  showPasswordToggle?: boolean;
}

export const PasswordInput: React.FC<PasswordInputProps> = ({
  password,
  onChange,
  error,
  isDisabled,
  showPasswordToggle,
}) => (
  <PasswordInputBase
    id="worker-register-password"
    label="Hasło"
    value={password}
    onChange={onChange}
    error={error}
    isDisabled={isDisabled}
    autoComplete="new-password"
    showPasswordToggle={showPasswordToggle}
    isInvalid={Boolean(error)}
  />
);

export interface ConfirmPasswordInputProps {
  password: string;
  confirmPassword: string;
  onChange: (value: string) => void;
  error?: string;
  isDisabled?: boolean;
  showPasswordToggle?: boolean;
}

export const ConfirmPasswordInput: React.FC<ConfirmPasswordInputProps> = ({
  password,
  confirmPassword,
  onChange,
  error,
  isDisabled,
  showPasswordToggle,
}) => {
  const isMismatch =
    confirmPassword.length > 0 && password.length > 0 && confirmPassword !== password;

  return (
    <PasswordInputBase
      id="worker-register-confirm-password"
      label="Potwierdź hasło"
      value={confirmPassword}
      onChange={onChange}
      error={error}
      isDisabled={isDisabled}
      autoComplete="new-password"
      showPasswordToggle={showPasswordToggle}
      isInvalid={Boolean(error) || isMismatch}
    />
  );
};

ConfirmPasswordInput.displayName = "ConfirmPasswordInput";
PasswordInput.displayName = "PasswordInput";


