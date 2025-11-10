import type { Route } from "./+types/home";

import { LandingPage } from "../pages/unauthenticated/landing-page";

export const meta: Route.MetaFunction = () => [
  { title: "Call Center | Zglos problem lub zaloguj sie" },
  {
    name: "description",
    content:
      "Utworz nowe zgloszenie dla naszego zespolu wsparcia lub zaloguj sie jako pracownik, aby zarzadzac ticketami klientow.",
  },
];

export default function Home() {
  return <LandingPage />;
}
