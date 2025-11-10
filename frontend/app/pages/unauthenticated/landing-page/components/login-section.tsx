import * as React from "react";

import { WorkerLoginForm } from "~/modules/unauthenticated/worker-login";

import type { LandingPageLayout } from "../landing-page";

interface LoginSectionProps {
  title: string;
  sectionId: string;
  isActive: boolean;
  forceVisibleOnDesktop?: boolean;
  layout: LandingPageLayout;
}

export const LoginSection: React.FC<LoginSectionProps> = ({
  title,
  sectionId,
  isActive,
  forceVisibleOnDesktop = false,
  layout,
}) => {
  const visibilityClasses = React.useMemo(() => {
    if (forceVisibleOnDesktop) {
      return isActive ? "block md:w-[400px]" : "hidden md:block md:w-[400px]";
    }

    return isActive ? "block md:w-[400px]" : "hidden";
  }, [forceVisibleOnDesktop, isActive]);

  return (
    <section
      id={sectionId}
      className={`${visibilityClasses} rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200 transition-all dark:bg-slate-900 dark:ring-slate-700`}
      aria-label={title}
      aria-hidden={!isActive && !forceVisibleOnDesktop}
      role="region"
    >
      <div className="space-y-4">
        <div>
          <p className="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Pracownicy
          </p>
          <h2 className="text-2xl font-bold text-slate-900 dark:text-slate-50">
            {title}
          </h2>
          <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
            Zaloguj sie, aby zarzadzac zgloszeniami klientow, przegladac
            historie rozmow i monitorowac obciazenie zespolu.
          </p>
        </div>

        <WorkerLoginForm title="Dostep dla pracownikow" />

        {layout === "columns" ? (
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Po pomyslnym logowaniu zostaniesz przekierowany do panelu pracownika.
          </p>
        ) : null}
      </div>
    </section>
  );
};

