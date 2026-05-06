<?php

namespace App\Imports;

use App\Imports\Contracts\TransactionFileParser;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class XmlTransactionFileParser implements TransactionFileParser
{
    public function supports(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'xml';
    }

    public function parse(UploadedFile $file): iterable
    {
        try {
            $xml = new SimpleXMLElement($file->getContent());
        } catch (Throwable $exception) {
            throw new RuntimeException('Invalid XML file.', previous: $exception);
        }

        $records = $xml->xpath('//transaction') ?: [];

        foreach ($records as $record) {
            yield json_decode(json_encode($record, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }
    }
}
