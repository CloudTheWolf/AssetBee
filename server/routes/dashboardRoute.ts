import { Hono } from 'hono'

export const dashboardRoute = new Hono()
    .get("/",(c) => c.json({"message": "Dashboard"}))
    //.post
    //.delete
    //.put