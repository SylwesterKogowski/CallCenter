import * as React from "react";

import { formatDateTime } from "../utils";

export interface LastUpdateIndicatorProps {
  lastUpdate: string | null;
  isConnected: boolean;
  isRefreshing?: boolean;
  onRefresh: () => void;
}

export const LastUpdateIndicator: React.FC<LastUpdateIndicatorProps> = ({
  lastUpdate,
  isConnected,
  isRefreshing = false,
  onRefresh,
}) => {
  return (
    <div className="flex flex-wrap items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
      <div className="space-y-1">
        <p className="text-sm font-semibold text-gray-700">
          Ostatnia aktualizacja: <span className="font-normal">{formatDateTime(lastUpdate)}</span>
        </p>
        <p className="text-sm text-gray-500">
          Połączenie SSE:{" "}
          <span
            className={`font-medium ${
              isConnected ? "text-green-600" : "text-red-600"
            }`}
            data-testid="sse-connection-status"
          >
            {isConnected ? "aktywne" : "rozłączone"}
          </span>
        </p>
      </div>
      <button
        type="button"
        onClick={() => {
          if (!isRefreshing) {
            onRefresh();
          }
        }}
        disabled={isRefreshing}
        className="mt-2 inline-flex items-center rounded-md border border-blue-600 px-3 py-2 text-sm font-medium text-blue-600 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60 md:mt-0"
      >
        {isRefreshing ? "Odświeżanie..." : "Odśwież dane"}
      </button>
    </div>
  );
};


