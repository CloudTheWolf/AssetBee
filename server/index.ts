import app from './app';
const host = process.env.HOST || '0.0.0.0';
Bun.serve({
    hostname: host,
    fetch: app.fetch
});
console.log("Server is running");

