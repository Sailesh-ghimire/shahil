<?php

namespace App\Support\Spreadsheets;

use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

class XlsxToCsv
{
    /**
     * Converts the first sheet of an .xlsx file to an in-memory CSV stream.
     *
     * @return resource
     */
    public static function convert(string $path)
    {
        $reader = new Reader;
        $reader->open($path);

        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            throw new RuntimeException('Could not open a temporary stream.');
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(static::cellToString(...), $row->toArray());

                    if (array_filter($cells, fn (string $cell): bool => $cell !== '') === []) {
                        continue;
                    }

                    fputcsv($stream, $cells, escape: '');
                }

                break;
            }
        } finally {
            $reader->close();
        }

        rewind($stream);

        return $stream;
    }

    protected static function cellToString(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => (string) (int) $value,
            default => (string) $value,
        };
    }
}
