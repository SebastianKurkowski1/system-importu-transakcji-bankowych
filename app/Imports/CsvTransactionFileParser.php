<?php

namespace App\Imports;

use App\Imports\Contracts\TransactionFileParser;
use Illuminate\Http\UploadedFile;
use SplFileObject;

class CsvTransactionFileParser implements TransactionFileParser
{
    public function supports(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'csv';
    }

    public function parse(UploadedFile $file): iterable
    {
        $csv = new SplFileObject($file->getRealPath());
        $csv->setCsvControl($this->detectDelimiter($file));
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = null;

        foreach ($csv as $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

            if ($headers === null) {
                $headers = array_map(fn ($header) => trim((string) $header), $row);

                continue;
            }

            if (count($headers) !== count($row)) {
                yield [
                    'transaction_id' => $row[0] ?? null,
                    '_parse_error' => 'CSV row does not match header column count.',
                ];

                continue;
            }

            yield array_combine($headers, $row);
        }
    }

    private function detectDelimiter(UploadedFile $file): string
    {
        $line = $this->firstNonEmptyLine($file);

        if ($line === null) {
            return ',';
        }

        $delimiters = [',', ';', "\t", '|', ':'];
        $bestDelimiter = ',';
        $bestColumnCount = 1;

        foreach ($delimiters as $delimiter) {
            $columnCount = count(str_getcsv($line, $delimiter));

            if ($columnCount > $bestColumnCount) {
                $bestDelimiter = $delimiter;
                $bestColumnCount = $columnCount;
            }
        }

        return $bestDelimiter;
    }

    private function firstNonEmptyLine(UploadedFile $file): ?string
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return null;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line !== '') {
                    return $line;
                }
            }

            return null;
        } finally {
            fclose($handle);
        }
    }
}
