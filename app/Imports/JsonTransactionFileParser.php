<?php

namespace App\Imports;

use App\Imports\Contracts\TransactionFileParser;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class JsonTransactionFileParser implements TransactionFileParser
{
    public function supports(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'json';
    }

    public function parse(UploadedFile $file): iterable
    {
        try {
            $decoded = json_decode($file->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Invalid JSON file.', previous: $exception);
        }

        $records = $decoded['transactions'] ?? $decoded;

        if (! is_array($records)) {
            throw new RuntimeException('JSON file must contain an array of transactions.');
        }

        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new RuntimeException('JSON transaction record must be an object.');
            }

            yield $record;
        }
    }
}
