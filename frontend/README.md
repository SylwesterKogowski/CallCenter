
## Overview

Frontend for the call center scheduling platform. Agents log in to review their schedules, track active tickets, receive phone calls and collaborate with managers. Customers can open new tickets and follow conversations in real time. The app talks to the Symfony backend and listens to Server-Sent Events to stay in sync.

## Tech Stack

- React 18 with TypeScript [link](https://react.dev/reference/react)
- React Router for routing [link](https://reactrouter.com/start/framework/routing)
- TailwindCSS for styling [link](https://tailwindcss.com/docs/styling-with-utility-classes)
- `@tanstack/react-query` for data fetching, caching and background updates [link](https://tanstack.com/query/latest/docs/framework/react/quick-start)
- Vite for tooling and dev server

## Key Features

- Authentication flows for workers and managers
- Worker dashboards for schedules, phone handling, ticket planning and availability
- Manager monitoring with live metrics and controls
- Customer-facing ticket creation and chat with SSE updates
- Shared UI components and layouts optimized for call center workflows

## Project Structure

- `app/modules/*` — feature modules grouped by persona (unauthenticated, worker, manager)
- `app/pages/*` — page-level routes and layouts
- `app/shared/*` — shared UI, hooks and utilities
- `exec.sh` — helper for running npm commands inside the frontend container

## Development

- Install dependencies:

  ```bash
  ./exec.sh npm install
  ```

- Start local dev server (HMR enabled at `http://localhost:5173`):

  ```bash
  ./exec.sh npm run dev
  ```

## Production Build

Generate an optimized bundle:

```bash
./exec.sh npm run build
```