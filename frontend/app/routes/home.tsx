import type { Route } from "./+types/home";

import { LandingPage } from "../pages/unauthenticated/landing-page";

export const meta: Route.MetaFunction = () => [
  { title: "Call Center | Zgłoś problem lub zaloguj się" },
  {
    name: "description",
    content:
      "Utwórz nowe zgłoszenie dla naszego zespołu wsparcia lub zaloguj się jako pracownik, aby zarządzać ticketami klientów.",
  },
];

export default function Home() {
  return <LandingPage />;
}
