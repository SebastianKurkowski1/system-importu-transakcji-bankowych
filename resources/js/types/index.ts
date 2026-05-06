export const importStatuses = ['success', 'partial', 'failed'] as const;

export type ImportStatus = (typeof importStatuses)[number];

export interface ImportLog {
    id: string;
    import_id: string;
    transaction_id: string | null;
    error_message: string;
    created_at: string;
    updated_at: string;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
}

export interface ImportItem {
    id: string;
    file_name: string;
    total_records: number;
    successful_records: number;
    failed_records: number;
    status: ImportStatus;
    created_at: string;
    updated_at: string;
    logs: ImportLog[];
}

export interface ImportDetails extends ImportItem {
    logs_meta: PaginationMeta;
}
