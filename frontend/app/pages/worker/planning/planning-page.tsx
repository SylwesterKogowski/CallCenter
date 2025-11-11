import * as React from "react";

import { loadWorkerSession } from "~/modules/unauthenticated/worker-login/session";
import { TicketPlanning } from "~/modules/worker/ticket-planning";

export default function PlanningPage() {
  const workerId = React.useMemo(
    () => loadWorkerSession()?.worker.id ?? "",
    [],
  );

  return <TicketPlanning workerId={workerId} />;
}

