import * as React from "react";
import { NavLink } from "react-router";

export interface MenuItem {
  label: string;
  path: string;
  icon?: React.ReactNode;
  requiresManager?: boolean;
}

export interface NavigationSidebarProps {
  items: MenuItem[];
  currentPath: string;
  isManager: boolean;
  onNavigate: (path: string) => void;
  onLogout: () => void;
  isOpen?: boolean;
  onClose?: () => void;
}

export const NavigationSidebar: React.FC<NavigationSidebarProps> = ({
  items,
  currentPath,
  isManager,
  onNavigate,
  onLogout,
  isOpen = false,
  onClose,
}) => {
  const availableItems = React.useMemo(
    () => items.filter((item) => !item.requiresManager || isManager),
    [items, isManager],
  );

  const isItemCurrent = React.useCallback(
    (path: string) =>
      currentPath === path || currentPath.startsWith(`${path}/`),
    [currentPath],
  );

  const handleNavigate = React.useCallback(
    (path: string) => {
      onNavigate(path);
      onClose?.();
    },
    [onClose, onNavigate],
  );

  const createItemClassName = React.useCallback(
    (isActive: boolean) =>
      [
        "flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition",
        isActive
          ? "bg-blue-600 text-white shadow"
          : "text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800",
      ].join(" "),
    [],
  );

  return (
    <aside
      className={[
        "fixed inset-y-0 left-0 z-40 w-72 transform bg-white px-4 py-6 shadow-xl transition-transform duration-200 ease-in-out dark:bg-slate-950 lg:static lg:translate-x-0 lg:shadow-none",
        isOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0",
      ].join(" ")}
      aria-label="Menu nawigacyjne"
    >
      <div className="flex items-center justify-between px-2">
        <span className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Panel pracownika
        </span>
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
          aria-label="Zamknij menu nawigacji"
        >
          Zamknij
        </button>
      </div>

      <nav className="mt-6 flex flex-col gap-1" role="navigation">
        {availableItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            end={item.path === "/worker"}
            aria-current={isItemCurrent(item.path) ? "page" : undefined}
            className={({ isActive }) => createItemClassName(isActive)}
            onClick={(event) => {
              event.preventDefault();
              handleNavigate(item.path);
            }}
          >
            {item.icon ? <span className="text-xl">{item.icon}</span> : null}
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>

      <div className="mt-10 border-t border-slate-200 pt-4 dark:border-slate-800">
        <button
          type="button"
          onClick={() => {
            onLogout();
            onClose?.();
          }}
          className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          Wyloguj się
        </button>
      </div>
    </aside>
  );
};


