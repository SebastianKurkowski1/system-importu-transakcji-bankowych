<?php

namespace Tests\Feature\Api;

use App\Enums\ImportStatus;
use App\Models\Import as TransactionImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_can_be_listed(): void
    {
        foreach (range(1, 12) as $index) {
            TransactionImport::query()->create([
                'file_name' => "transactions-{$index}.csv",
                'total_records' => 1,
                'successful_records' => 1,
                'failed_records' => 0,
                'status' => ImportStatus::Success,
            ]);
        }

        $response = $this->getJson('/api/imports?per_page=5&page=2');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 12);
    }

    public function test_import_file_can_be_uploaded(): void
    {
        $response = $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent(
                'valid_transactions.csv',
                file_get_contents(base_path('tests/Fixtures/imports/valid_transactions.csv')),
            ),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.file_name', 'valid_transactions.csv')
            ->assertJsonPath('data.total_records', 2)
            ->assertJsonPath('data.successful_records', 2)
            ->assertJsonPath('data.failed_records', 0)
            ->assertJsonPath('data.status', ImportStatus::Success->value)
            ->assertJsonCount(0, 'data.logs');

        $this->assertDatabaseHas('imports', [
            'file_name' => 'valid_transactions.csv',
            'status' => ImportStatus::Success->value,
        ]);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440000',
            'account_number' => 'PL61109010140000071219812874',
            'amount' => 150000,
            'currency' => 'PLN',
        ]);
    }

    public function test_csv_import_detects_common_delimiters(): void
    {
        $source = file_get_contents(base_path('tests/Fixtures/imports/valid_transactions.csv'));
        $delimiters = [
            'semicolon' => ';',
            'tab' => "\t",
            'pipe' => '|',
            'colon' => ':',
        ];

        foreach ($delimiters as $name => $delimiter) {
            $content = str_replace(',', $delimiter, $source);
            $content = str_replace(
                [
                    '550e8400-e29b-41d4-a716-446655440000',
                    '550e8400-e29b-41d4-a716-446655440001',
                ],
                [
                    "transaction-{$name}-1",
                    "transaction-{$name}-2",
                ],
                $content,
            );

            $response = $this->postJson('/api/imports', [
                'file' => UploadedFile::fake()->createWithContent("valid_transactions_{$name}.csv", $content),
            ]);

            $response
                ->assertCreated()
                ->assertJsonPath('data.total_records', 2)
                ->assertJsonPath('data.successful_records', 2)
                ->assertJsonPath('data.failed_records', 0)
                ->assertJsonPath('data.status', ImportStatus::Success->value);

            $this->assertDatabaseHas('transactions', [
                'transaction_id' => "transaction-{$name}-1",
                'currency' => 'PLN',
            ]);
        }
    }

    public function test_import_upload_requires_supported_file(): void
    {
        $response = $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->create('transactions.pdf', 1, 'application/pdf'),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_import_details_include_error_logs(): void
    {
        $import = TransactionImport::query()->create([
            'file_name' => 'transactions.xml',
            'total_records' => 12,
            'successful_records' => 0,
            'failed_records' => 12,
            'status' => ImportStatus::Failed,
        ]);

        foreach (range(1, 12) as $index) {
            $import->logs()->create([
                'transaction_id' => "TX-{$index}",
                'error_message' => 'Invalid amount.',
            ]);
        }

        $response = $this->getJson("/api/imports/{$import->id}?per_page=5&logs_page=2");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonCount(5, 'data.logs')
            ->assertJsonPath('data.logs_meta.current_page', 2)
            ->assertJsonPath('data.logs_meta.per_page', 5)
            ->assertJsonPath('data.logs_meta.total', 12);
    }

    public function test_json_import_can_be_processed(): void
    {
        $response = $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent(
                'valid_transactions.json',
                file_get_contents(base_path('tests/Fixtures/imports/valid_transactions.json')),
            ),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', ImportStatus::Success->value)
            ->assertJsonPath('data.successful_records', 2);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440000',
            'currency' => 'PLN',
        ]);
    }

    public function test_xml_import_can_be_processed(): void
    {
        $response = $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent(
                'valid_transactions.xml',
                file_get_contents(base_path('tests/Fixtures/imports/valid_transactions.xml')),
            ),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', ImportStatus::Success->value)
            ->assertJsonPath('data.successful_records', 2);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440001',
            'currency' => 'USD',
        ]);
    }

    public function test_sample_files_with_invalid_ibans_are_logged_as_failed_records(): void
    {
        foreach (['csv', 'json', 'xml'] as $extension) {
            $response = $this->postJson('/api/imports', [
                'file' => UploadedFile::fake()->createWithContent(
                    "invalid_transactions.{$extension}",
                    file_get_contents(base_path("tests/Fixtures/imports/invalid_transactions.{$extension}")),
                ),
            ]);

            $response
                ->assertCreated()
                ->assertJsonPath('data.total_records', 2)
                ->assertJsonPath('data.successful_records', 0)
                ->assertJsonPath('data.failed_records', 2)
                ->assertJsonPath('data.status', ImportStatus::Failed->value)
                ->assertJsonCount(2, 'data.logs');
        }

        $this->assertDatabaseMissing('transactions', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->assertDatabaseHas('import_logs', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);
    }

    public function test_partial_sample_files_save_valid_records_and_log_invalid_records(): void
    {
        foreach (['csv', 'json', 'xml'] as $extension) {
            $response = $this->postJson('/api/imports', [
                'file' => UploadedFile::fake()->createWithContent(
                    "partial_transactions.{$extension}",
                    file_get_contents(base_path("tests/Fixtures/imports/partial_transactions.{$extension}")),
                ),
            ]);

            $response
                ->assertCreated()
                ->assertJsonPath('data.total_records', 2)
                ->assertJsonPath('data.successful_records', 1)
                ->assertJsonPath('data.failed_records', 1)
                ->assertJsonPath('data.status', ImportStatus::Partial->value)
                ->assertJsonCount(1, 'data.logs');
        }

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440000',
            'account_number' => 'PL61109010140000071219812874',
        ]);

        $this->assertDatabaseHas('import_logs', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440001',
        ]);
    }

    public function test_malformed_csv_row_is_logged_without_stopping_the_import(): void
    {
        $response = $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent(
                'malformed_transactions.csv',
                file_get_contents(base_path('tests/Fixtures/imports/malformed_transactions.csv')),
            ),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.total_records', 2)
            ->assertJsonPath('data.successful_records', 1)
            ->assertJsonPath('data.failed_records', 1)
            ->assertJsonPath('data.status', ImportStatus::Partial->value)
            ->assertJsonPath('data.logs.0.error_message', 'CSV row does not match header column count.');

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => '550e8400-e29b-41d4-a716-446655440030',
        ]);
    }

    public function test_duplicate_transaction_id_uses_safe_error_message(): void
    {
        $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent(
                'valid_transactions.csv',
                file_get_contents(base_path('tests/Fixtures/imports/valid_transactions.csv')),
            ),
        ])->assertCreated();

        $response = $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent(
                'valid_transactions.csv',
                file_get_contents(base_path('tests/Fixtures/imports/valid_transactions.csv')),
            ),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', ImportStatus::Failed->value)
            ->assertJsonPath('data.failed_records', 2)
            ->assertJsonPath('data.logs.0.error_message', 'Transaction ID already exists.');
    }
}
