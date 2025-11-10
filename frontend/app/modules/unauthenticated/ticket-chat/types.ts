import type { TicketDetails, TicketMessage } from "~/api/tickets";
import type { TicketStatus } from "~/api/types";

export type ChatConnectionStatus =
  | "connecting"
  | "connected"
  | "disconnected"
  | "error";

export interface ChatErrors {
  message?: string;
  connection?: string;
  api?: string;
  general?: string;
}

export interface TicketChatTypingState {
  isTyping: boolean;
  workerName?: string;
}

export type TicketChatMessage = TicketMessage;

export type TicketChatDetails = TicketDetails;

export type TicketChatStatus = TicketStatus;
