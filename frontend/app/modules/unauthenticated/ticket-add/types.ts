export interface ClientData {
  email?: string;
  phone?: string;
  firstName?: string;
  lastName?: string;
}

export interface ClientDataErrors {
  email?: string;
  phone?: string;
  firstName?: string;
  lastName?: string;
  general?: string;
}

export interface TicketDetailsData {
  title?: string;
  description?: string;
}

export interface TicketDetailsErrors {
  title?: string;
  description?: string;
}

export interface TicketAddFormValues {
  client: ClientData;
  categoryId?: string;
  title?: string;
  description?: string;
}

export interface FormErrors {
  client?: ClientDataErrors;
  category?: string;
  title?: string;
  description?: string;
  general?: string;
}


