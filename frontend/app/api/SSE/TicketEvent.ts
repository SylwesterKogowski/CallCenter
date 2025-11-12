export interface TicketEvent<T> {
  type: "message" | "status_changed" | "ticket_removed" | "ticket_added" | "ticket_status_changed" | "ticket_updated";
  data: T;
  ticketId: string;
  timestamp: string;
  workerId?: string;
}