import { betterAuth } from "better-auth";
import { drizzleAdapter } from "better-auth/adapters/drizzle";
import { twoFactor } from "better-auth/plugins"
import {twoFactor as twoFactorTable } from "../db/schema"

import { db } from "../config/database";
import {
    users,
    accounts,
    sessions,
    verificationTokens,
} from "../db/schema";

export const auth = betterAuth({
    adapter: drizzleAdapter(db, {
        provider: "mysql",
        usePlural: true,
        schema: {
            users,
            accounts,
            sessions,
            verificationTokens,
            twoFactorTable
        }
    }),
    plugins: [
        twoFactor()
    ],


    emailAndPassword: {
        enabled: true,
    },

    session: {
        expiresIn: 60 * 60 * 24 * 7, // 7 days
    }
});
