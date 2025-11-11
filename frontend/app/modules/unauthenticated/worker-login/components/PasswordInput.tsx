import * as React from "react";

export interface PasswordInputProps {
  password: string;
  onChange: (value: string) => void;
  error?: string;
  isDisabled?: boolean;
  showPasswordToggle?: boolean;
  inputRef?: React.RefObject<HTMLInputElement>;
  onEnterPress?: () => void;
}

export const PasswordInput: React.FC<PasswordInputProps> = ({
  password,
  onChange,
  error,
  isDisabled,
  showPasswordToggle = true,
  inputRef,
  onEnterPress,
}) => {
  const [isPasswordVisible, setIsPasswordVisible] = React.useState(false);

  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter" && onEnterPress) {
      onEnterPress();
    }
  };

  return (
    <div>
      <label
        className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200"
        htmlFor="worker-login-form-password"
      >
        Hasło
      </label>
      <div className="relative">
        <input
          id="worker-login-form-password"
          name="password"
          type={isPasswordVisible ? "text" : "password"}
          autoComplete="current-password"
          value={password}
          ref={inputRef}
          disabled={isDisabled}
          placeholder="********"
          onKeyDown={handleKeyDown}
          onChange={(event) => onChange(event.target.value)}
          className="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-base text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-blue-400 dark:focus:ring-blue-900"
        />
        {showPasswordToggle ? (
          <button
            type="button"
            className="absolute inset-y-0 right-2 flex items-center rounded-md px-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed dark:text-slate-300 dark:hover:text-slate-50 dark:focus:ring-blue-900"
            onClick={() => setIsPasswordVisible((state) => !state)}
            disabled={isDisabled}
            aria-label={isPasswordVisible ? "Ukryj hasło" : "Pokaż hasło"}
          >
            {isPasswordVisible ? "Ukryj" : "Pokaż"}
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


