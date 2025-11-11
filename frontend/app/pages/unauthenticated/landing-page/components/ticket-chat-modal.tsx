import * as React from "react";

import { TicketChat } from "~/modules/unauthenticated/ticket-chat";

interface TicketChatModalProps {
  isOpen: boolean;
  ticketId: string | null;
  onClose: () => void;
}

export const TicketChatModal: React.FC<TicketChatModalProps> = ({
  isOpen,
  ticketId,
  onClose,
}) => {
  const modalContentRef = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    if (!isOpen || !ticketId || typeof document === "undefined") {
      return;
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        onClose();
      }
    };

    document.addEventListener("keydown", handleKeyDown);

    return () => {
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [isOpen, onClose, ticketId]);

  React.useEffect(() => {
    if (!isOpen || !ticketId || typeof document === "undefined") {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [isOpen, ticketId]);

  React.useEffect(() => {
    if (!isOpen || !ticketId || !modalContentRef.current) {
      return;
    }

    modalContentRef.current.focus();
  }, [isOpen, ticketId]);

  if (!isOpen || !ticketId) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
      role="presentation"
      onClick={onClose}
    >
      <div
        ref={modalContentRef}
        className="relative max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200 focus:outline-none dark:bg-slate-900 dark:ring-slate-700"
        role="dialog"
        aria-modal="true"
        aria-label="Czat z zespołem wsparcia"
        tabIndex={-1}
        onClick={(event) => {
          event.stopPropagation();
        }}
      >
        <button
          type="button"
          onClick={onClose}
          className="absolute right-4 top-4 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
        >
          Zamknij
        </button>
        <TicketChat ticketId={ticketId} />
      </div>
    </div>
  );
};


