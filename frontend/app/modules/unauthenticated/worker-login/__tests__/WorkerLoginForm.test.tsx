import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router";
import { describe, expect, it, beforeEach, afterEach, vi } from "vitest";

import * as http from "~/api/http";
import type { LoginResponse } from "~/api/auth";

import { WorkerLoginForm } from "../WorkerLoginForm";
import { WORKER_SESSION_STORAGE_KEY } from "../session";

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
    <MemoryRouter initialEntries={["/login"]}>
      <QueryClientProvider client={queryClient}>
        {ui ?? <WorkerLoginForm />}
      </QueryClientProvider>
    </MemoryRouter>,
  );

  return { queryClient, view };
};

describe("WorkerLoginForm", () => {
  const createLocalStorageMock = (): Storage => {
    let store: Record<string, string> = {};

    return {
      get length() {
        return Object.keys(store).length;
      },
      clear: () => {
        store = {};
      },
      getItem: (key: string) => store[key] ?? null,
      key: (index: number) => Object.keys(store)[index] ?? null,
      removeItem: (key: string) => {
        delete store[key];
      },
      setItem: (key: string, value: string) => {
        store[key] = value;
      },
    };
  };

  beforeEach(() => {
    const storage = createLocalStorageMock();
    Object.defineProperty(window, "localStorage", {
      value: storage,
      configurable: true,
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("renders login form fields and button", () => {
    renderForm();

    expect(screen.getByLabelText(/Login/i)).toBeInTheDocument();
    expect(
      screen.getByLabelText(/Haslo/i, { selector: "input" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Zaloguj sie/i }),
    ).toBeInTheDocument();
  });

  it("shows validation errors for empty fields", async () => {
    renderForm();

    await userEvent.click(
      screen.getByRole("button", { name: /Zaloguj sie/i }),
    );

    expect(
      await screen.findByText(/Login jest wymagany/i),
    ).toBeInTheDocument();
    expect(
      await screen.findByText(/Haslo jest wymagane/i),
    ).toBeInTheDocument();
  });

  it("submits credentials and stores session on success", async () => {
    const loginResponse: LoginResponse = {
      worker: {
        id: "worker-1",
        login: "jan.kowalski",
        createdAt: "2025-01-01T12:00:00Z",
      },
      session: {
        token: "jwt-token",
        expiresAt: "2025-01-01T18:00:00Z",
      },
    };

    const fetchSpy = vi
      .spyOn(http, "apiFetch")
      .mockResolvedValueOnce(loginResponse);

    const navigateMock = vi.fn();
    const onLoginSuccess = vi.fn();

    renderForm(
      <WorkerLoginForm
        navigate={navigateMock}
        onLoginSuccess={(worker, response) => onLoginSuccess(worker, response)}
      />,
    );

    await userEvent.type(screen.getByLabelText(/Login/i), "jan.kowalski");
    await userEvent.type(
      screen.getByLabelText(/Haslo/i, { selector: "input" }),
      "superHaslo123",
    );

    await userEvent.click(
      screen.getByRole("button", { name: /Zaloguj sie/i }),
    );

    await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

    expect(onLoginSuccess).toHaveBeenCalledWith(
      loginResponse.worker,
      loginResponse,
    );
    expect(navigateMock).toHaveBeenCalledWith("/worker");

    const stored = window.localStorage.getItem(WORKER_SESSION_STORAGE_KEY);
    expect(stored).not.toBeNull();

    const parsed = JSON.parse(stored ?? "{}");
    expect(parsed).toMatchObject({
      worker: loginResponse.worker,
      token: loginResponse.session.token,
      expiresAt: loginResponse.session.expiresAt,
    });
  });

  it("displays API validation errors", async () => {
    const apiError = new http.ApiError("Validation failed", 400, {
      errors: { login: "Login jest wymagany", password: "Haslo jest wymagane" },
      message: "Walidacja nie powiodla sie",
    });

    vi.spyOn(http, "apiFetch").mockRejectedValueOnce(apiError);

    renderForm();

    await userEvent.type(screen.getByLabelText(/Login/i), "ja");
    await userEvent.type(
      screen.getByLabelText(/Haslo/i, { selector: "input" }),
      "haslo123",
    );

    // pierwszy bled walidacji klienta
    await userEvent.click(
      screen.getByRole("button", { name: /Zaloguj sie/i }),
    );

    // Popraw login, aby przejsc walidacje klienta
    await userEvent.clear(screen.getByLabelText(/Login/i));
    await userEvent.type(screen.getByLabelText(/Login/i), "jan.kowalski");
    await userEvent.type(
      screen.getByLabelText(/Haslo/i, { selector: "input" }),
      "superHaslo123",
    );

    await userEvent.click(
      screen.getByRole("button", { name: /Zaloguj sie/i }),
    );

    expect(
      await screen.findByText(/Walidacja nie powiodla sie/i),
    ).toBeInTheDocument();
    expect(
      await screen.findByText(/Login jest wymagany/i),
    ).toBeInTheDocument();
    expect(
      await screen.findByText(/Haslo jest wymagane/i),
    ).toBeInTheDocument();

    expect(window.localStorage.getItem(WORKER_SESSION_STORAGE_KEY)).toBeNull();
  });
});


