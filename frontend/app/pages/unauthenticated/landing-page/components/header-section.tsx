import * as React from "react";

interface HeaderSectionProps {
  logoSrc?: string;
  title?: string;
  subtitle?: string;
  description?: string;
}

export const HeaderSection: React.FC<HeaderSectionProps> = ({
  logoSrc,
  title = "Call Center",
  subtitle,
  description,
}) => {
  return (
    <header
      className="space-y-6"
      aria-label="Nagłówek strony"
      role="banner"
    >
      <div className="flex flex-col gap-4 text-center md:flex-row md:items-center md:justify-between md:text-left">
        <div className="flex items-center justify-center md:justify-start">
          {logoSrc ? (
            <img
              src={logoSrc}
              alt={title ?? "Call Center"}
              className="h-12 w-auto"
              loading="lazy"
            />
          ) : (
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-blue-600 text-lg font-semibold text-white shadow-lg">
              CC
            </div>
          )}
        </div>
        <div className="space-y-2">
          {title ? (
            <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">
              {title}
            </h1>
          ) : null}
          {subtitle ? (
            <p className="text-lg font-medium text-blue-600 dark:text-blue-400">
              {subtitle}
            </p>
          ) : null}
          {description ? (
            <p className="max-w-2xl text-base text-slate-700 dark:text-slate-300">
              {description}
            </p>
          ) : null}
        </div>
      </div>
    </header>
  );
};

