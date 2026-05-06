<?php

namespace App\Providers;

use App\Imports\CsvTransactionFileParser;
use App\Imports\JsonTransactionFileParser;
use App\Imports\TransactionFileParserResolver;
use App\Imports\XmlTransactionFileParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TransactionFileParserResolver::class, fn () => new TransactionFileParserResolver([
            new CsvTransactionFileParser,
            new JsonTransactionFileParser,
            new XmlTransactionFileParser,
        ]));
    }

    public function boot(): void {}
}
