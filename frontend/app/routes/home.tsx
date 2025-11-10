import type { Route } from "./+types/home";
import { Welcome } from "../welcome/welcome";

export const meta: Route.MetaFunction = () => [
  { title: "New React Router App" },
  { name: "description", content: "Welcome to React Router!" },
];

export default function Home() {
  return <Welcome />;
}
