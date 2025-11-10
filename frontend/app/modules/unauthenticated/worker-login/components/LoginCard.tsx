import * as React from "react";

export interface LoginCardProps {
  title?: string;
  children: React.ReactNode;
}

export const LoginCard: React.FC<LoginCardProps> = ({ title, children }) => {
  return (
    <section className="mx-auto mt-16 max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-md dark:border-slate-800 dark:bg-slate-900">
      {title ? (
        <header className="mb-6 text-center">
          <h1 className="text-2xl font-semibold text-slate-900 dark:text-slate-100">
            {title}
          </h1>
        </header>
      ) : null}
      {children}
    </section>
  );
};


