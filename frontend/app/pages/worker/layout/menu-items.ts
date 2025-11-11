import type { MenuItem } from "./components/navigation-sidebar";

export const MENU_ITEMS: MenuItem[] = [
  {
    label: "Grafik",
    path: "/worker",
    icon: "🗓️",
  },
  {
    label: "Planowanie",
    path: "/worker/planning",
    icon: "🗂️",
  },
  {
    label: "Dostępność",
    path: "/worker/availability",
    icon: "⏰",
  },
  {
    label: "Monitoring",
    path: "/worker/monitoring",
    icon: "📊",
    requiresManager: true,
  },
  {
    label: "Dodaj pracownika",
    path: "/worker/register",
    icon: "➕",
    requiresManager: true,
  },
];


