import * as React from "react";
import { useNavigate } from "react-router";

import { ApiError } from "~/api/http";
import { useTicketCategoriesQuery } from "~/api/ticket-categories";
import {
  useCreateTicketMutation,
  type CreateTicketPayload,
  type CreateTicketResponse,
} from "~/api/tickets";
import type { TicketCategory } from "~/api/types";

import { ClientDataForm } from "./components/ClientDataForm";
import { CategorySelector } from "./components/CategorySelector";
import { ErrorDisplay } from "./components/ErrorDisplay";
import { LoadingSpinner } from "./components/LoadingSpinner";
import { SubmitButton } from "./components/SubmitButton";
import {
  TicketDetailsForm,
  type TicketDetailsFormValues,
} from "./components/TicketDetailsForm";
import type {
  ClientData,
  ClientDataErrors,
  FormErrors,
  TicketAddFormValues,
} from "./types";
import {
  hasFormErrors,
  mergeFormErrors,
  validateTicketAddForm,
} from "./validation";

const emptyClientData: ClientData = Object.freeze({
  email: "",
  phone: "",
  firstName: "",
  lastName: "",
});

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null;

const sanitizeString = (value?: string) => {
  const trimmed = value?.trim() ?? "";
  return trimmed.length > 0 ? trimmed : undefined;
};

const focusFirstError = (form: HTMLFormElement | null, errors: FormErrors) => {
  if (!form) {
    return;
  }

  const selectors: string[] = [];

  if (errors.client?.email || errors.client?.general) {
    selectors.push('input[name="client.email"]');
  }

  if (errors.client?.phone) {
    selectors.push('input[name="client.phone"]');
  }

  if (errors.client?.firstName) {
    selectors.push('input[name="client.firstName"]');
  }

  if (errors.client?.lastName) {
    selectors.push('input[name="client.lastName"]');
  }

  if (errors.category) {
    selectors.push('select[name="categoryId"]');
  }

  if (errors.title) {
    selectors.push('input[name="title"]');
  }

  if (errors.description) {
    selectors.push('textarea[name="description"]');
  }

  selectors.push('[data-error-field]');

  for (const selector of selectors) {
    const element = form.querySelector<HTMLElement>(selector);
    if (element) {
      element.focus();
      if (typeof element.scrollIntoView === "function") {
        element.scrollIntoView({ behavior: "smooth", block: "center" });
      }
      return;
    }
  }

  if (errors.general) {
    form.focus();
  }
};

const extractFieldErrors = (payload: unknown): FormErrors => {
  if (!isRecord(payload) || !isRecord(payload.errors)) {
    return {};
  }

  const rawErrors = payload.errors as Record<string, unknown>;
  const formErrors: FormErrors = {};
  const clientErrors: ClientDataErrors = {};

  Object.entries(rawErrors).forEach(([key, value]) => {
    if (typeof value !== "string") {
      return;
    }

    switch (key) {
      case "categoryId":
        formErrors.category = value;
        break;
      case "title":
        formErrors.title = value;
        break;
      case "description":
        formErrors.description = value;
        break;
      case "client.email":
        clientErrors.email = value;
        break;
      case "client.phone":
        clientErrors.phone = value;
        break;
      case "client.firstName":
        clientErrors.firstName = value;
        break;
      case "client.lastName":
        clientErrors.lastName = value;
        break;
      case "client.general":
        clientErrors.general = value;
        break;
      case "general":
        formErrors.general = value;
        break;
      default:
        break;
    }
  });

  if (Object.keys(clientErrors).length > 0) {
    formErrors.client = clientErrors;
  }

  return formErrors;
};

const extractMessage = (payload: unknown, fallback: string) => {
  if (isRecord(payload)) {
    if (typeof payload.message === "string") {
      return payload.message;
    }

    if (typeof payload.error === "string") {
      return payload.error;
    }
  }

  return fallback;
};

export interface TicketAddFormProps {
  onTicketCreated?: (ticketId: string, response: CreateTicketResponse) => void;
  navigate?: (path: string) => void;
  initialValues?: Partial<TicketAddFormValues>;
}

export const TicketAddForm: React.FC<TicketAddFormProps> = ({
  onTicketCreated,
  navigate: navigateOverride,
  initialValues,
}) => {
  const [clientData, setClientData] = React.useState<ClientData>({
    ...emptyClientData,
    ...initialValues?.client,
  });
  const [selectedCategoryId, setSelectedCategoryId] = React.useState(
    initialValues?.categoryId ?? "",
  );
  const [ticketDetails, setTicketDetails] = React.useState<TicketDetailsFormValues>({
    title: initialValues?.title ?? "",
    description: initialValues?.description ?? "",
  });
  const [errors, setErrors] = React.useState<FormErrors>({});
  const [apiError, setApiError] = React.useState<string | null>(null);

  const formRef = React.useRef<HTMLFormElement>(null);
  const navigateHook = useNavigate();
  const navigate = navigateOverride ?? navigateHook;

  const categoriesQuery = useTicketCategoriesQuery({
    staleTime: 5 * 60 * 1000,
  });

  const createTicketMutation = useCreateTicketMutation();

  const isSubmitting = createTicketMutation.isPending;
  const categories: TicketCategory[] = categoriesQuery.data?.categories ?? [];

  const categoryErrorMessage = React.useMemo(() => {
    if (!categoriesQuery.error) {
      return null;
    }

    if (categoriesQuery.error instanceof ApiError) {
      return extractMessage(categoriesQuery.error.payload, categoriesQuery.error.message);
    }

    if (categoriesQuery.error instanceof Error) {
      return categoriesQuery.error.message;
    }

    return "Nie udalo sie pobrac listy kategorii.";
  }, [categoriesQuery.error]);

  const clearClientErrors = React.useCallback(() => {
    setErrors((prev) => {
      if (!prev.client) {
        return prev;
      }

      const next = { ...prev };
      delete next.client;
      return next;
    });
  }, []);

  const clearCategoryError = React.useCallback(() => {
    setErrors((prev) => {
      if (!prev.category) {
        return prev;
      }

      const next = { ...prev };
      delete next.category;
      return next;
    });
  }, []);

  const clearDetailsError = React.useCallback((field: "title" | "description") => {
    setErrors((prev) => {
      if (!prev[field]) {
        return prev;
      }

      const next = { ...prev };
      delete next[field];
      return next;
    });
  }, []);

  const resetErrors = React.useCallback(() => {
    setErrors({});
    setApiError(null);
  }, []);

  const handleClientChange = React.useCallback(
    (next: ClientData) => {
      setClientData(next);
      clearClientErrors();
      setApiError(null);
    },
    [clearClientErrors],
  );

  const handleCategoryChange = React.useCallback(
    (categoryId: string) => {
      setSelectedCategoryId(categoryId);
      clearCategoryError();
      setApiError(null);
    },
    [clearCategoryError],
  );

  const handleTicketDetailsChange = React.useCallback(
    (next: TicketDetailsFormValues) => {
      setTicketDetails(next);
      if (errors.title) {
        clearDetailsError("title");
      }
      if (errors.description) {
        clearDetailsError("description");
      }
      setApiError(null);
    },
    [clearDetailsError, errors.description, errors.title],
  );

  const handleApiError = React.useCallback(
    (error: unknown) => {
      if (error instanceof ApiError) {
        const fieldErrors = extractFieldErrors(error.payload);
        const message = extractMessage(error.payload, error.message);

        setErrors((prev) => mergeFormErrors(prev, fieldErrors));
        setApiError(message);
        focusFirstError(formRef.current, mergeFormErrors(fieldErrors));
        return;
      }

      if (error instanceof Error) {
        setApiError(error.message);
        return;
      }

      setApiError("Nie udalo sie utworzyc ticketa. Sprobuj ponownie.");
    },
    [],
  );

  const createPayload = React.useCallback((): CreateTicketPayload => {
    const sanitizedClient: ClientData = {
      email: sanitizeString(clientData.email),
      phone: sanitizeString(clientData.phone),
      firstName: sanitizeString(clientData.firstName),
      lastName: sanitizeString(clientData.lastName),
    };

    const sanitizedCategoryId = sanitizeString(selectedCategoryId) ?? "";

    const payload: CreateTicketPayload = {
      client: sanitizedClient,
      categoryId: sanitizedCategoryId,
      title: sanitizeString(ticketDetails.title),
      description: sanitizeString(ticketDetails.description),
    };

    if (!payload.title) {
      delete payload.title;
    }

    if (!payload.description) {
      delete payload.description;
    }

    Object.entries(payload.client).forEach(([key, value]) => {
      if (!value) {
        delete (payload.client as Record<string, string | undefined>)[key];
      }
    });

    return payload;
  }, [clientData, selectedCategoryId, ticketDetails.description, ticketDetails.title]);

  const handleSuccess = React.useCallback(
    (response: CreateTicketResponse) => {
      onTicketCreated?.(response.ticket.id, response);
      navigate(`/ticket-chat/${response.ticket.id}`);
    },
    [navigate, onTicketCreated],
  );

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (isSubmitting) {
      return;
    }

    const payload = createPayload();

    const validationErrors = validateTicketAddForm(
      {
        client: payload.client,
        categoryId: payload.categoryId,
        title: payload.title,
        description: payload.description,
      },
      categories,
    );

    if (hasFormErrors(validationErrors)) {
      setErrors(validationErrors);
      setApiError(null);
      focusFirstError(formRef.current, validationErrors);
      return;
    }

    try {
      resetErrors();
      const response = await createTicketMutation.mutateAsync(payload);
      handleSuccess(response);
    } catch (error) {
      handleApiError(error);
    }
  };

  const handleRetryCategories = () => {
    void categoriesQuery.refetch();
  };

  return (
    <div className="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <header className="space-y-2">
        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">
          Utworz nowy ticket
        </h1>
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Wypelnij formularz, aby rozpoczal sie czat z naszym zespolem wsparcia. Wszystkie
          pola poza kategoria sa opcjonalne.
        </p>
      </header>

      <form
        ref={formRef}
        className="mt-6 space-y-8"
        onSubmit={handleSubmit}
        noValidate
        aria-busy={isSubmitting}
        tabIndex={-1}
        aria-live="assertive"
      >
        <ErrorDisplay errors={errors} apiError={apiError} onDismiss={resetErrors} />

        <ClientDataForm
          data={clientData}
          errors={errors.client}
          onChange={handleClientChange}
          isDisabled={isSubmitting}
        />

        <CategorySelector
          categories={categories}
          selectedCategoryId={selectedCategoryId}
          onChange={handleCategoryChange}
          error={errors.category}
          isDisabled={isSubmitting}
          isLoading={categoriesQuery.isPending}
          isError={categoriesQuery.isError}
          fetchErrorMessage={categoryErrorMessage ?? undefined}
          onRetry={categoriesQuery.isError ? handleRetryCategories : undefined}
        />

        <TicketDetailsForm
          values={ticketDetails}
          errors={{
            title: errors.title,
            description: errors.description,
          }}
          onChange={handleTicketDetailsChange}
          isDisabled={isSubmitting}
        />

        <div className="space-y-3">
          <SubmitButton isLoading={isSubmitting} isDisabled={isSubmitting} />
          {isSubmitting ? (
            <LoadingSpinner message="Tworzymy ticket. To moze potrwac kilka sekund..." />
          ) : null}
        </div>
      </form>
    </div>
  );
};


