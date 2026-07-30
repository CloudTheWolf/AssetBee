import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../client";

export function useCompanies() {
    return useQuery({
        queryKey: ["companies"],
        queryFn: () => apiFetch("/core/companies"),
    });
}
