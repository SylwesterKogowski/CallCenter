import * as React from "react";

import {
  PhoneReceiveButton as CorePhoneReceiveButton,
} from "~/modules/worker/worker-phone-receive/components/PhoneReceiveButton";

interface PhoneReceiveButtonProps {
  onClick: () => void;
  isDisabled?: boolean;
  isActive?: boolean;
}

export const PhoneReceiveButton: React.FC<PhoneReceiveButtonProps> = ({
  onClick,
  isDisabled = false,
  isActive = false,
}) => {
  return (
    <div className="max-w-xs">
      <CorePhoneReceiveButton onClick={onClick} isDisabled={isDisabled} isActive={isActive} />
    </div>
  );
};


