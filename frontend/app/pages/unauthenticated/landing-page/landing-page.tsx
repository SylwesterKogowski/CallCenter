import * as React from "react";

import { FooterSection } from "./components/footer-section";
import { HeaderSection } from "./components/header-section";
import { LoginSection } from "./components/login-section";
import { NavigationTabs } from "./components/navigation-tabs";
import { TicketSection } from "./components/ticket-section";
import { ToggleButton } from "./components/toggle-button";

export type LandingPageLayout = "columns" | "tabs" | "toggle";
export type LandingPageSection = "ticket" | "login";

export interface LandingPageProps {
  defaultSection?: LandingPageSection;
  layout?: LandingPageLayout;
}

export const LandingPage: React.FC<LandingPageProps> = ({
  defaultSection = "ticket",
  layout = "columns",
}) => {
  const [activeSection, setActiveSection] =
    React.useState<LandingPageSection>(defaultSection);

  const isColumnsLayout = layout === "columns";
  const showTabs = layout === "tabs" || layout === "columns";
  const showToggle = layout === "toggle";

  const ticketSectionId = React.useId();
  const loginSectionId = React.useId();

  const handleSectionChange = React.useCallback((section: LandingPageSection) => {
    setActiveSection(section);
  }, []);

  const handleToggle = React.useCallback(() => {
    setActiveSection((prev) => (prev === "ticket" ? "login" : "ticket"));
  }, []);

  const ticketTitle = "Zglos problem";
  const loginTitle = "Logowanie dla pracownikow";

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <a
        href="#landing-main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-slate-900 dark:focus:bg-slate-800 dark:focus:text-white"
      >
        Przejdz do glownej tresci
      </a>

      <div className="mx-auto max-w-7xl px-4 pb-16 pt-12 sm:px-6 lg:px-8">
        <HeaderSection
          title="Call Center Support"
          subtitle="Szybka pomoc dla klientow"
          description="Utworz nowe zgloszenie lub zaloguj sie jako pracownik, aby zarzadzac sprawami klientow."
        />

        {showTabs ? (
          <NavigationTabs
            activeTab={activeSection}
            onTabChange={handleSectionChange}
            className={isColumnsLayout ? "md:hidden" : undefined}
            sectionIds={{ ticket: ticketSectionId, login: loginSectionId }}
          />
        ) : null}

        {showToggle ? (
          <ToggleButton activeSection={activeSection} onToggle={handleToggle} />
        ) : null}

        <main
          id="landing-main"
          className={`mt-10 flex flex-col gap-10 ${
            isColumnsLayout ? "md:flex-row md:items-start" : ""
          }`}
          role="main"
        >
          <TicketSection
            title={ticketTitle}
            sectionId={ticketSectionId}
            isActive={activeSection === "ticket"}
            forceVisibleOnDesktop={isColumnsLayout}
            layout={layout}
          />
          <LoginSection
            title={loginTitle}
            sectionId={loginSectionId}
            isActive={activeSection === "login"}
            forceVisibleOnDesktop={isColumnsLayout}
            layout={layout}
          />
        </main>
      </div>

      <FooterSection
        companyInfo={{
          name: "Call Center Sp. z o.o.",
          address: "ul. Przykladowa 123, 00-001 Warszawa",
          phone: "+48 123 456 789",
          email: "kontakt@callcenter.pl",
          links: [
            { label: "Polityka prywatnosci", url: "/privacy" },
            { label: "Regulamin", url: "/terms" },
            { label: "Kontakt", url: "/contact" },
          ],
        }}
      />
    </div>
  );
};

