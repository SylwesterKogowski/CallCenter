import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { describe, expect, it, beforeEach, afterEach, vi } from "vitest";

import type {
  RegisterWorkerPayload,
  RegisterWorkerResponse,
  RegisteredWorker,
} from "~/api/auth";
import * as http from "~/api/http";
import type { ApiFetchOptions } from "~/api/http";
import type { TicketCategoriesResponse } from "~/api/ticket-categories";

import { WorkerRegisterForm } from "../WorkerRegisterForm";

const categoriesResponse: TicketCategoriesResponse = {
  categories: [
    {
      id: "category-sales",
      name: "Sprzedaż",
      description: "Obsługa ticketów sprzedażowych",
      defaultResolutionTimeMinutes: 45,
    },
    {
      id: "category-support",
      name: "Wsparcie techniczne",
      description: "Wsparcie klientów w zakresie produktów",
      defaultResolutionTimeMinutes: 60,
    },
  ],
};

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

const renderForm = (ui?: React.ReactNode) => {
  const queryClient = createQueryClient();

  const view = render(
    <QueryClientProvider client={queryClient}>
      {ui ?? <WorkerRegisterForm />}
    </QueryClientProvider>,
  );

  return { queryClient, view };
};

describe("WorkerRegisterForm", () => {
  let fetchSpy: ReturnType<typeof vi.spyOn<typeof http, "apiFetch">>;

  beforeEach(() => {
    fetchSpy = vi.spyOn(http, "apiFetch");
  });

  afterEach(() => {
    fetchSpy.mockRestore();
  });

  const mockCategories = () => {
    fetchSpy.mockResolvedValueOnce(categoriesResponse);
  };

  it("renders registration form fields and categories", async () => {
    mockCategories();
    renderForm();

    expect(
      screen.queryByRole("heading", { name: /Pracownik został pomyślnie/i }),
    ).not.toBeInTheDocument();
    expect(screen.getByLabelText(/Login pracownika/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^Hasło$/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Potwierdź hasło/i)).toBeInTheDocument();

    expect(
      screen.getByRole("button", { name: /Zarejestruj pracownika/i }),
    ).toBeInTheDocument();

    expect(await screen.findByText(/Wybierz, do jakich kolejek/i)).toBeInTheDocument();
    expect(
      await screen.findByRole("checkbox", { name: /Sprzedaż/i }),
    ).toBeInTheDocument();
    expect(
      await screen.findByRole("checkbox", { name: /Wsparcie techniczne/i }),
    ).toBeInTheDocument();
  });

  it("shows validation errors for empty submission", async () => {
    mockCategories();
    renderForm();

    const submitButton = await screen.findByRole("button", {
      name: /Zarejestruj pracownika/i,
    });

    await userEvent.click(submitButton);

    expect(
      await screen.findByText(/Login jest wymagany/i),
    ).toBeInTheDocument();
    expect(
      await screen.findByText(/Hasło jest wymagane/i),
    ).toBeInTheDocument();
    expect(
      await screen.findByText(/Potwierdzenie hasła jest wymagane/i),
    ).toBeInTheDocument();
    expect(
      await screen.findByText(/Wybierz co najmniej jedną kategorię/i),
    ).toBeInTheDocument();
  });

  it("submits worker data and shows success message", async () => {
    mockCategories();

    const registerResponse: RegisterWorkerResponse = {
      worker: {
        id: "worker-1",
        login: "jan.kowalski",
        isManager: true,
        createdAt: "2025-01-01T10:00:00Z",
      },
      categories: [
        { id: "category-sales", name: "Sprzedaż" },
        { id: "category-support", name: "Wsparcie techniczne" },
      ],
    };

    fetchSpy.mockResolvedValueOnce(registerResponse);

    const onWorkerRegistered = vi.fn<(worker: RegisteredWorker) => void>();

    renderForm(<WorkerRegisterForm onWorkerRegistered={onWorkerRegistered} />);

    await userEvent.type(screen.getByLabelText(/Login pracownika/i), "jan.kowalski");
    await userEvent.type(screen.getByLabelText(/^Hasło$/i), "SilneHaslo123");
    await userEvent.type(screen.getByLabelText(/Potwierdź hasło/i), "SilneHaslo123");
    await userEvent.click(await screen.findByRole("checkbox", { name: /Sprzedaż/i }));
    await userEvent.click(
      await screen.findByRole("checkbox", { name: /Wsparcie techniczne/i }),
    );
    await userEvent.click(screen.getByLabelText(/Nadaj uprawnienia managera/i));

    const submitButton = screen.getByRole("button", {
      name: /Zarejestruj pracownika/i,
    });

    await userEvent.click(submitButton);

    await waitFor(() =>
      expect(
        screen.getByText(/Pracownik został pomyślnie zarejestrowany/i),
      ).toBeInTheDocument(),
    );

    expect(onWorkerRegistered).toHaveBeenCalledWith(registerResponse.worker);

    const registerCall = fetchSpy.mock.calls[1]?.[0] as ApiFetchOptions | undefined;
    expect(registerCall?.path).toBe("/api/auth/register");
    expect(registerCall?.method).toBe("POST");
    expect(registerCall?.body).toEqual<RegisterWorkerPayload>({
      login: "jan.kowalski",
      password: "SilneHaslo123",
      categoryIds: ["category-sales", "category-support"],
      isManager: true,
    });
  });

  it("displays API validation errors", async () => {
    mockCategories();

    const apiError = new http.ApiError("Validation failed", 400, {
      message: "Login już istnieje w systemie",
      errors: {
        login: "Login już istnieje w systemie",
        categoryIds: "Musisz wybrać co najmniej jedną kategorię",
      },
    });

    fetchSpy.mockRejectedValueOnce(apiError);

    renderForm();

    await userEvent.type(screen.getByLabelText(/Login pracownika/i), "jan");
    await userEvent.type(screen.getByLabelText(/^Hasło$/i), "haslo1234");
    await userEvent.type(screen.getByLabelText(/Potwierdź hasło/i), "haslo1234");
    await userEvent.click(await screen.findByRole("checkbox", { name: /Sprzedaż/i }));

    await userEvent.click(
      screen.getByRole("button", { name: /Zarejestruj pracownika/i }),
    );

    const loginErrors = await screen.findAllByText(/Login już istnieje w systemie/i);
    expect(loginErrors.length).toBeGreaterThan(0);
    expect(
      await screen.findByText(/Musisz wybrać co najmniej jedną kategorię/i),
    ).toBeInTheDocument();
  });

  it("resets form when registering another worker", async () => {
    mockCategories();

    const registerResponse: RegisterWorkerResponse = {
      worker: {
        id: "worker-2",
        login: "anna.nowak",
        isManager: false,
        createdAt: "2025-01-02T12:00:00Z",
      },
      categories: [{ id: "category-sales", name: "Sprzedaż" }],
    };

    fetchSpy.mockResolvedValueOnce(registerResponse);

    renderForm();

    await userEvent.type(screen.getByLabelText(/Login pracownika/i), "anna.nowak");
    await userEvent.type(screen.getByLabelText(/^Hasło$/i), "MocneHaslo123");
    await userEvent.type(screen.getByLabelText(/Potwierdź hasło/i), "MocneHaslo123");
    await userEvent.click(await screen.findByRole("checkbox", { name: /Sprzedaż/i }));

    await userEvent.click(
      screen.getByRole("button", { name: /Zarejestruj pracownika/i }),
    );

    expect(
      await screen.findByText(/Pracownik został pomyślnie zarejestrowany/i),
    ).toBeInTheDocument();

    await userEvent.click(
      screen.getByRole("button", { name: /Zarejestruj kolejnego pracownika/i }),
    );

    await waitFor(() =>
      expect(
        screen.queryByText(/Pracownik został pomyślnie zarejestrowany/i),
      ).not.toBeInTheDocument(),
    );

    const loginInput = screen.getByLabelText(
      /Login pracownika/i,
    ) as HTMLInputElement;
    expect(loginInput.value).toBe("");
  });
});


