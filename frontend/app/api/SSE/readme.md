# SSE Integration Overview

This document explains how Server-Sent Events (SSE) are consumed in the frontend through `mercureClient.ts` and the domain-specific connections located in `worker/worker-schedule` and `manager/manager-monitoring`.

## Mercure Client (`mercureClient.ts`)
- **Topics**: Every subscription provides one or more topic strings. They are appended to the Mercure hub URL as `topic` query parameters. Example: `worker/schedule/{workerId}`.
- **Authentication**: If `VITE_MERCURE_SUBSCRIBER_JWT_KEY` is defined, it is embedded as a `token` query parameter.
- **Events**: Consumers declare a list of `eventTypes`. The client ensures each is registered on the underlying `EventSource`. The default `message` event is only added when `includeMessageEvent` is `true`.
- **Parsing**: A `parse` callback converts the raw SSE payload into a typed object. Returning `null` skips the `onMessage` handler.
- **Handlers**:
  - `onMessage(payload)` receives `{ event, raw, data }` when parsing succeeds.
  - `onError(error)` is called when the connection fails, parsing throws, or the browser lacks SSE support.
  - `onConnectionChange(isConnected)` is triggered on `EventSource` open/close state transitions (optional).
- **Lifecycle**: `subscribeToMercure` returns `{ close, reconnect }` so callers can control the connection.

## Worker Schedule SSE (`worker/worker-schedule/components/SSEConnection.tsx`)
- **Topic**: `worker/schedule/{workerId}` (dynamic per worker).
- **Event Types**: `ticket_added`, `ticket_updated`, `ticket_removed`, `status_changed`, `time_updated`.
- **Payload Shape** (`ScheduleEnvelope`):
  - `type?`: optional event type hint from the backend.
  - `ticketId?`: string identifier of the ticket affected.
  - `data?`: domain-specific payload; forwarded verbatim.
  - `timestamp?`: ISO timestamp string.
- **Usage**: `WorkerScheduleSSEConnection` subscribes when `workerId` is present. Upon a message:
  - Resolve the final event type using `data.type` or fallback to the Mercure event name.
  - Call `onScheduleUpdate({ type, ticketId, data, timestamp })` where missing values fall back to `""` or the current timestamp.
  - Propagate errors via the optional `onError` prop.
- **Consumers**: Any schedule view component can mount this connection to receive real-time updates for a specific worker.

## Manager Monitoring SSE (`manager/manager-monitoring/components/SSEConnection.tsx`)
- **Topic**: Currently `manager/monitoring`. (The helper `buildManagerTopic` is prepared to include `managerId` and `selectedDate` when backend support is available.)
- **Event Types**: `worker_stats_updated`, `queue_stats_updated`, `ticket_added`, `ticket_status_changed`.
- **Payload Shape** (`MonitoringEnvelope`):
  - `type?`: optional backend-sent type; falls back to the event name when recognised.
  - `data?`: domain-specific monitoring payload.
  - `timestamp?`: ISO timestamp string.
- **Usage**: `ManagerMonitoringSSEConnection` subscribes whenever `managerId` is set.
  - Parses events, normalises the type, and calls `onUpdate({ type, data, timestamp })` with defaults similar to the worker connection.
  - Forwards errors to `onError` and connection state changes to `onConnectionChange` if provided.
- **Consumers**: Real-time monitoring dashboards mount this component to react to backend statistics and ticket activity updates.

## Error Handling & Reconnection
- Both domain connections rely on the shared `onError` pathway. They do not auto-retry but expose the `reconnect` handle from `subscribeToMercure` for manual reconnection strategies if needed.
- When a parse error occurs, the relevant connection invokes its `onError` callback and ignores the malformed event.

## Adding New Events or Topics
1. Extend the relevant `SUPPORTED_EVENT_TYPES` array.
2. Update the envelope interface to include any new payload fields.
3. Adjust downstream UI handlers to react to the new event type.
4. Ensure the backend publishes the corresponding Mercure topic/event combination.
