<script setup lang="ts">
import { fetchImport, fetchImports, uploadImport } from '@/api/imports';
import { Button } from '@/components/ui/button';
import type { ImportDetails, ImportItem, ImportStatus, PaginationMeta } from '@/types';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, ChevronLeft, ChevronRight, FileUp, ListChecks, RefreshCw, Upload, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const emptyPagination: PaginationMeta = {
    current_page: 1,
    from: null,
    last_page: 1,
    per_page: 10,
    to: null,
    total: 0,
};

type ImportViewItem = ImportItem & Partial<Pick<ImportDetails, 'logs_meta'>>;

const imports = ref<ImportViewItem[]>([]);
const importsMeta = ref<PaginationMeta>({ ...emptyPagination });
const selectedFile = ref<File | null>(null);
const selectedImportId = ref<string | null>(null);
const isLoadingImports = ref(false);
const isLoadingDetails = ref(false);
const isUploading = ref(false);
const errorMessage = ref<string | null>(null);
const fileInputKey = ref(0);

const selectedImport = computed(() => imports.value.find((item) => item.id === selectedImportId.value) ?? imports.value[0] ?? null);
const selectedLogs = computed(() => selectedImport.value?.logs ?? []);
const logsMeta = computed(() => selectedImport.value?.logs_meta ?? emptyPagination);
const importPages = computed(() => pageNumbers(importsMeta.value));
const logPages = computed(() => pageNumbers(logsMeta.value));

const statusMeta = {
    success: {
        label: 'Success',
        class: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        icon: CheckCircle2,
    },
    partial: {
        label: 'Partial',
        class: 'border-amber-200 bg-amber-50 text-amber-700',
        icon: AlertTriangle,
    },
    failed: {
        label: 'Failed',
        class: 'border-red-200 bg-red-50 text-red-700',
        icon: XCircle,
    },
} satisfies Record<ImportStatus, { label: string; class: string; icon: typeof CheckCircle2 }>;

const loadImports = async (page = importsMeta.value.current_page) => {
    isLoadingImports.value = true;
    errorMessage.value = null;

    try {
        const result = await fetchImports(page);
        imports.value = result.data;
        importsMeta.value = result.meta;

        if (!selectedImportId.value || !imports.value.some((item) => item.id === selectedImportId.value)) {
            selectedImportId.value = imports.value[0]?.id ?? null;
        }

        if (selectedImportId.value) {
            await loadImportDetails(selectedImportId.value);
        }
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Could not load imports.';
    } finally {
        isLoadingImports.value = false;
    }
};

const loadImportDetails = async (id: string, logsPage = 1) => {
    selectedImportId.value = id;
    isLoadingDetails.value = true;
    errorMessage.value = null;

    try {
        const item = await fetchImport(id, logsPage);
        const index = imports.value.findIndex((item) => item.id === id);

        if (index === -1) {
            imports.value = [item, ...imports.value];
        } else {
            imports.value[index] = item;
        }
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Could not load import logs.';
    } finally {
        isLoadingDetails.value = false;
    }
};

const pageNumbers = (meta: PaginationMeta): number[] => {
    return Array.from({ length: meta.last_page }, (_, index) => index + 1);
};

const selectedPage = (event: Event): number => {
    return event.target instanceof HTMLSelectElement ? Number(event.target.value) : 1;
};

const handleFileChange = (event: Event) => {
    selectedFile.value = event.target instanceof HTMLInputElement ? (event.target.files?.[0] ?? null) : null;
};

const handleSubmit = async () => {
    const file = selectedFile.value;

    if (!file) {
        return;
    }

    isUploading.value = true;
    errorMessage.value = null;

    try {
        const item = await uploadImport(file);

        imports.value = [item, ...imports.value.filter((existingItem) => existingItem.id !== item.id)];
        selectedImportId.value = item.id;
        selectedFile.value = null;
        fileInputKey.value++;
        await loadImports(1);
        await loadImportDetails(item.id);
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Could not import file.';
    } finally {
        isUploading.value = false;
    }
};

onMounted(() => {
    void loadImports();
});
</script>

<template>
    <Head title="Transaction imports" />

    <main class="min-h-screen bg-slate-50 text-slate-950">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-1 border-b border-slate-200 pb-5">
                <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Bank transaction import system</p>
                <h1 class="text-2xl font-semibold tracking-normal text-slate-950 sm:text-3xl">Imports</h1>
            </header>

            <div v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ errorMessage }}
            </div>

            <section class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <form class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="handleSubmit">
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-slate-950 text-white">
                            <FileUp class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">File upload</h2>
                            <p class="mt-1 text-sm text-slate-500">CSV, JSON or XML</p>
                        </div>
                    </div>

                    <label
                        for="transaction-file"
                        class="mt-5 flex min-h-40 cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition hover:border-slate-400 hover:bg-slate-100"
                    >
                        <Upload class="size-8 text-slate-500" />
                        <span class="text-sm font-medium text-slate-900">
                            {{ selectedFile ? selectedFile.name : 'Choose a file to import' }}
                        </span>
                        <span class="text-xs text-slate-500">.csv, .json, .xml</span>
                        <input
                            :key="fileInputKey"
                            id="transaction-file"
                            class="sr-only"
                            type="file"
                            accept=".csv,.json,.xml,text/csv,application/json,application/xml,text/xml"
                            :disabled="isUploading"
                            @change="handleFileChange"
                        />
                    </label>

                    <div class="mt-5 flex items-center justify-between gap-3">
                        <p class="min-w-0 truncate text-sm text-slate-500">
                            {{ selectedFile ? `${Math.ceil(selectedFile.size / 1024)} KB` : 'No file selected' }}
                        </p>
                        <Button type="submit" :disabled="!selectedFile || isUploading">
                            <Upload class="size-4" />
                            {{ isUploading ? 'Importing...' : 'Import' }}
                        </Button>
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Import history</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ importsMeta.total }} imports</p>
                        </div>
                        <Button variant="ghost" size="icon-sm" type="button" :disabled="isLoadingImports" @click="loadImports()">
                            <RefreshCw class="size-4" :class="{ 'animate-spin': isLoadingImports }" />
                        </Button>
                    </div>

                    <div v-if="imports.length" class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3 font-medium">File</th>
                                    <th class="px-5 py-3 font-medium">Records</th>
                                    <th class="px-5 py-3 font-medium">Successful</th>
                                    <th class="px-5 py-3 font-medium">Failed</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="item in imports"
                                    :key="item.id"
                                    class="cursor-pointer transition hover:bg-slate-50"
                                    :class="{ 'bg-slate-50': selectedImport?.id === item.id }"
                                    @click="loadImportDetails(item.id)"
                                >
                                    <td class="px-5 py-4 font-medium text-slate-950">{{ item.file_name }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ item.total_records }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ item.successful_records }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ item.failed_records }}</td>
                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                            :class="statusMeta[item.status].class"
                                        >
                                            <component :is="statusMeta[item.status].icon" class="size-3.5" />
                                            {{ statusMeta[item.status].label }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="importsMeta.total > importsMeta.per_page"
                        class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span>Page {{ importsMeta.current_page }} of {{ importsMeta.last_page }}</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                :disabled="isLoadingImports || importsMeta.current_page <= 1"
                                @click="loadImports(importsMeta.current_page - 1)"
                            >
                                <ChevronLeft class="size-4" />
                                Previous
                            </Button>
                            <label class="flex items-center gap-2">
                                <span class="text-slate-500">Go to</span>
                                <select
                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-950 shadow-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    :value="importsMeta.current_page"
                                    :disabled="isLoadingImports"
                                    @change="loadImports(selectedPage($event))"
                                >
                                    <option v-for="page in importPages" :key="page" :value="page">
                                        {{ page }}
                                    </option>
                                </select>
                            </label>
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                :disabled="isLoadingImports || importsMeta.current_page >= importsMeta.last_page"
                                @click="loadImports(importsMeta.current_page + 1)"
                            >
                                Next
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div v-if="!imports.length" class="px-5 py-12 text-center">
                        <ListChecks class="mx-auto mb-3 size-7 text-slate-400" />
                        <p class="text-sm font-medium text-slate-900">No imports yet</p>
                        <p class="mt-1 text-sm text-slate-500">Import summaries will appear here after upload.</p>
                    </div>
                </section>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Error logs</h2>
                        <p class="text-sm text-slate-500">{{ selectedImport ? selectedImport.file_name : 'No import selected' }}</p>
                    </div>
                    <span v-if="selectedImport" class="text-sm font-medium text-slate-600">
                        {{ isLoadingDetails ? 'Loading...' : `${logsMeta.total} errors` }}
                    </span>
                </div>

                <div v-if="selectedLogs.length" class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">Transaction ID</th>
                                <th class="px-5 py-3 font-medium">Error message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in selectedLogs" :key="log.id">
                                <td class="px-5 py-4 font-mono text-xs text-slate-700">{{ log.transaction_id ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-900">{{ log.error_message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="logsMeta.total > logsMeta.per_page"
                    class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span>Page {{ logsMeta.current_page }} of {{ logsMeta.last_page }}</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            :disabled="isLoadingDetails || logsMeta.current_page <= 1"
                            @click="selectedImport && loadImportDetails(selectedImport.id, logsMeta.current_page - 1)"
                        >
                            <ChevronLeft class="size-4" />
                            Previous
                        </Button>
                        <label class="flex items-center gap-2">
                            <span class="text-slate-500">Go to</span>
                            <select
                                class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-950 shadow-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                :value="logsMeta.current_page"
                                :disabled="isLoadingDetails"
                                @change="selectedImport && loadImportDetails(selectedImport.id, selectedPage($event))"
                            >
                                <option v-for="page in logPages" :key="page" :value="page">
                                    {{ page }}
                                </option>
                            </select>
                        </label>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            :disabled="isLoadingDetails || logsMeta.current_page >= logsMeta.last_page"
                            @click="selectedImport && loadImportDetails(selectedImport.id, logsMeta.current_page + 1)"
                        >
                            Next
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>

                <div v-if="!selectedLogs.length" class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-slate-900">No error logs</p>
                    <p class="mt-1 text-sm text-slate-500">Select an import with failed records to inspect the details.</p>
                </div>
            </section>
        </div>
    </main>
</template>
