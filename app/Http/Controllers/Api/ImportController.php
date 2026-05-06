<?php

namespace App\Http\Controllers\Api;

use App\Actions\Imports\ProcessTransactionImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportLogResource;
use App\Http\Resources\ImportResource;
use App\Models\Import as TransactionImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $imports = TransactionImport::query()
            ->latest()
            ->paginate($this->perPage($request));

        return ImportResource::collection($imports);
    }

    public function store(StoreImportRequest $request, ProcessTransactionImport $processImport): JsonResponse
    {
        $import = $processImport->handle($request->uploadedFile());

        return (new ImportResource($import))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, TransactionImport $import): JsonResponse
    {
        $logs = $import->logs()
            ->latest()
            ->paginate($this->perPage($request), ['*'], 'logs_page');

        $data = (new ImportResource($import))->resolve($request);
        $data['logs'] = ImportLogResource::collection($logs)->resolve($request);
        $data['logs_meta'] = [
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
            'from' => $logs->firstItem(),
            'to' => $logs->lastItem(),
        ];

        return response()->json([
            'data' => $data,
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 10), 1), 50);
    }
}
