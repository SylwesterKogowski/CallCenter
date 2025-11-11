import * as React from "react";
import type { Route } from "./+types/worker";

import { WorkerLayout } from "../pages/worker/layout";
import { SchedulePage } from "../pages/worker/schedule";

export const meta: Route.MetaFunction = () => [
  { title: "Call Center | Grafik pracownika" },
  {
    name: "description",
    content:
      "Zarządzaj swoim grafikiem, aktywnymi ticketami i zadaniami w Call Center.",
  },
];

export default function WorkerRoute() {
  return (
    <WorkerLayout>
      <SchedulePage />
    </WorkerLayout>
  );
}


