import mysql from "mysql2/promise";
import { drizzle } from "drizzle-orm/mysql2";
import { env } from "./env";

/**
 * MySQL / MariaDB connection pool
 */
export const pool = mysql.createPool({
    host: env.DB_HOST,
    port: env.DB_PORT,
    user: env.DB_USER,
    password: env.DB_PASSWORD,
    database: env.DB_NAME,

    connectionLimit: 10,
    enableKeepAlive: true,
});

/**
 * Drizzle ORM instance
 */
export const db = drizzle(pool);
