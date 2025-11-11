import * as React from "react";

import { TicketAddForm } from "~/modules/unauthenticated/ticket-add";
import { TicketChatModal } from "./ticket-chat-modal";

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
  const [chatTicketId, setChatTicketId] = React.useState<string | null>(null);
  const latestTicketIdRef = React.useRef<string | null>(null);

  const visibilityClasses = React.useMemo(() => {
    if (forceVisibleOnDesktop) {
      return isActive
        ? "block md:flex-1"
        : "hidden md:flex-1 md:block";
    }

    return isActive ? "block md:flex-1" : "hidden";
  }, [forceVisibleOnDesktop, isActive]);

  const isChatOpen = chatTicketId !== null;

  const handleCloseChat = React.useCallback(() => {
    setChatTicketId(null);
    latestTicketIdRef.current = null;
  }, []);

  const handleTicketCreated = React.useCallback((ticketId: string) => {
    latestTicketIdRef.current = ticketId;
    setChatTicketId(ticketId);
  }, []);

  const handleNavigateToChat = React.useCallback((path: string) => {
    if (latestTicketIdRef.current) {
      setChatTicketId(latestTicketIdRef.current);
      return;
    }

    const extractedId = path.split("/").filter(Boolean).pop();

    if (extractedId) {
      latestTicketIdRef.current = extractedId;
      setChatTicketId(extractedId);
    }
  }, []);

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
            Wypełnij formularz, aby rozpoczął się czat z naszym zespołem.
            Możesz podać tylko te dane, które chcesz udostępnić.
          </p>
        </div>

        <TicketAddForm
          onTicketCreated={handleTicketCreated}
          navigate={handleNavigateToChat}
        />

        {layout === "columns" ? (
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Po wysłaniu formularza zostaniesz automatycznie przekierowany do
            czatu z naszym konsultantem.
          </p>
        ) : null}
      </div>
      <TicketChatModal
        isOpen={isChatOpen}
        ticketId={chatTicketId}
        onClose={handleCloseChat}
      />
    </section>
  );
};

