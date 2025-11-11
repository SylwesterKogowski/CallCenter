import { type RouteConfig, index, layout, prefix, route } from "@react-router/dev/routes";

export default [
    index("routes/home.tsx"),
    layout("routes/worker.tsx",[
        ...prefix("worker",[
            index("pages/worker/schedule/schedule-page.tsx")
        ]),
    ]),
] satisfies RouteConfig;
