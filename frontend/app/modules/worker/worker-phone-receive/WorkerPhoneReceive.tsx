import * as React from "react";

import type { WorkerTicket } from "~/api/worker/phone";

import { PhoneReceiveButton } from "./components/PhoneReceiveButton";
import { PhoneReceiveModal } from "./components/PhoneReceiveModal";

export interface WorkerPhoneReceiveProps {
  workerId: string;
  previousActiveTicket: WorkerTicket | null;
  /**
   * Optional callback invoked after the phone call modal successfully finishes.
   * Useful to refresh surrounding data (e.g. worker schedule).
   */
  onCompleted?: () => void;
}

export const WorkerPhoneReceive: React.FC<WorkerPhoneReceiveProps> = ({
  workerId,
  previousActiveTicket,
  onCompleted,
}) => {
  const [isModalOpen, setIsModalOpen] = React.useState(false);

  const handleOpen = React.useCallback(() => {
    setIsModalOpen(true);
  }, []);

  const handleClose = React.useCallback(
    (completed: boolean) => {
      setIsModalOpen(false);
      if (completed && onCompleted) {
        onCompleted();
      }
    },
    [onCompleted],
  );

  return (
    <div className="flex flex-col gap-3">
      <PhoneReceiveButton onClick={handleOpen} isDisabled={isModalOpen} isActive={false} />
      <PhoneReceiveModal
        isOpen={isModalOpen}
        onClose={handleClose}
        workerId={workerId}
        previousActiveTicket={previousActiveTicket}
      />
    </div>
  );
};


