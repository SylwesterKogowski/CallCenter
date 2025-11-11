import * as React from "react";
import { loadWorkerSession } from "~/modules/unauthenticated/worker-login/session";

import { WorkerSchedule } from "~/modules/worker/worker-schedule";

export default function SchedulePage() {
  const workerId = React.useMemo(
    () => loadWorkerSession()?.worker.id ?? "",
    [],
  );

  return <WorkerSchedule workerId={workerId} />;
};


