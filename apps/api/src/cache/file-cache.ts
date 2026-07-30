import { mkdir, readFile, writeFile, unlink } from "fs/promises";
import path from "path";
import { CacheProvider } from "./cache.interface";

export class FileCache implements CacheProvider {
    private dir = ".cache";

    private file(key: string) {
        return path.join(this.dir, `${key}.json`);
    }

    async get<T>(key: string): Promise<T | null> {
        try {
            const raw = await readFile(this.file(key), "utf-8");
            return JSON.parse(raw).value;
        } catch {
            return null;
        }
    }

    async set<T>(key: string, value: T) {
        await mkdir(this.dir, { recursive: true });
        await writeFile(this.file(key), JSON.stringify({ value }));
    }

    async delete(key: string) {
        try {
            await unlink(this.file(key));
        } catch {}
    }
}
