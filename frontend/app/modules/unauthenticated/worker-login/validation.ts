import type { LoginErrors } from "./types";

const LOGIN_REGEX = /^[\p{L}\p{N}._-]+$/u;

export const validateLogin = (login: string): string | undefined => {
  if (!login.trim()) {
    return "Login jest wymagany.";
  }

  if (login.length < 3) {
    return "Login musi mieć co najmniej 3 znaki.";
  }

  if (login.length > 255) {
    return "Login może mieć maksymalnie 255 znaków.";
  }

  if (!LOGIN_REGEX.test(login)) {
    return "Login może zawierać tylko litery, cyfry, kropki, myślniki i podkreślenia.";
  }

  return undefined;
};

export const validatePassword = (password: string): string | undefined => {
  if (!password) {
    return "Hasło jest wymagane.";
  }

  if (password.length < 8) {
    return "Hasło musi mieć co najmniej 8 znaków.";
  }

  return undefined;
};

export const validateCredentials = (
  login: string,
  password: string,
): LoginErrors => {
  const loginError = validateLogin(login);
  const passwordError = validatePassword(password);

  return {
    ...(loginError ? { login: loginError } : {}),
    ...(passwordError ? { password: passwordError } : {}),
  };
};


