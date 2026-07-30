export async function parseError(res: Response) {
    let body: any = null;

    try {
        body = await res.json();
    } catch {}

    return {
        status: res.status,
        message: body?.message ?? res.statusText,
    };
}
