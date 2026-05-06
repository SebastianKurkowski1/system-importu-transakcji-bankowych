<?php

namespace App\Actions\Imports;

use App\Enums\ImportStatus;
use App\Imports\TransactionFileParserResolver;
use App\Models\Import as TransactionImport;
use App\Models\Transaction;
use App\Rules\Iban;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class ProcessTransactionImport
{
    public function __construct(
        private readonly TransactionFileParserResolver $parserResolver,
    ) {}

    public function handle(UploadedFile $file): TransactionImport
    {
        $import = TransactionImport::query()->create([
            'file_name' => $file->getClientOriginalName(),
            'total_records' => 0,
            'successful_records' => 0,
            'failed_records' => 0,
            'status' => ImportStatus::Failed,
        ]);

        $totalRecords = 0;
        $successfulRecords = 0;
        $failedRecords = 0;

        try {
            $parser = $this->parserResolver->resolve($file);

            foreach ($parser->parse($file) as $record) {
                $totalRecords++;

                $normalized = $this->normalizeRecord($record);

                if ($normalized['_parse_error'] !== null) {
                    $failedRecords++;
                    $import->logs()->create([
                        'transaction_id' => $normalized['transaction_id'] ?: null,
                        'error_message' => $normalized['_parse_error'],
                    ]);

                    continue;
                }

                $validator = Validator::make($normalized, $this->rules());

                if ($validator->fails()) {
                    $failedRecords++;
                    $this->logValidationError($import, $normalized, $validator->errors()->all());

                    continue;
                }

                try {
                    Transaction::query()->create([
                        'import_id' => $import->id,
                        'transaction_id' => $normalized['transaction_id'],
                        'account_number' => strtoupper(str_replace(' ', '', $normalized['account_number'])),
                        'transaction_date' => $normalized['transaction_date'],
                        'amount' => $normalized['amount'],
                        'currency' => strtoupper($normalized['currency']),
                    ]);

                    $successfulRecords++;
                } catch (Throwable $exception) {
                    $failedRecords++;
                    $import->logs()->create([
                        'transaction_id' => $normalized['transaction_id'] ?: null,
                        'error_message' => $this->databaseErrorMessage($exception),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            $failedRecords++;
            $import->logs()->create([
                'transaction_id' => null,
                'error_message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'The import file could not be processed.',
            ]);
        }

        $import->update([
            'total_records' => $totalRecords,
            'successful_records' => $successfulRecords,
            'failed_records' => $failedRecords,
            'status' => $this->resolveStatus($successfulRecords, $failedRecords),
        ]);

        return $import->refresh()->load('logs');
    }

    private function normalizeRecord(array $record): array
    {
        return [
            'transaction_id' => $this->stringValue($record['transaction_id'] ?? null),
            'account_number' => $this->stringValue($record['account_number'] ?? null),
            'transaction_date' => $this->stringValue($record['transaction_date'] ?? null),
            'amount' => $record['amount'] ?? null,
            'currency' => $this->stringValue($record['currency'] ?? null),
            '_parse_error' => $this->stringValue($record['_parse_error'] ?? null) ?: null,
        ];
    }

    private function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string'],
            'account_number' => ['required', new Iban],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ];
    }

    private function logValidationError(TransactionImport $import, array $record, array $messages): void
    {
        $import->logs()->create([
            'transaction_id' => $record['transaction_id'] ?: null,
            'error_message' => implode(' ', $messages),
        ]);
    }

    private function resolveStatus(int $successfulRecords, int $failedRecords): ImportStatus
    {
        if ($successfulRecords > 0 && $failedRecords === 0) {
            return ImportStatus::Success;
        }

        if ($successfulRecords > 0) {
            return ImportStatus::Partial;
        }

        return ImportStatus::Failed;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function databaseErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException && str_contains(strtolower($exception->getMessage()), 'unique')) {
            return 'Transaction ID already exists.';
        }

        return 'Transaction could not be saved.';
    }
}
