import {
    mysqlTable,
    varchar,
    datetime,
    boolean,
    text,
} from "drizzle-orm/mysql-core";

import { sql } from "drizzle-orm";

/**
 * Users
 * Extend this later with:
 * - system_user
 * - default_company_id
 */
export const users = mysqlTable("users", {
    id: varchar("id", { length: 36 }).primaryKey(),

    email: varchar("email", { length: 255 }).notNull().unique(),
    emailVerified: datetime("email_verified", { mode: "date" }),

    name: varchar("name", { length: 255 }),
    image: text("image"),

    passwordHash: varchar("password_hash", { length: 255 }),

    locked: boolean("locked").notNull().default(false),
    deleted: boolean("deleted").notNull().default(false),
    forcePasswordReset: boolean("force_password_reset").notNull().default(false),

    // ✅ REQUIRED by Better Auth MFA
    twoFactorEnabled: boolean("two_factor_enabled")
        .notNull()
        .default(false),

    createdAt: datetime("created_at", { mode: "date" })
        .notNull()
        .default(sql`CURRENT_TIMESTAMP`),
});

/**
 * OAuth accounts
 */
export const accounts = mysqlTable("accounts", {
    id: varchar("id", { length: 36 }).primaryKey(),
    userId: varchar("user_id", { length: 36 }).notNull(),
    provider: varchar("provider", { length: 50 }).notNull(),
    providerAccountId: varchar("provider_account_id", {
        length: 255,
    }).notNull(),

    accessToken: text("access_token"),
    refreshToken: text("refresh_token"),
    expiresAt: datetime("expires_at"),

    createdAt: datetime("created_at").default(sql`CURRENT_TIMESTAMP`),
});

/**
 * Sessions
 */
export const sessions = mysqlTable("sessions", {
    id: varchar("id", { length: 36 }).primaryKey(),
    userId: varchar("user_id", { length: 36 }).notNull(),
    expiresAt: datetime("expires_at").notNull(),

    createdAt: datetime("created_at").default(sql`CURRENT_TIMESTAMP`),
});

/**
 * Verification tokens (email, etc — future use)
 */
export const verificationTokens = mysqlTable("verification_tokens", {
    identifier: varchar("identifier", { length: 255 }).notNull(),
    token: varchar("token", { length: 255 }).notNull(),
    expiresAt: datetime("expires_at").notNull(),
});
