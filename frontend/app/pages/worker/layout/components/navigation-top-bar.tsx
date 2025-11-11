import * as React from "react";
import { NavLink } from "react-router";

import type { MenuItem } from "./navigation-sidebar";

interface NavigationTopBarProps {
  items: MenuItem[];
  currentPath: string;
  isManager: boolean;
  onNavigate: (path: string) => void;
}

export const NavigationTopBar: React.FC<NavigationTopBarProps> = ({
  items,
  currentPath,
  isManager,
  onNavigate,
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

  const createItemClassName = React.useCallback(
    (isActive: boolean) =>
      [
        "rounded-lg px-4 py-2 text-sm font-semibold transition",
        isActive
          ? "bg-blue-600 text-white"
          : "text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800",
      ].join(" "),
    [],
  );

  return (
    <nav
      className="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-800 dark:bg-slate-950"
      aria-label="Menu górne"
    >
      {availableItems.map((item) => (
        <NavLink
          key={item.path}
          to={item.path}
          end={item.path === "/worker"}
          aria-current={isItemCurrent(item.path) ? "page" : undefined}
          className={({ isActive }) => createItemClassName(isActive)}
          onClick={(event) => {
            event.preventDefault();
            onNavigate(item.path);
          }}
        >
          {item.label}
        </NavLink>
      ))}
    </nav>
  );
};


