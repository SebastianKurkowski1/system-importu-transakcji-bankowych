<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
