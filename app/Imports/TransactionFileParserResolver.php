<?php

namespace App\Imports;

use App\Imports\Contracts\TransactionFileParser;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class TransactionFileParserResolver
{
    public function __construct(
        private readonly iterable $parsers,
    ) {}

    public function resolve(UploadedFile $file): TransactionFileParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($file)) {
                return $parser;
            }
        }

        throw new InvalidArgumentException('Unsupported import file type.');
    }
}
