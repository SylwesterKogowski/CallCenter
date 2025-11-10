import * as React from "react";

export interface MessageInputProps {
  onSend: (content: string) => Promise<void>;
  isLoading: boolean;
  isDisabled?: boolean;
  maxLength?: number;
  placeholder?: string;
  error?: string;
}

const DEFAULT_MAX_LENGTH = 5000;

export const MessageInput: React.FC<MessageInputProps> = ({
  onSend,
  isLoading,
  isDisabled,
  maxLength = DEFAULT_MAX_LENGTH,
  placeholder,
  error,
}) => {
  const [value, setValue] = React.useState("");
  const textAreaRef = React.useRef<HTMLTextAreaElement>(null);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (isLoading || isDisabled) {
      return;
    }

    const currentValue = value;

    try {
      await onSend(currentValue);
      setValue("");
      textAreaRef.current?.focus();
    } catch (submissionError) {
      // onSend is responsible for reporting errors. We keep the value to let the user retry.
      console.error("Message submission failed", submissionError);
    }
  };

  const handleKeyDown = (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      event.currentTarget.form?.requestSubmit();
    }
  };

  const remainingCharacters = maxLength - value.length;

  return (
    <form className="space-y-3" onSubmit={handleSubmit} noValidate>
      <label className="block text-sm font-medium text-slate-700 dark:text-slate-200" htmlFor="chat-message">
        Wiadomosc dla zespolu wsparcia
      </label>

      <textarea
        id="chat-message"
        ref={textAreaRef}
        className="min-h-[120px] w-full resize-y rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-900 shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-emerald-400 dark:focus:ring-emerald-800"
        placeholder={placeholder ?? "Napisz tutaj, aby kontynuowac rozmowe..."}
        value={value}
        onChange={(event) => setValue(event.target.value)}
        onKeyDown={handleKeyDown}
        disabled={isLoading || isDisabled}
        maxLength={maxLength}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? "chat-message-error" : undefined}
        aria-label="Pole wprowadzania wiadomosci"
      />

      <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
        <span>{remainingCharacters >= 0 ? `Pozostalo ${remainingCharacters} znakow` : "Brak pozostalych znakow"}</span>
        <span>Enter wysyla wiadomosc, Shift+Enter dodaje nowa linie</span>
      </div>

      {error ? (
        <p
          id="chat-message-error"
          className="text-sm text-red-600 dark:text-red-400"
          role="alert"
          aria-live="assertive"
        >
          {error}
        </p>
      ) : null}

      <div className="flex justify-end">
        <button
          type="submit"
          className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-emerald-700 focus:outline-none focus-visible:ring focus-visible:ring-emerald-300 disabled:cursor-not-allowed disabled:bg-emerald-400"
          disabled={isLoading || isDisabled}
        >
          {isLoading ? "Wysylanie..." : "Wyslij wiadomosc"}
        </button>
      </div>
    </form>
  );
};
