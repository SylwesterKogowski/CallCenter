import * as React from "react";

import type { LandingPageSection } from "../landing-page";

interface NavigationTabsProps {
  activeTab: LandingPageSection;
  onTabChange: (tab: LandingPageSection) => void;
  className?: string;
  sectionIds?: {
    ticket?: string;
    login?: string;
  };
}

const tabs: Array<{ id: LandingPageSection; label: string; description: string }> =
  [
    {
      id: "ticket",
      label: "Zgłoś problem",
      description: "Dla klientów",
    },
    {
      id: "login",
      label: "Logowanie",
      description: "Dla pracowników",
    },
  ];

export const NavigationTabs: React.FC<NavigationTabsProps> = ({
  activeTab,
  onTabChange,
  className,
  sectionIds,
}) => {
  return (
    <nav
      className={`mt-8 rounded-full bg-white p-2 shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700 ${className ?? ""}`}
      aria-label="Nawigacja między sekcjami"
      role="navigation"
    >
      <div className="grid grid-cols-2 gap-2" role="tablist">
        {tabs.map((tab) => {
          const isActive = activeTab === tab.id;
          const baseClasses =
            "flex flex-col items-center justify-center rounded-full px-4 py-3 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 sm:flex-row sm:gap-2";
          const activeClasses = isActive
            ? "bg-blue-600 text-white shadow-md dark:bg-blue-500"
            : "text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800";

          return (
            <button
              key={tab.id}
              type="button"
              role="tab"
              aria-selected={isActive}
              aria-controls={
                tab.id === "ticket" ? sectionIds?.ticket : sectionIds?.login
              }
              className={`${baseClasses} ${activeClasses}`}
              onClick={() => onTabChange(tab.id)}
            >
              <span>{tab.label}</span>
              <span className="text-xs font-normal text-slate-500 dark:text-slate-300 sm:text-sm">
                {tab.description}
              </span>
            </button>
          );
        })}
      </div>
    </nav>
  );
};

