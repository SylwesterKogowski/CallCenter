// Plik odpowiedzialny za wspólną warstwę komunikacji HTTP z backendem i mapę ścieżek API.

const API_BASE_URL = import.meta.env.VITE_API_URL;

if (!API_BASE_URL) {
  console.warn(
    "[api] Brak zmiennej środowiskowej VITE_API_URL. Żądania prawdopodobnie zakończą się niepowodzeniem.",
  );
}

export type QueryParams =
  | Record<
      string,
      | string
      | number
      | boolean
      | null
      | undefined
      | Array<string | number | boolean>
    >
  | URLSearchParams;

export interface ApiFetchOptions extends Omit<RequestInit, "body"> {
  path: string;
  params?: QueryParams;
  body?: unknown;
  headers?: HeadersInit;
}

export class ApiError extends Error {
  public status: number;
  public payload: unknown;

  constructor(message: string, status: number, payload: unknown) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.payload = payload;
  }
}

const normalizeParams = (params?: QueryParams) => {
  if (!params) {
    return undefined;
  }

  if (params instanceof URLSearchParams) {
    return params;
  }

  const searchParams = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === null || value === undefined) {
      return;
    }

    if (Array.isArray(value)) {
      if (value.length === 0) {
        return;
      }

      searchParams.set(
        key,
        value
          .map((item) => (typeof item === "string" ? item : String(item)))
          .join(","),
      );
      return;
    }

    if (typeof value === "boolean") {
      searchParams.set(key, value ? "true" : "false");
      return;
    }

    searchParams.set(key, typeof value === "string" ? value : String(value));
  });

  return searchParams;
};

const buildUrl = (path: string, params?: QueryParams) => {
  const baseUrl = API_BASE_URL ? API_BASE_URL.replace(/\/$/, "") : "";
  const sanitizedPath = path.startsWith("/") ? path : `/${path}`;

  const origin =
    typeof window !== "undefined" ? window.location.origin : "http://localhost";

  const url = new URL(`${baseUrl}${sanitizedPath}`, origin);
  const searchParams = normalizeParams(params);

  if (searchParams && [...searchParams.keys()].length > 0) {
    url.search = searchParams.toString();
  }

  return url.toString();
};

const isJson = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null;

export async function apiFetch<TResponse>({
  path,
  params,
  body,
  headers,
  ...rest
}: ApiFetchOptions): Promise<TResponse> {
  const url = buildUrl(path, params);

  const init: RequestInit = {
    credentials: "include",
    ...rest,
    headers: {
      Accept: "application/json",
      ...headers,
    },
  };

  if (body !== undefined) {
    init.body = body instanceof FormData ? body : JSON.stringify(body);
    if (!(body instanceof FormData)) {
      init.headers = {
        "Content-Type": "application/json",
        ...((init.headers as Record<string, string>) ?? {}),
      };
    }
  }

  const response = await fetch(url, init);

  const contentType = response.headers.get("content-type") ?? "";
  const canParseJson = contentType.includes("application/json");

  if (!response.ok) {
    let errorPayload: unknown = null;

    if (canParseJson) {
      try {
        errorPayload = await response.json();
      } catch (error) {
        errorPayload = { message: (error as Error).message };
      }
    } else {
      errorPayload = await response.text();
    }

    const message =
      isJson(errorPayload) && typeof errorPayload.message === "string"
        ? errorPayload.message
        : response.statusText || "Request failed";

    throw new ApiError(message, response.status, errorPayload);
  }

  if (response.status === 204) {
    return undefined as TResponse;
  }

  if (!canParseJson) {
    return (await response.text()) as TResponse;
  }

  return (await response.json()) as TResponse;
}

export const apiPaths = {
  managerMonitoring: "/api/manager/monitoring",
  managerAutoAssignment: "/api/manager/auto-assignment",
  managerAutoAssignmentTrigger: "/api/manager/auto-assignment/trigger",
  ticketCategories: "/api/ticket-categories",
  authRegister: "/api/auth/register",
  authLogin: "/api/auth/login",
  authLogout: "/api/auth/logout",
  workerTicketsBacklog: "/api/worker/tickets/backlog",
  workerScheduleWeek: "/api/worker/schedule/week",
  workerSchedulePredictions: "/api/worker/schedule/predictions",
  workerScheduleAssign: "/api/worker/schedule/assign",
  workerScheduleAutoAssign: "/api/worker/schedule/auto-assign",
  workerAvailability: "/api/worker/availability",
  workerAvailabilityCopy: "/api/worker/availability/copy",
  workerPhoneReceive: "/api/worker/phone/receive",
  workerPhoneEnd: "/api/worker/phone/end",
  workerTicketsSearch: "/api/worker/tickets/search",
  workerTickets: "/api/worker/tickets",
  workerTicketNotes: (ticketId: string) => `/api/worker/tickets/${ticketId}/notes`,
  workerTicketMessages: (ticketId: string) =>
    `/api/worker/tickets/${ticketId}/messages`,
  workerClientsSearch: "/api/worker/clients/search",
  workerSchedule: "/api/worker/schedule",
  workerTicketStatus: (ticketId: string) =>
    `/api/worker/tickets/${ticketId}/status`,
  workerTicketTime: (ticketId: string) =>
    `/api/worker/tickets/${ticketId}/time`,
  workerTicketClose: (ticketId: string) =>
    `/api/worker/tickets/${ticketId}/close`,
  workerWorkStatus: "/api/worker/work-status",
  tickets: "/api/tickets",
  ticketDetails: (ticketId: string) => `/api/tickets/${ticketId}`,
  ticketMessages: (ticketId: string) => `/api/tickets/${ticketId}/messages`,
  workerAvailabilityForDate: (date: string) =>
    `/api/worker/availability/${date}`,
  workerAvailabilityTimeSlot: (date: string, timeSlotId: string) =>
    `/api/worker/availability/${date}/time-slots/${timeSlotId}`,
} as const;

export type ApiPaths = typeof apiPaths;


