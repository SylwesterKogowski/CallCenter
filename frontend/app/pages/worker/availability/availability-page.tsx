import * as React from "react";

import { WorkerAvailability } from "~/modules/worker/worker-availability";
import { loadWorkerSession } from "~/modules/unauthenticated/worker-login/session";

export default function AvailabilityPage() {
  const workerId = React.useMemo(
    () => loadWorkerSession()?.worker.id ?? "",
    [],
  );

  return <WorkerAvailability workerId={workerId} />;
};


