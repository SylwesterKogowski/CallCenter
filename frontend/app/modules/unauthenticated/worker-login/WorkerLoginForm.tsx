import * as React from "react";
import { useNavigate } from "react-router";

import {
  useLoginMutation,
  type LoginPayload,
  type LoginResponse,
} from "~/api/auth";
import { ApiError } from "~/api/http";

import { LoginButton } from "./components/LoginButton";
import { ErrorDisplay } from "./components/ErrorDisplay";
import { LoadingSpinner } from "./components/LoadingSpinner";
import { LoginCard } from "./components/LoginCard";
import { LoginInput } from "./components/LoginInput";
import { PasswordInput } from "./components/PasswordInput";
import {
  clearWorkerSession,
  saveWorkerSession,
  WORKER_SESSION_STORAGE_KEY,
} from "./session";
import type { LoginErrors, Worker } from "./types";
import { validateCredentials } from "./validation";

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null;

const extractFieldErrors = (payload: unknown): LoginErrors => {
  if (!isRecord(payload) || !isRecord(payload.errors)) {
    return {};
  }

  const { errors } = payload;

  const result: LoginErrors = {};

  if (typeof errors.login === "string") {
    result.login = errors.login;
  }

  if (typeof errors.password === "string") {
    result.password = errors.password;
  }

  if (typeof errors.general === "string") {
    result.general = errors.general;
  }

  return result;
};

const extractMessage = (payload: unknown, fallback: string) => {
  if (isRecord(payload) && typeof payload.message === "string") {
    return payload.message;
  }

  if (isRecord(payload) && typeof payload.error === "string") {
    return payload.error;
  }

  return fallback;
};

export interface WorkerLoginFormProps {
  onLoginSuccess?: (worker: Worker, response: LoginResponse) => void;
  title?: string;
  initialLogin?: string;
  autoFocusLogin?: boolean;
  showPasswordToggle?: boolean;
  navigate?: (path: string) => void;
  disableSessionPersistence?: boolean;
}

export const WorkerLoginForm: React.FC<WorkerLoginFormProps> = ({
  onLoginSuccess,
  title = "Logowanie do systemu",
  initialLogin = "",
  autoFocusLogin = true,
  showPasswordToggle = true,
  navigate: navigateOverride,
  disableSessionPersistence = false,
}) => {
  const [login, setLogin] = React.useState(initialLogin);
  const [password, setPassword] = React.useState("");
  const [errors, setErrors] = React.useState<LoginErrors>({});
  const [apiError, setApiError] = React.useState<string | null>(null);

  const loginMutation = useLoginMutation();
  const navigateHook = useNavigate();
  const navigate = navigateOverride ?? navigateHook;

  const passwordInputRef = React.useRef<HTMLInputElement>(null);
  const formRef = React.useRef<HTMLFormElement>(null);

  const isSubmitting = loginMutation.isPending;

  const handleResetErrors = React.useCallback(() => {
    setErrors({});
    setApiError(null);
  }, []);

  const persistSession = React.useCallback(
    (response: LoginResponse) => {
      if (disableSessionPersistence) {
        return;
      }

      saveWorkerSession({
        worker: response.worker,
        token: response.session.token,
        expiresAt: response.session.expiresAt,
      });
    },
    [disableSessionPersistence],
  );

  const handleSuccess = React.useCallback(
    (response: LoginResponse) => {
      persistSession(response);
      onLoginSuccess?.(response.worker, response);
      navigate("/worker");
    },
    [navigate, onLoginSuccess, persistSession],
  );

  const handleApiError = React.useCallback((error: unknown) => {
    if (error instanceof ApiError) {
      const fieldErrors = extractFieldErrors(error.payload);
      const message = extractMessage(error.payload, error.message);

      setErrors(fieldErrors);
      setApiError(message);
      return;
    }

    if (error instanceof Error) {
      setApiError(error.message);
      return;
    }

    setApiError("Nie udało się zalogować. Spróbuj ponownie.");
  }, []);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (isSubmitting) {
      return;
    }

    const validationErrors = validateCredentials(login, password);

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      setApiError(null);
      return;
    }

    try {
      handleResetErrors();

      const payload: LoginPayload = { login, password };
      const response = await loginMutation.mutateAsync(payload);

      handleSuccess(response);
    } catch (error) {
      handleApiError(error);
    }
  };

  const handleGeneralInputChange = () => {
    if (Object.keys(errors).length > 0 || apiError) {
      setErrors({});
      setApiError(null);
    }
  };

  React.useEffect(() => {
    return () => {
      if (!disableSessionPersistence) {
        return;
      }

      clearWorkerSession();
    };
  }, [disableSessionPersistence]);

  return (
    <LoginCard title={title}>
      <form
        ref={formRef}
        className="space-y-6"
        onSubmit={handleSubmit}
        onChange={handleGeneralInputChange}
        noValidate
      >
        <ErrorDisplay
          errors={errors}
          apiError={apiError}
          onDismiss={handleResetErrors}
        />

        <LoginInput
          login={login}
          onChange={setLogin}
          error={errors.login}
          isDisabled={isSubmitting}
          autoFocus={autoFocusLogin}
        />

        <PasswordInput
          password={password}
          onChange={setPassword}
          error={errors.password}
          isDisabled={isSubmitting}
          showPasswordToggle={showPasswordToggle}
          inputRef={passwordInputRef}
          onEnterPress={() => formRef.current?.requestSubmit()}
        />

        <LoginButton isLoading={isSubmitting} isDisabled={isSubmitting}>
          Zaloguj się
        </LoginButton>

        {isSubmitting ? <LoadingSpinner message="Sprawdzamy dane..." /> : null}
      </form>
      <p className="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
        Logowanie jest chronione protokołem HTTPS. Hasło nie jest przechowywane
        na Twoim urządzeniu.
      </p>
    </LoginCard>
  );
};

export { WORKER_SESSION_STORAGE_KEY };


