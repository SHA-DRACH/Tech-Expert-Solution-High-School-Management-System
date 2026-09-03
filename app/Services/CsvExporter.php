<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a collection out as CSV (spec section 57).
 *
 * Streamed rather than built in memory so exporting a school with thousands of
 * students does not exhaust the request's memory limit.
 */
class CsvExporter
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable  $rows  each row an array matching the headings
     */
    public function stream(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        $filename = str_ends_with($filename, '.csv') ? $filename : $filename.'.csv';

        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'wb');

            // A BOM so Excel opens accented names correctly rather than as mojibake.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->sanitise(...), $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralise values a spreadsheet would treat as a formula.
     *
     * A name entered as "=cmd|..." is a real attack against whoever opens the
     * export, so leading formula characters are prefixed with a quote.
     */
    protected function sanitise(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
