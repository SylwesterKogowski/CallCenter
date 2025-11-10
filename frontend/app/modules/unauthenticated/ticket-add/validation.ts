import type {
  ClientData,
  ClientDataErrors,
  FormErrors,
  TicketAddFormValues,
  TicketDetailsData,
  TicketDetailsErrors,
} from "./types";

import type { TicketCategory } from "~/api/types";

const EMAIL_REGEX =
  /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;

const PHONE_REGEX = /^\+?[0-9 ()-]{6,20}$/;

const trim = (value?: string) => value?.trim() ?? "";

const isEmpty = (value?: string) => trim(value).length === 0;

const validateName = (value?: string, label?: string) => {
  const trimmed = trim(value);
  if (trimmed.length === 0) {
    return undefined;
  }

  if (trimmed.length < 2) {
    return `${label ?? "Pole"} musi zawierac co najmniej 2 znaki`;
  }

  if (trimmed.length > 100) {
    return `${label ?? "Pole"} nie moze przekraczac 100 znakow`;
  }

  return undefined;
};

export const validateClientData = (client: ClientData): ClientDataErrors => {
  const errors: ClientDataErrors = {};

  const hasEmail = !isEmpty(client.email);
  const hasPhone = !isEmpty(client.phone);

  if (!hasEmail && !hasPhone) {
    errors.general = "Wymagane jest podanie emaila lub telefonu";
  }

  if (hasEmail && !EMAIL_REGEX.test(trim(client.email))) {
    errors.email = "Nieprawidlowy format adresu email";
  }

  if (hasPhone && !PHONE_REGEX.test(trim(client.phone))) {
    errors.phone = "Nieprawidlowy format numeru telefonu";
  }

  const firstNameError = validateName(client.firstName, "Imie");
  if (firstNameError) {
    errors.firstName = firstNameError;
  }

  const lastNameError = validateName(client.lastName, "Nazwisko");
  if (lastNameError) {
    errors.lastName = lastNameError;
  }

  return errors;
};

export const validateTicketDetails = (
  details: TicketDetailsData,
): TicketDetailsErrors => {
  const errors: TicketDetailsErrors = {};

  const titleLength = trim(details.title).length;
  if (titleLength > 255) {
    errors.title = "Tytul nie moze przekraczac 255 znakow";
  }

  const descriptionLength = trim(details.description).length;
  if (descriptionLength > 5000) {
    errors.description = "Opis nie moze przekraczac 5000 znakow";
  }

  return errors;
};

const mergeClientErrors = (
  target: ClientDataErrors | undefined,
  source: ClientDataErrors | undefined,
): ClientDataErrors | undefined => {
  if (!target && !source) {
    return undefined;
  }

  return {
    ...(target ?? {}),
    ...(source ?? {}),
  };
};

export const hasFormErrors = (errors: FormErrors): boolean => {
  if (errors.general) {
    return true;
  }

  if (errors.category || errors.title || errors.description) {
    return true;
  }

  if (!errors.client) {
    return false;
  }

  return Object.keys(errors.client).length > 0;
};

export const validateTicketAddForm = (
  values: TicketAddFormValues,
  availableCategories?: TicketCategory[],
): FormErrors => {
  const clientErrors = validateClientData(values.client);
  const detailsErrors = validateTicketDetails({
    title: values.title,
    description: values.description,
  });

  const errors: FormErrors = {};

  if (Object.keys(clientErrors).length > 0) {
    errors.client = clientErrors;
  }

  if (detailsErrors.title) {
    errors.title = detailsErrors.title;
  }

  if (detailsErrors.description) {
    errors.description = detailsErrors.description;
  }

  const categoryId = values.categoryId?.trim();
  if (!categoryId) {
    errors.category = "Kategoria jest wymagana";
  } else if (
    Array.isArray(availableCategories) &&
    availableCategories.length > 0 &&
    !availableCategories.some((category) => category.id === categoryId)
  ) {
    errors.category = "Wybrana kategoria nie jest dostepna";
  }

  if (hasFormErrors(errors)) {
    return errors;
  }

  return errors;
};

export const mergeFormErrors = (...sources: Array<FormErrors | undefined>) => {
  const merged: FormErrors = {};

  sources.forEach((source) => {
    if (!source) {
      return;
    }

    if (source.general) {
      merged.general = source.general;
    }

    if (source.category) {
      merged.category = source.category;
    }

    if (source.title) {
      merged.title = source.title;
    }

    if (source.description) {
      merged.description = source.description;
    }

    merged.client = mergeClientErrors(merged.client, source.client);
  });

  if (merged.client && Object.keys(merged.client).length === 0) {
    delete merged.client;
  }

  return merged;
};


