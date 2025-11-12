const MERCURE_HUB_URL = import.meta.env.VITE_MERCURE_URL ?? "/.well-known/mercure";
const MERCURE_SUBSCRIBER_TOKEN = import.meta.env.VITE_MERCURE_JWT_SUBSCRIBER_TOKEN;

export interface MercurePayload<T = unknown> {
  /** Raw event name as received from Mercure hub. */
  event: string;
  /** Raw SSE data string. */
  raw: string;
  /** Parsed payload returned by the custom parser. */
  data: T;
}

export interface MercureSubscription {
  close: () => void;
  reconnect: () => void;
}

export interface MercureSubscribeOptions<T> {
  topics: string[];
  /**
   * Additional event names to listen to.
   * `'message'` is added automatically unless `includeMessageEvent` is set to false.
   */
  eventTypes?: string[];
  /**
   * Parses a raw SSE payload to a typed object.
   * Returning `null` will skip invoking `onMessage`.
   */
  parse?: (raw: string, eventName: string) => T | null;
  onMessage: (payload: MercurePayload<T>) => void;
  onError?: (error: Error) => void;
  onConnectionChange?: (isConnected: boolean) => void;
  includeMessageEvent?: boolean;
  withCredentials?: boolean;
}

const defaultParse = <T,>(raw: string): T | null => {
  try {
    return JSON.parse(raw) as T;
  } catch (error) {
    console.warn("Failed to parse Mercure payload", error);
    return null;
  }
};

const buildHubUrl = (topics: string[]): string | null => {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const url = new URL(MERCURE_HUB_URL, window.location.origin);

    topics.forEach((topic) => {
      const normalized = topic.startsWith("http") ? topic : topic.trim();
      if (normalized.length > 0) {
        url.searchParams.append("topic", normalized);
      }
    });

    if (MERCURE_SUBSCRIBER_TOKEN) {
      url.searchParams.set("authorization", MERCURE_SUBSCRIBER_TOKEN);
    }

    return url.toString();
  } catch (error) {
    console.error("Failed to build Mercure hub URL", error);
    return null;
  }
};

export const subscribeToMercure = <T = unknown>(
  options: MercureSubscribeOptions<T>,
): MercureSubscription => {
  const {
    topics,
    eventTypes = [],
    parse = defaultParse as (raw: string, eventName: string) => T | null,
    onMessage,
    onError,
    onConnectionChange,
    includeMessageEvent = true,
    withCredentials = false,
  } = options;

  if (typeof window === "undefined" || typeof window.EventSource === "undefined") {
    onError?.(new Error("Mercure SSE is not supported in this environment."));
    return {
      close: () => {},
      reconnect: () => {},
    };
  }

  const normalizedEventTypes = new Set<string>(
    [
      ...(includeMessageEvent ? ["message"] : []),
      ...eventTypes.map((name) => name.trim()).filter((name) => name.length > 0),
    ].sort(),
  );

  const listeners = new Map<string, (event: MessageEvent<string>) => void>();
  let eventSource: EventSource | null = null;

  const cleanup = () => {
    if (!eventSource) {
      return;
    }

    listeners.forEach((handler, eventName) => {
      eventSource?.removeEventListener(eventName, handler);
    });
    listeners.clear();
    eventSource.close();
    eventSource = null;
  };

  const connect = () => {
    const hubUrl = buildHubUrl(topics);

    if (!hubUrl) {
      onError?.(new Error("Unable to resolve Mercure hub URL."));
      return;
    }

    cleanup();

    try {
      eventSource = new EventSource(hubUrl, { withCredentials });
    } catch (error) {
      cleanup();
      onError?.(
        error instanceof Error
          ? error
          : new Error("Failed to establish Mercure EventSource connection."),
      );
      return;
    }

    eventSource.onopen = () => {
      onConnectionChange?.(true);
    };

    eventSource.onerror = () => {
      onConnectionChange?.(false);
      onError?.(new Error("Mercure connection interrupted."));
    };

    normalizedEventTypes.forEach((eventName) => {
      const handler = (event: MessageEvent<string>) => {
        const payload = parse(event.data, event.type);

        if (payload === null) {
          return;
        }

        onMessage({
          event: event.type,
          raw: event.data,
          data: payload,
        });
      };

      listeners.set(eventName, handler);
      eventSource?.addEventListener(eventName, handler);
    });
  };

  connect();

  return {
    close: cleanup,
    reconnect: connect,
  };
};


