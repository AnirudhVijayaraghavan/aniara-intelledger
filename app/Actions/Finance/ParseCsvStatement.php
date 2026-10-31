<?php

namespace App\Actions\Finance;

use Generator;
use Illuminate\Validation\ValidationException;

class ParseCsvStatement
{
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;

    public const MAX_DATA_RECORDS = 25000;

    public const MAX_COLUMNS = 64;

    public const MAX_FIELD_BYTES = 8192;

    public const MAX_RECORD_BYTES = 65536;

    /**
     * Strict comma/double-quote CSV. Header is logical record one; physical newlines
     * inside quoted fields do not increment record numbers. Every value stays text.
     *
     * @param  resource  $stream
     * @return Generator<int, array{number: int, fields: list<string>}>
     */
    public function records($stream): Generator
    {
        $field = '';
        $fields = [];
        $state = 'start';
        $number = 1;
        $recordBytes = 0;
        $inputBytes = 0;
        $columns = null;
        $expectLf = false;
        $prefix = fread($stream, 8192);
        if ($prefix === false) {
            $this->fail(1, 'The CSV could not be read.');
        }
        $signature = str_starts_with($prefix, "\xEF\xBB\xBF") ? substr($prefix, 3) : $prefix;
        if (preg_match('/\A(?:%PDF-|PK\x03\x04|\xD0\xCF\x11\xE0|<\?php|<!DOCTYPE\s+html|<html\b)/i', $signature) === 1) {
            $this->fail(1, 'Upload CSV text rather than a document, archive, or executable file.');
        }

        foreach ($this->chunks($stream, $prefix) as $chunk) {
            $inputBytes += strlen($chunk);
            if ($inputBytes > self::MAX_FILE_BYTES) {
                $this->fail($number, 'The CSV exceeds the 10 MiB limit.');
            }
            if ($inputBytes === strlen($chunk) && str_starts_with($chunk, "\xEF\xBB\xBF")) {
                $chunk = substr($chunk, 3);
            }
            $length = strlen($chunk);
            for ($offset = 0; $offset < $length; $offset++) {
                $byte = $chunk[$offset];
                if ($expectLf) {
                    if ($byte !== "\n") {
                        $this->fail($number, 'Use LF or CRLF line endings.');
                    }
                    $expectLf = false;

                    continue;
                }
                if (++$recordBytes > self::MAX_RECORD_BYTES) {
                    $this->fail($number, 'The record exceeds 64 KiB.');
                }
                if ($state === 'quoted') {
                    if ($byte === '"') {
                        $state = 'closed';
                    } else {
                        $field .= $byte;
                    }
                } elseif ($state === 'closed' && $byte === '"') {
                    $field .= '"';
                    $state = 'quoted';
                } elseif ($byte === ',' || $byte === "\n" || $byte === "\r") {
                    $this->finishField($fields, $field, $number);
                    $state = 'start';
                    if ($byte === ',') {
                        if (count($fields) >= self::MAX_COLUMNS) {
                            $this->fail($number, 'The record exceeds 64 columns.');
                        }

                        continue;
                    }
                    $this->validateRecord($fields, $columns, $number);
                    yield ['number' => $number, 'fields' => $fields];
                    $number++;
                    $fields = [];
                    $recordBytes = 0;
                    $expectLf = $byte === "\r";
                } elseif ($state === 'closed') {
                    $this->fail($number, 'Unexpected text after a closing quote.');
                } elseif ($byte === '"') {
                    if ($state !== 'start') {
                        $this->fail($number, 'Quotes must surround the entire field.');
                    }
                    $state = 'quoted';
                } else {
                    $field .= $byte;
                    $state = 'plain';
                }
                if (strlen($field) > self::MAX_FIELD_BYTES) {
                    $this->fail($number, 'A field exceeds 8 KiB.');
                }
            }
        }
        if ($expectLf) {
            $this->fail($number, 'Use LF or CRLF line endings.');
        }
        if ($state === 'quoted') {
            $this->fail($number, 'A quoted field was not closed.');
        }
        if ($recordBytes > 0) {
            $this->finishField($fields, $field, $number);
            $this->validateRecord($fields, $columns, $number);
            yield ['number' => $number, 'fields' => $fields];
            $number++;
        }
        if ($number <= 2) {
            $this->fail(1, 'A header and at least one data record are required.');
        }
    }

    /**
     * @param  resource  $stream
     * @return Generator<int, string>
     */
    private function chunks($stream, string $prefix): Generator
    {
        if ($prefix !== '') {
            yield $prefix;
        }
        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                $this->fail(1, 'The CSV could not be read.');
            }
            if ($chunk !== '') {
                yield $chunk;
            }
        }
    }

    /** @param list<string> $fields */
    private function finishField(array &$fields, string &$field, int $number): void
    {
        if (! mb_check_encoding($field, 'UTF-8')) {
            $this->fail($number, 'The file must use UTF-8 encoding.');
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $field) === 1) {
            $this->fail($number, 'Unsupported control characters were found.');
        }
        $fields[] = $field;
        $field = '';
    }

    /**
     * @param  list<string>  $fields
     *
     * @param-out int $columns
     */
    private function validateRecord(array $fields, ?int &$columns, int $number): void
    {
        if ($number > self::MAX_DATA_RECORDS + 1) {
            $this->fail($number, 'The CSV exceeds 25,000 data records.');
        }
        if ($columns === null) {
            foreach ($fields as $header) {
                if (trim($header) === '') {
                    $this->fail($number, 'Header names cannot be empty.');
                }
            }
            $columns = count($fields);
        } elseif (count($fields) !== $columns) {
            $this->fail($number, 'The column count does not match the header.');
        }
    }

    private function fail(int $number, string $message): never
    {
        throw ValidationException::withMessages(['file' => 'CSV record '.$number.': '.$message]);
    }
}
