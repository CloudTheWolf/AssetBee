import { Hono } from 'hono'
import { logger } from 'hono/logger'

import { dashboardRoute} from "./routes/dashboardRoute";
import {hardwareRoute} from "./routes/hardwareRoute";

const app = new Hono()

app.use('*',logger())

app.get('/', (c) => c.json({"message": "Success"}))

app.route("/api/dashboard", dashboardRoute);
app.route("/api/hardware", hardwareRoute);

export default app