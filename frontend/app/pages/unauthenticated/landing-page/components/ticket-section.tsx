import * as React from "react";

import { TicketAddForm } from "~/modules/unauthenticated/ticket-add";

import type { LandingPageLayout } from "../landing-page";

interface TicketSectionProps {
  title: string;
  sectionId: string;
  isActive: boolean;
  forceVisibleOnDesktop?: boolean;
  layout: LandingPageLayout;
}

export const TicketSection: React.FC<TicketSectionProps> = ({
  title,
  sectionId,
  isActive,
  forceVisibleOnDesktop = false,
  layout,
}) => {
  const visibilityClasses = React.useMemo(() => {
    if (forceVisibleOnDesktop) {
      return isActive
        ? "block md:flex-1"
        : "hidden md:flex-1 md:block";
    }

    return isActive ? "block md:flex-1" : "hidden";
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
          <p className="text-sm font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">
            Klienci
          </p>
          <h2 className="text-2xl font-bold text-slate-900 dark:text-slate-50">
            {title}
          </h2>
          <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
            Wypelnij formularz, aby rozpoczal sie czat z naszym zespolem.
            Mozesz podac tylko te dane, ktore chcesz udostepnic.
          </p>
        </div>

        <TicketAddForm />

        {layout === "columns" ? (
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Po wyslaniu formularza zostaniesz automatycznie przekierowany do
            czatu z naszym konsultantem.
          </p>
        ) : null}
      </div>
    </section>
  );
};

