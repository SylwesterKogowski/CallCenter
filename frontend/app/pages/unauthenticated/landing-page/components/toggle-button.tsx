import * as React from "react";

import type { LandingPageSection } from "../landing-page";

interface ToggleButtonProps {
  activeSection: LandingPageSection;
  onToggle: () => void;
}

export const ToggleButton: React.FC<ToggleButtonProps> = ({
  activeSection,
  onToggle,
}) => {
  const nextLabel =
    activeSection === "ticket" ? "Jestem pracownikiem" : "Zglos problem";

  return (
    <div className="mt-8 text-center">
      <button
        type="button"
        onClick={onToggle}
        className="inline-flex items-center justify-center rounded-full bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-400 dark:bg-blue-500 dark:hover:bg-blue-400"
        aria-live="polite"
      >
        {nextLabel}
      </button>
    </div>
  );
};

