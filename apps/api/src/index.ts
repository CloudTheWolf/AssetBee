import { app } from "./app";
import "dotenv/config";

Bun.serve({
    port: 3001,
    fetch: app.fetch,
});

console.log("AssetBee API running on http://localhost:3001");
