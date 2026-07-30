import { env } from "../utils/env";
import {getAuthHeader} from "./auth.ts";
import {parseError} from "./errors.ts";

type FetchOptions = RequestInit & {
    auth?: boolean;
};

export async function apiFetch<T>(
    path: string,
    options: FetchOptions = {}
): Promise<T> {
    const { auth = true, headers, ...rest } = options;

    const res = await fetch(`${env.apiBaseUrl}${path}`, {
        ...rest,
        headers: {
            "Content-Type": "application/json",
            ...(auth ? getAuthHeader() : {}),
            ...headers,
        },
        credentials: "include",
    });

    if (!res.ok) {
        throw await parseError(res);
    }

    return res.json();
}
