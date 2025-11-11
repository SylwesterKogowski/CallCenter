import * as React from "react";

import {
  useRegisterWorkerMutation,
  type RegisterWorkerPayload,
  type RegisteredWorker,
} from "~/api/auth";
import { ApiError } from "~/api/http";
import { useTicketCategoriesQuery } from "~/api/ticket-categories";
import type { TicketCategory } from "~/api/types";

import { CategoryCheckboxList } from "./components/CategoryCheckboxList";
import { ConfirmPasswordInput } from "./components/PasswordFields";
import { ErrorDisplay } from "./components/ErrorDisplay";
import { LoadingSpinner } from "./components/LoadingSpinner";
import { LoginInput } from "./components/LoginInput";
import { ManagerCheckbox } from "./components/ManagerCheckbox";
import { PasswordInput } from "./components/PasswordFields";
import { RegisterButton } from "./components/RegisterButton";
import { SuccessMessage } from "./components/SuccessMessage";

export interface WorkerRegisterFormProps {
  onWorkerRegistered?: (worker: RegisteredWorker) => void;
  autoFocusLogin?: boolean;
  showPasswordToggle?: boolean;
}

export interface FormErrors {
  login?: string;
  password?: string;
  confirmPassword?: string;
  categories?: string;
  general?: string;
}

interface WorkerRegisterFormState {
  login: string;
  password: string;
  confirmPassword: string;
  selectedCategories: string[];
  isManager: boolean;
}

const initialState: WorkerRegisterFormState = {
  login: "",
  password: "",
  confirmPassword: "",
  selectedCategories: [],
  isManager: false,
};

const LOGIN_PATTERN = /^[a-z0-9._]{3,255}$/i;

const validateState = (state: WorkerRegisterFormState): FormErrors => {
  const errors: FormErrors = {};

  if (!state.login.trim()) {
    errors.login = "Login jest wymagany.";
  } else if (state.login.trim().length < 3 || state.login.trim().length > 255) {
    errors.login = "Login musi mieć od 3 do 255 znaków.";
  } else if (!LOGIN_PATTERN.test(state.login.trim())) {
    errors.login =
      "Login może zawierać wyłącznie litery, cyfry, kropki oraz podkreślenia.";
  }

  if (!state.password) {
    errors.password = "Hasło jest wymagane.";
  } else if (state.password.length < 8) {
    errors.password = "Hasło musi mieć co najmniej 8 znaków.";
  }

  if (!state.confirmPassword) {
    errors.confirmPassword = "Potwierdzenie hasła jest wymagane.";
  } else if (state.confirmPassword !== state.password) {
    errors.confirmPassword = "Hasła muszą być identyczne.";
  }

  if (state.selectedCategories.length === 0) {
    errors.categories = "Wybierz co najmniej jedną kategorię.";
  }

  return errors;
};

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null;

const extractFieldErrors = (payload: unknown): FormErrors => {
  if (!isRecord(payload) || !isRecord(payload.errors)) {
    return {};
  }

  const { errors } = payload;
  const result: FormErrors = {};

  if (typeof errors.login === "string") {
    result.login = errors.login;
  }

  if (typeof errors.password === "string") {
    result.password = errors.password;
  }

  if (typeof errors.confirmPassword === "string") {
    result.confirmPassword = errors.confirmPassword;
  }

  if (typeof errors.categoryIds === "string") {
    result.categories = errors.categoryIds;
  } else if (typeof errors.categories === "string") {
    result.categories = errors.categories;
  }

  if (typeof errors.general === "string") {
    result.general = errors.general;
  }

  return result;
};

const extractMessage = (payload: unknown, fallback: string): string => {
  if (isRecord(payload) && typeof payload.message === "string") {
    return payload.message;
  }

  if (isRecord(payload) && typeof payload.error === "string") {
    return payload.error;
  }

  return fallback;
};

export const WorkerRegisterForm: React.FC<WorkerRegisterFormProps> = ({
  onWorkerRegistered,
  autoFocusLogin = true,
  showPasswordToggle = true,
}) => {
  const [state, setState] = React.useState<WorkerRegisterFormState>(initialState);
  const [errors, setErrors] = React.useState<FormErrors>({});
  const [apiError, setApiError] = React.useState<string | null>(null);
  const [registeredWorker, setRegisteredWorker] =
    React.useState<RegisteredWorker | null>(null);
  const [isSuccessVisible, setIsSuccessVisible] = React.useState(false);

  const loginInputRef = React.useRef<HTMLInputElement>(null);

  const registerWorkerMutation = useRegisterWorkerMutation();
  const categoriesQuery = useTicketCategoriesQuery({
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  });

  const isSubmitting = registerWorkerMutation.isPending;
  const categories = React.useMemo<TicketCategory[]>(
    () => categoriesQuery.data?.categories ?? [],
    [categoriesQuery.data?.categories],
  );

  const handleStateChange = React.useCallback(
    <K extends keyof WorkerRegisterFormState>(
      key: K,
      value: WorkerRegisterFormState[K],
    ) => {
      setState((current) => ({ ...current, [key]: value }));
      setErrors((current) => {
        if (key === "login" && current.login) {
          const next = { ...current };
          delete next.login;
          delete next.general;
          return next;
        }
        if (key === "password" && current.password) {
          const next = { ...current };
          delete next.password;
          delete next.confirmPassword;
          delete next.general;
          return next;
        }
        if (key === "confirmPassword" && current.confirmPassword) {
          const next = { ...current };
          delete next.confirmPassword;
          delete next.general;
          return next;
        }
        if (key === "selectedCategories" && current.categories) {
          const next = { ...current };
          delete next.categories;
          delete next.general;
          return next;
        }
        if (key === "isManager" && current.general) {
          const next = { ...current };
          delete next.general;
          return next;
        }
        return current;
      });
      if (apiError) {
        setApiError(null);
      }
      if (isSuccessVisible) {
        setIsSuccessVisible(false);
        setRegisteredWorker(null);
      }
    },
    [apiError, isSuccessVisible],
  );

  const resetForm = React.useCallback(() => {
    setState(initialState);
    setErrors({});
    setApiError(null);
    registerWorkerMutation.reset();
    setTimeout(() => {
      loginInputRef.current?.focus();
    }, 0);
  }, [registerWorkerMutation]);

  const handleRegisterAnother = React.useCallback(() => {
    setIsSuccessVisible(false);
    setRegisteredWorker(null);
    resetForm();
  }, [resetForm]);

  const handleApiError = React.useCallback((error: unknown) => {
    if (error instanceof ApiError) {
      const fieldErrors = extractFieldErrors(error.payload);
      const message = extractMessage(
        error.payload,
        error.message || "Rejestracja nie powiodła się.",
      );

      setErrors(fieldErrors);
      setApiError(message);
      return;
    }

    if (error instanceof Error) {
      setApiError(error.message);
      return;
    }

    setApiError("Rejestracja nie powiodła się. Spróbuj ponownie.");
  }, []);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (isSubmitting || isSuccessVisible) {
      return;
    }

    const validationErrors = validateState(state);
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      setApiError(null);
      return;
    }

    const payload: RegisterWorkerPayload = {
      login: state.login.trim(),
      password: state.password,
      categoryIds: state.selectedCategories,
      isManager: state.isManager,
    };

    try {
      setApiError(null);
      setErrors({});

      const response = await registerWorkerMutation.mutateAsync(payload);
      setRegisteredWorker(response.worker);
      setIsSuccessVisible(true);
      onWorkerRegistered?.(response.worker);
    } catch (error) {
      handleApiError(error);
    }
  };

  React.useEffect(() => {
    if (autoFocusLogin) {
      const timeout = window.setTimeout(() => {
        loginInputRef.current?.focus();
      }, 0);
      return () => window.clearTimeout(timeout);
    }
    return undefined;
  }, [autoFocusLogin]);

  return (
    <div className="space-y-6" data-testid="worker-register-form">
      {isSuccessVisible && registeredWorker ? (
        <SuccessMessage worker={registeredWorker} onRegisterAnother={handleRegisterAnother} />
      ) : null}

      <form className="space-y-6" onSubmit={handleSubmit} noValidate>
        <ErrorDisplay
          errors={errors}
          apiError={apiError}
          onDismiss={() => {
            setApiError(null);
            setErrors((current) => {
              const next = { ...current };
              delete next.general;
              return next;
            });
          }}
        />

        <LoginInput
          login={state.login}
          onChange={(value) => handleStateChange("login", value)}
          error={errors.login}
          isDisabled={isSubmitting || isSuccessVisible}
          autoFocus={autoFocusLogin}
          inputRef={loginInputRef}
        />

        <div className="grid gap-6 md:grid-cols-2">
          <PasswordInput
            password={state.password}
            onChange={(value) => handleStateChange("password", value)}
            error={errors.password}
            isDisabled={isSubmitting || isSuccessVisible}
            showPasswordToggle={showPasswordToggle}
          />

          <ConfirmPasswordInput
            password={state.password}
            confirmPassword={state.confirmPassword}
            onChange={(value) => handleStateChange("confirmPassword", value)}
            error={errors.confirmPassword}
            isDisabled={isSubmitting || isSuccessVisible}
            showPasswordToggle={showPasswordToggle}
          />
        </div>

        <CategoryCheckboxList
          categories={categories}
          selectedCategoryIds={state.selectedCategories}
          onChange={(value) => handleStateChange("selectedCategories", value)}
          error={errors.categories}
          isDisabled={isSubmitting || isSuccessVisible}
          isLoading={categoriesQuery.isLoading}
          isError={categoriesQuery.isError}
          onRetry={() => categoriesQuery.refetch()}
        />

        <ManagerCheckbox
          isManager={state.isManager}
          onChange={(value) => handleStateChange("isManager", value)}
          disabled={isSubmitting || isSuccessVisible}
        />

        <RegisterButton
          isLoading={isSubmitting}
          isDisabled={
            isSubmitting ||
            isSuccessVisible ||
            categoriesQuery.isLoading ||
            categoriesQuery.isError
          }
        >
          Zarejestruj pracownika
        </RegisterButton>
      </form>

      {isSubmitting ? <LoadingSpinner message="Rejestrujemy pracownika..." /> : null}
    </div>
  );
};

export default WorkerRegisterForm;

