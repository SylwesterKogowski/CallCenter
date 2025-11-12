import * as React from "react";

import { ApiError } from "~/api/http";
import { useTicketCategoriesQuery } from "~/api/ticket-categories";
import type { Client } from "~/api/types";

import { CategorySelector } from "../../../unauthenticated/ticket-add/components/CategorySelector";

export interface NewTicketData {
  title: string;
  categoryId: string;
  clientId?: string;
  clientData?: {
    name: string;
    email?: string;
    phone?: string;
  };
}

interface TicketCreateFormProps {
  onTicketCreate: (ticketData: NewTicketData) => Promise<void>;
  onCancel: () => void;
  workerId: string;
  clientSearchResults: Client[];
  onClientSearch: (value: string) => void;
  isSubmitting: boolean;
  errorMessage: string | null;
}

export const TicketCreateForm: React.FC<TicketCreateFormProps> = ({
  onTicketCreate,
  onCancel,
  workerId,
  clientSearchResults,
  onClientSearch,
  isSubmitting,
  errorMessage,
}) => {
  void workerId;

  const [title, setTitle] = React.useState("");
  const [categoryId, setCategoryId] = React.useState("");
  const [clientQuery, setClientQuery] = React.useState("");
  const [selectedClientId, setSelectedClientId] = React.useState<string | null>(null);
  const [newClientName, setNewClientName] = React.useState("");
  const [newClientEmail, setNewClientEmail] = React.useState("");
  const [newClientPhone, setNewClientPhone] = React.useState("");
  const [formError, setFormError] = React.useState<string | null>(null);
  const [categoryError, setCategoryError] = React.useState<string | null>(null);

  const categoriesQuery = useTicketCategoriesQuery({
    staleTime: 5 * 60 * 1000,
  });

  const categories = categoriesQuery.data?.categories ?? [];

  const categoryErrorMessage = React.useMemo(() => {
    const error = categoriesQuery.error;

    if (!error) {
      return null;
    }

    const extractMessage = (payload: unknown, fallback: string) => {
      if (typeof payload === "object" && payload !== null) {
        const record = payload as Record<string, unknown>;
        if (typeof record.message === "string") {
          return record.message;
        }
        if (typeof record.error === "string") {
          return record.error;
        }
      }

      return fallback;
    };

    if (error instanceof ApiError) {
      return extractMessage(error.payload, error.message);
    }

    if (error instanceof Error) {
      return error.message;
    }

    return "Nie udało się pobrać listy kategorii.";
  }, [categoriesQuery.error]);

  const resetForm = React.useCallback(() => {
    setTitle("");
    setCategoryId("");
    setCategoryError(null);
    setClientQuery("");
    setSelectedClientId(null);
    setNewClientName("");
    setNewClientEmail("");
    setNewClientPhone("");
    setFormError(null);
  }, []);

  const validate = React.useCallback(() => {
    setCategoryError(null);

    if (!title.trim()) {
      setFormError("Podaj tytuł ticketa.");
      return false;
    }

    if (!categoryId.trim()) {
      setCategoryError("Wybierz kategorię.");
      setFormError(null);
      return false;
    }

    if (!selectedClientId && !newClientName.trim()) {
      setFormError("Wybierz istniejącego klienta lub uzupełnij dane nowego klienta.");
      return false;
    }

    setFormError(null);
    return true;
  }, [categoryId, newClientName, selectedClientId, title]);

  const handleSubmit = React.useCallback(
    async (event: React.FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      if (!validate()) {
        return;
      }

      const payload: NewTicketData = {
        title: title.trim(),
        categoryId: categoryId.trim(),
      };

      if (selectedClientId) {
        payload.clientId = selectedClientId;
      } else {
        payload.clientData = {
          name: newClientName.trim(),
          email: newClientEmail.trim() || undefined,
          phone: newClientPhone.trim() || undefined,
        };
      }

      await onTicketCreate(payload);
      resetForm();
    },
    [
      categoryId,
      newClientEmail,
      newClientName,
      newClientPhone,
      onTicketCreate,
      resetForm,
      selectedClientId,
      title,
      validate,
    ],
  );

  React.useEffect(() => {
    const timeout = window.setTimeout(() => {
      if (clientQuery.trim().length === 0) {
        return;
      }

      onClientSearch(clientQuery.trim());
    }, 300);

    return () => {
      window.clearTimeout(timeout);
    };
  }, [clientQuery, onClientSearch]);

  const handleCategoryChange = React.useCallback(
    (nextCategoryId: string) => {
      setCategoryId(nextCategoryId);
      setCategoryError(null);
      setFormError(null);
    },
    [],
  );

  const handleRetryCategories = React.useCallback(() => {
    void categoriesQuery.refetch();
  }, [categoriesQuery]);

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/50"
    >
      <header className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Utwórz nowy ticket
        </h2>
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Uzupełnij najważniejsze dane, aby utworzyć ticket w trakcie rozmowy.
        </p>
      </header>

      {formError ? (
        <div className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-900/40 dark:text-amber-200">
          {formError}
        </div>
      ) : null}

      {errorMessage ? (
        <div className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-200">
          {errorMessage}
        </div>
      ) : null}

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-600 dark:text-slate-300">Tytuł</span>
        <input
          type="text"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          placeholder="np. Problemy z konfiguracją routera"
          className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
          required
        />
      </label>

      <CategorySelector
        categories={categories}
        selectedCategoryId={categoryId}
        onChange={handleCategoryChange}
        error={categoryError ?? undefined}
        isDisabled={isSubmitting}
        isLoading={categoriesQuery.isPending}
        isError={categoriesQuery.isError}
        fetchErrorMessage={categoryErrorMessage ?? undefined}
        onRetry={categoriesQuery.isError ? handleRetryCategories : undefined}
      />

      <section className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40">
        <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-200">
          Klient rozmowy
        </h3>

        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-600 dark:text-slate-300">Szukaj klienta</span>
          <input
            type="search"
            value={clientQuery}
            onChange={(event) => {
              setClientQuery(event.target.value);
              setSelectedClientId(null);
            }}
            placeholder="Wpisz nazwę, email lub telefon"
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
          />
        </label>

        {clientSearchResults.length > 0 ? (
          <ul className="flex flex-col gap-2">
            {clientSearchResults.map((client) => (
              <li key={client.id}>
                <button
                  type="button"
                  onClick={() => {
                    setSelectedClientId(client.id);
                    setNewClientName(client.name);
                    setNewClientEmail(client.email ?? "");
                    setNewClientPhone(client.phone ?? "");
                  }}
                  className={[
                    "flex w-full flex-col gap-1 rounded-lg border px-3 py-2 text-left text-sm transition",
                    selectedClientId === client.id
                      ? "border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-900/40 dark:text-blue-200"
                      : "border-slate-200 bg-white hover:border-blue-400 dark:border-slate-700 dark:bg-slate-900/40",
                  ].join(" ")}
                >
                  <span className="font-semibold">{client.name}</span>
                  <span className="text-xs text-slate-600 dark:text-slate-300">
                    {client.email ?? "brak e-mail"} · {client.phone ?? "brak telefonu"}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Zaczynając wpisywać dane klienta, pojawią się dopasowania.
          </p>
        )}

        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-600 dark:text-slate-300">Nazwa klienta</span>
            <input
              type="text"
              value={newClientName}
              onChange={(event) => {
                setNewClientName(event.target.value);
                setSelectedClientId(null);
              }}
              placeholder="Imię i nazwisko lub nazwa firmy"
              className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
              required={!selectedClientId}
            />
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-600 dark:text-slate-300">Email</span>
            <input
              type="email"
              value={newClientEmail}
              onChange={(event) => {
                setNewClientEmail(event.target.value);
                setSelectedClientId(null);
              }}
              placeholder="np. klient@example.com"
              className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-600 dark:text-slate-300">Telefon</span>
            <input
              type="tel"
              value={newClientPhone}
              onChange={(event) => {
                setNewClientPhone(event.target.value);
                setSelectedClientId(null);
              }}
              placeholder="+48 123 456 789"
              className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
            />
          </label>
        </div>
      </section>

      <div className="flex flex-wrap gap-2">
        <button
          type="submit"
          disabled={isSubmitting}
          className={[
            "rounded-xl border border-emerald-500 bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500",
            isSubmitting ? "pointer-events-none opacity-60" : "",
          ].join(" ")}
        >
          {isSubmitting ? "Tworzę ticket…" : "Utwórz ticket"}
        </button>
        <button
          type="button"
          onClick={() => {
            onCancel();
            resetForm();
          }}
          className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-400 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          Anuluj
        </button>
      </div>
    </form>
  );
};


