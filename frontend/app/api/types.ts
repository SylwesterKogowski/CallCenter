// Plik odpowiedzialny za wspólne typy domenowe używane w hakach API.

export type TicketStatus =
  | "waiting"
  | "in_progress"
  | "completed"
  | "awaiting_response"
  | "awaiting_client"
  | "closed";

export type TicketPriority = "low" | "normal" | "high" | "urgent";

export interface TicketCategory {
  id: string;
  name: string;
  description?: string;
  defaultResolutionTimeMinutes: number;
}

export interface Client {
  id: string;
  name: string;
  email?: string;
  phone?: string;
}

export interface TicketNote {
  id: string;
  content: string;
  createdAt: string;
  createdBy: string;
}


