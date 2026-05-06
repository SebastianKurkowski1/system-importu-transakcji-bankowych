<?php

namespace App\Imports\Contracts;

use Illuminate\Http\UploadedFile;

interface TransactionFileParser
{
    public function parse(UploadedFile $file): iterable;

    public function supports(UploadedFile $file): bool;
}
