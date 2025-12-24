import {
    mysqlTable,
    varchar,
    text,
} from "drizzle-orm/mysql-core";

export const twoFactor = mysqlTable("two_factor", {
    id: varchar("id", { length: 36 }).primaryKey(),
    userId: varchar("user_id", { length: 36 }).notNull(),
    secret: varchar("secret", { length: 255 }).notNull(),
    backupCodes: text("backup_codes"),
});
