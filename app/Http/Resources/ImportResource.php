<?php

namespace App\Http\Resources;

use App\Enums\ImportStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'total_records' => $this->total_records,
            'successful_records' => $this->successful_records,
            'failed_records' => $this->failed_records,
            'status' => $this->status instanceof ImportStatus ? $this->status->value : $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'logs' => $this->relationLoaded('logs')
                ? ImportLogResource::collection($this->logs)
                : [],
        ];
    }
}
