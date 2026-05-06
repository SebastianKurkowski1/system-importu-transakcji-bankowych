<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasUuids;

    protected $fillable = [
        'file_name',
        'total_records',
        'successful_records',
        'failed_records',
        'status',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    protected function casts(): array
    {
        return [
            'total_records' => 'integer',
            'successful_records' => 'integer',
            'failed_records' => 'integer',
            'status' => ImportStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
