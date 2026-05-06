import type { ImportItem, PaginationMeta } from '@/types';

type ApiResponse<T> = {
    data: T;
};

type PaginatedApiResponse<T> = ApiResponse<T> & {
    meta: PaginationMeta;
};

type ErrorResponse = {
    message?: unknown;
};

export type PaginatedResult<T> = {
    data: T;
    meta: PaginationMeta;
};

const isErrorResponse = (payload: unknown): payload is ErrorResponse => {
    return typeof payload === 'object' && payload !== null && 'message' in payload;
};

const fetchJson = async <T>(url: string, options?: RequestInit): Promise<T> => {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            ...(options?.headers ?? {}),
        },
        ...options,
    });

    if (!response.ok) {
        const payload: unknown = await response.json().catch(() => null);
        const message = isErrorResponse(payload) && typeof payload.message === 'string' ? payload.message : 'Request failed.';

        throw new Error(message);
    }

    const payload: unknown = await response.json();

    return payload as T;
};

export const fetchImports = async (page = 1): Promise<PaginatedResult<ImportItem[]>> => {
    const payload = await fetchJson<PaginatedApiResponse<ImportItem[]>>(`/api/imports?page=${page}`);

    return {
        data: payload.data,
        meta: payload.meta,
    };
};

export const fetchImport = async (id: string, logsPage = 1): Promise<ImportItem> => {
    const payload = await fetchJson<ApiResponse<ImportItem>>(`/api/imports/${id}?logs_page=${logsPage}`);

    return payload.data;
};

export const uploadImport = async (file: File): Promise<ImportItem> => {
    const formData = new FormData();
    formData.append('file', file);

    const payload = await fetchJson<ApiResponse<ImportItem>>('/api/imports', {
        method: 'POST',
        body: formData,
    });

    return payload.data;
};
