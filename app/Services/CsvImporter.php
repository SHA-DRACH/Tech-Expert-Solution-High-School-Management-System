<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Reads an uploaded CSV into rows keyed by column heading (spec section 57).
 *
 * Two rules shape this class.
 *
 * The first is that an import is never all-or-nothing at the file level. A
 * school's spreadsheet is typed by hand and a single bad date should not throw
 * away the other four hundred rows, so each row is validated on its own and the
 * caller is told exactly which ones failed and why.
 *
 * The second is that nothing is written until the person has seen what will
 * happen. `read()` produces a preview; the caller decides whether to commit.
 */
class CsvImporter
{
    public const MAX_ROWS = 2000;

    /**
     * @return array{headings: array<int, string>, rows: Collection<int, array<string, string>>, error: ?string}
     */
    public function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return ['headings' => [], 'rows' => collect(), 'error' => 'That file could not be opened.'];
        }

        $headings = fgetcsv($handle);

        if ($headings === false || $headings === [null]) {
            fclose($handle);

            return ['headings' => [], 'rows' => collect(), 'error' => 'That file appears to be empty.'];
        }

        // Excel writes a UTF-8 BOM in front of the first heading, which would
        // otherwise make "Name" fail to match the column called "Name".
        $headings[0] = preg_replace('/^\x{FEFF}/u', '', (string) $headings[0]);

        $headings = array_map(fn ($heading) => $this->normaliseHeading((string) $heading), $headings);

        $rows = collect();
        $lineNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;

            // A trailing newline yields a single null column, not a row.
            if ($values === [null] || $values === []) {
                continue;
            }

            if ($rows->count() >= self::MAX_ROWS) {
                fclose($handle);

                return [
                    'headings' => $headings,
                    'rows' => $rows,
                    'error' => 'That file has more than '.number_format(self::MAX_ROWS).' rows. Split it and import in parts.',
                ];
            }

            $row = [];

            foreach ($headings as $index => $heading) {
                if ($heading === '') {
                    continue;
                }

                $row[$heading] = trim((string) ($values[$index] ?? ''));
            }

            // Skip rows that are entirely blank rather than reporting a page of
            // errors for the empty lines at the bottom of a spreadsheet.
            if (collect($row)->filter(fn ($value) => $value !== '')->isEmpty()) {
                continue;
            }

            $row['__line'] = $lineNumber;

            $rows->push($row);
        }

        fclose($handle);

        return ['headings' => $headings, 'rows' => $rows, 'error' => null];
    }

    /**
     * "First Name", "first_name" and "  FIRST NAME " are the same column.
     * Schools export from all sorts of places and retyping headings to match
     * an exact string is not a reasonable thing to ask of them.
     */
    public function normaliseHeading(string $heading): string
    {
        $heading = strtolower(trim($heading));
        $heading = preg_replace('/[^a-z0-9]+/', '_', $heading);

        return trim((string) $heading, '_');
    }

    /**
     * Check the file offers every column an import cannot do without.
     *
     * @param  array<int, string>  $headings
     * @param  array<int, string>  $required
     * @return array<int, string> the ones that are missing
     */
    public function missingColumns(array $headings, array $required): array
    {
        return array_values(array_diff($required, $headings));
    }
}
