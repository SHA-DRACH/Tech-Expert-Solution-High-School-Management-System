<?php

namespace App\Services;

use App\Models\School;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The Excel grade sheet for a class: download it, fill it in, upload it back.
 *
 * One workbook per class and period, one worksheet per subject. The layout is
 * fixed so a returned file can be read without guessing:
 *
 *   row 1-3   school, class and period, and how to fill it in
 *   row 5     headings
 *   row 6+    one student per row: number, name, then a mark per column
 *
 * Students are matched on the way back in by **student number**, never by
 * name. The number and name cells are locked (the sheet is protected with no
 * password - this stops accidents, not people), and the mark cells only accept
 * a number between 0 and what the column is out of.
 *
 * A hidden worksheet records which class and period the file was made for and
 * which subject each worksheet holds. That record is used only to refuse a file
 * uploaded against the wrong class; it grants nothing. Every mark still goes
 * through `GradeSheet::write()` and its locks, exactly like one typed on screen.
 */
class GradeSheetWorkbook
{
    public const META_SHEET = 'gsms';

    protected const HEADING_ROW = 5;

    protected const FIRST_ROW = 6;

    public function __construct(private readonly GradeSheet $gradeSheet) {}

    /**
     * @param  Collection<int, Subject>  $subjects
     * @param  callable(Subject): Collection  $rosterFor
     */
    public function build(?School $school, Section $section, Term|Semester $sheet, Collection $subjects, callable $rosterFor): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()
            ->setCreator($school?->name ?? 'Grace School Management System')
            ->setTitle($this->gradeSheet->title($section, null, $sheet));

        $book->removeSheetByIndex(0);

        $meta = new Worksheet($book, self::META_SHEET);
        $book->addSheet($meta);
        $meta->fromArray([
            ['section', $section->id],
            ['sheet', $this->sheetKey($sheet)],
            ['version', 1],
        ]);

        $columns = $this->gradeSheet->columns($sheet);
        $metaRow = 5;
        $usedTitles = [];

        foreach ($subjects as $subject) {
            $worksheet = new Worksheet($book, $this->worksheetTitle($subject->name, $usedTitles));
            $book->addSheet($worksheet, $book->getSheetCount() - 1);

            $meta->fromArray([$worksheet->getTitle(), $subject->id, implode(',', array_keys($columns))], null, 'A'.$metaRow++);

            $this->fillWorksheet($worksheet, $school, $section, $subject, $sheet, $columns, $rosterFor($subject));
        }

        // Hidden from the teacher, not from the file: this is a label, not a secret.
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * Read an uploaded workbook back into marks, keyed by subject.
     *
     * @return array{section: int, sheet: string, subjects: array<int, array{title: string, rows: array<int, array{line: int, number: string, marks: array<string, mixed>}>}>}
     *
     * @throws ValidationException
     */
    public function read(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $book = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'That file could not be opened as an Excel workbook (.xlsx).']);
        }

        $meta = $book->getSheetByName(self::META_SHEET);

        if ($meta === null) {
            throw ValidationException::withMessages(['file' => 'This is not a grade sheet downloaded from this system. Download the class grade sheet, fill it in, and upload that file.']);
        }

        $section = (int) $meta->getCell('B1')->getValue();
        $sheetKey = (string) $meta->getCell('B2')->getValue();
        $subjects = [];

        for ($row = 5; $row <= $meta->getHighestRow(); $row++) {
            $title = (string) $meta->getCell('A'.$row)->getValue();
            $subjectId = (int) $meta->getCell('B'.$row)->getValue();
            $types = array_filter(explode(',', (string) $meta->getCell('C'.$row)->getValue()));

            $worksheet = $title !== '' ? $book->getSheetByName($title) : null;

            if ($worksheet === null || $subjectId === 0) {
                continue;
            }

            $rows = [];

            for ($line = self::FIRST_ROW; $line <= $worksheet->getHighestDataRow(); $line++) {
                $number = trim((string) $worksheet->getCell('A'.$line)->getValue());

                if ($number === '') {
                    continue;
                }

                $marks = [];

                foreach (array_values($types) as $offset => $type) {
                    $marks[$type] = $worksheet->getCell(Coordinate::stringFromColumnIndex(3 + $offset).$line)->getValue();
                }

                $rows[] = ['line' => $line, 'number' => $number, 'marks' => $marks];
            }

            $subjects[$subjectId] = ['title' => $title, 'rows' => $rows];
        }

        return ['section' => $section, 'sheet' => $sheetKey, 'subjects' => $subjects];
    }

    /** "period:12" or "exam:3" - how the page and the file name a sheet. */
    public function sheetKey(Term|Semester $sheet): string
    {
        return ($sheet instanceof Semester ? 'exam:' : 'period:').$sheet->id;
    }

    /**
     * @param  array<string, array{label: string, max: int}>  $columns
     */
    protected function fillWorksheet(Worksheet $ws, ?School $school, Section $section, Subject $subject, Term|Semester $sheet, array $columns, Collection $students): void
    {
        $assessments = $this->gradeSheet->assessments($section, $subject, $sheet);
        $marks = $this->gradeSheet->marks($assessments, $students);
        $isPeriod = $sheet instanceof Term;

        $ws->setCellValue('A1', $school?->name ?? '');
        $ws->setCellValue('A2', $this->gradeSheet->title($section, $subject, $sheet));
        $ws->setCellValue('A3', 'Type marks in the shaded columns. Do not change student numbers. A blank cell means no mark.');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A2')->getFont()->setBold(true);
        $ws->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $headings = ['Student number', 'Student name'];

        foreach ($columns as $column) {
            $headings[] = $column['label'].' (out of '.$column['max'].')';
        }

        if ($isPeriod) {
            $headings[] = 'Period grade';
        }

        $ws->fromArray($headings, null, 'A'.self::HEADING_ROW);

        $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
        $firstMark = 'C';
        $lastMark = Coordinate::stringFromColumnIndex(2 + count($columns));
        $totalMax = array_sum(array_column($columns, 'max'));

        $ws->getStyle('A'.self::HEADING_ROW.':'.$lastColumn.self::HEADING_ROW)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
        ]);

        $line = self::FIRST_ROW;

        foreach ($students as $student) {
            // Explicit strings: a student number like "0042" must survive, and
            // a name that happens to start with "=" must not become a formula.
            $ws->setCellValueExplicit('A'.$line, (string) $student->student_number, DataType::TYPE_STRING);
            $ws->setCellValueExplicit('B'.$line, (string) $student->full_name, DataType::TYPE_STRING);

            $offset = 0;

            foreach (array_keys($columns) as $type) {
                $cell = Coordinate::stringFromColumnIndex(3 + $offset).$line;
                $score = $marks->get($type.'.'.$student->id)?->score;

                if ($score !== null) {
                    $ws->setCellValue($cell, (float) $score);
                }

                $offset++;
            }

            if ($isPeriod) {
                $ws->setCellValue(
                    Coordinate::stringFromColumnIndex(3 + count($columns)).$line,
                    "=IF(COUNTBLANK({$firstMark}{$line}:{$lastMark}{$line})>0,\"\",ROUND(SUM({$firstMark}{$line}:{$lastMark}{$line})/{$totalMax}*100,2))",
                );
            }

            $line++;
        }

        $lastLine = max(self::FIRST_ROW, $line - 1);

        // Only the mark cells are open, and only to a number in range.
        $offset = 0;

        foreach ($columns as $column) {
            $letter = Coordinate::stringFromColumnIndex(3 + $offset);
            $range = $letter.self::FIRST_ROW.':'.$letter.$lastLine;

            $ws->getStyle($range)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
            $ws->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF9C3');

            $validation = new DataValidation;
            $validation->setType(DataValidation::TYPE_DECIMAL)
                ->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setFormula1('0')
                ->setFormula2((string) $column['max'])
                ->setAllowBlank(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Mark out of range')
                ->setError($column['label'].' is marked out of '.$column['max'].'.');
            $ws->setDataValidation($range, $validation);

            $offset++;
        }

        $ws->getProtection()->setSheet(true);

        foreach (range(1, count($headings)) as $index) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $ws->freezePane('C'.self::FIRST_ROW);
    }

    /** Excel titles: 31 characters, no []:*?/\, and unique in the book. */
    protected function worksheetTitle(string $name, array &$used): string
    {
        $base = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name)) ?: 'Subject', 0, 28);
        $title = $base;
        $n = 2;

        while (in_array(mb_strtolower($title), $used, true) || mb_strtolower($title) === self::META_SHEET) {
            $title = $base.' '.$n++;
        }

        $used[] = mb_strtolower($title);

        return $title;
    }
}
