export type ValidationField = "startTime" | "endTime" | "overlap" | "order";

export interface ValidationError {
  timeSlotId: string | null;
  field: ValidationField;
  message: string;
}


