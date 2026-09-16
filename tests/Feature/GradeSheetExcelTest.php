<?php

namespace Tests\Feature;

use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\GradeSheetWorkbook;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * The Excel grade sheet: download a class, fill it in, upload it back.
 *
 * The upload is the part that matters. It must match students by number, go
 * through the same locks as typing on screen, refuse a file for another class,
 * and keep nothing at all if any single mark in it is refused.
 */
class GradeSheetExcelTest extends PeriodGradingTestCase
{
    protected function download($user, ?string $sheet = null)
    {
        return $this->actingAs($user)->get(route('gradesheet.download', [
            'section' => $this->section->id,
            'sheet' => $sheet ?? $this->key(1),
        ]));
    }

    protected function workbookFrom($response): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'gsms').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return IOFactory::load($path);
    }

    protected function upload($user, Spreadsheet $book, ?string $sheet = null, ?Section $section = null)
    {
        $path = tempnam(sys_get_temp_dir(), 'gsms').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $this->actingAs($user)->post(route('gradesheet.upload', [
            'section' => ($section ?? $this->section)->id,
            'subject' => $this->maths->id,
            'sheet' => $sheet ?? $this->key(1),
        ]), ['file' => new UploadedFile($path, 'marks.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)]);
    }

    /* ------------------------------------------------------------------ */

    public function test_the_download_has_every_student_and_every_assessment_column(): void
    {
        $this->setUpPeriods();
        $this->student('S-1', 'Mary');
        $this->student('S-2', 'Ben');

        $response = $this->download($this->teacherUser());
        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $sheet = $this->workbookFrom($response)->getSheetByName('Mathematics');

        $this->assertNotNull($sheet, 'One worksheet per subject, named after it.');
        $this->assertSame('Student number', $sheet->getCell('A5')->getValue());
        $this->assertSame('Period test (out of 40)', $sheet->getCell('C5')->getValue());
        $this->assertSame('Quiz (out of 20)', $sheet->getCell('D5')->getValue());
        $this->assertSame('Assignment (out of 20)', $sheet->getCell('E5')->getValue());
        $this->assertSame('Attendance (out of 20)', $sheet->getCell('F5')->getValue());
        $this->assertSame('Period grade', $sheet->getCell('G5')->getValue());

        $numbers = [$sheet->getCell('A6')->getValue(), $sheet->getCell('A7')->getValue()];
        sort($numbers);
        $this->assertSame(['S-1', 'S-2'], $numbers);
    }

    public function test_marks_already_entered_come_down_in_the_file(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $teacher = $this->teacherUser();

        $this->save($teacher, $this->key(1), ['quiz' => [$mary->id => 17]]);

        $sheet = $this->workbookFrom($this->download($teacher))->getSheetByName('Mathematics');

        $this->assertEquals(17, $sheet->getCell('D6')->getValue());
    }

    public function test_a_filled_in_file_uploads_the_marks(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $teacher = $this->teacherUser();

        $book = $this->workbookFrom($this->download($teacher));
        $sheet = $book->getSheetByName('Mathematics');
        $sheet->setCellValue('C6', 35)->setCellValue('D6', 18)->setCellValue('E6', 19)->setCellValue('F6', 20);

        $this->upload($teacher, $book)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(4, AssessmentScore::where('student_id', $mary->id)->count());
        $this->assertSame(92.0, app(\App\Services\PeriodGrades::class)
            ->subjectSheet($this->section, $this->maths, $this->year, collect([$mary]), approvedOnly: false)
            ->first()['periods'][1]);
    }

    /** Matched by number, so reordering or retyping names changes nothing. */
    public function test_rows_are_matched_by_student_number_not_by_position_or_name(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1', 'Mary');
        $ben = $this->student('S-2', 'Ben');
        $teacher = $this->teacherUser();

        $book = $this->workbookFrom($this->download($teacher));
        $sheet = $book->getSheetByName('Mathematics');
        $sheet->getProtection()->setSheet(false);

        // Swap the rows and scramble the names; the numbers still decide.
        $sheet->setCellValue('A6', 'S-2')->setCellValue('B6', 'Someone else')->setCellValue('C6', 10);
        $sheet->setCellValue('A7', 'S-1')->setCellValue('B7', 'Wrong name')->setCellValue('C7', 30);

        $this->upload($teacher, $book)->assertSessionHasNoErrors();

        $this->assertSame(30.0, (float) AssessmentScore::where('student_id', $mary->id)->value('score'));
        $this->assertSame(10.0, (float) AssessmentScore::where('student_id', $ben->id)->value('score'));
    }

    public function test_one_bad_mark_in_the_file_keeps_nothing_from_it(): void
    {
        $this->setUpPeriods();
        $this->student('S-1', 'Mary');
        $this->student('S-2', 'Ben');
        $teacher = $this->teacherUser();

        $book = $this->workbookFrom($this->download($teacher));
        $book->getSheetByName('Mathematics')->setCellValue('C6', 30)->setCellValue('C7', 55);

        $this->upload($teacher, $book)
            ->assertSessionHasErrors('file')
            ->assertSessionHas('uploadProblems');

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_an_unknown_student_number_is_refused(): void
    {
        $this->setUpPeriods();
        $this->student('S-1');
        $teacher = $this->teacherUser();

        $book = $this->workbookFrom($this->download($teacher));
        $sheet = $book->getSheetByName('Mathematics');
        $sheet->setCellValue('A7', 'S-999')->setCellValue('C7', 20);

        $this->upload($teacher, $book)->assertSessionHasErrors('file');

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_a_file_for_another_class_is_refused(): void
    {
        $this->setUpPeriods();
        $this->student('S-1');
        $teacher = $this->teacherUser();

        $other = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->section->school_class_id, 'name' => 'B']);
        TeachingAssignment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'teacher_id' => $this->teacher->id, 'section_id' => $other->id, 'subject_id' => $this->maths->id,
        ]);

        $book = $this->workbookFrom($this->download($teacher));
        $book->getSheetByName('Mathematics')->setCellValue('C6', 30);

        $this->upload($teacher, $book, section: $other)->assertSessionHasErrors('file');

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_a_file_for_another_period_is_refused(): void
    {
        $this->setUpPeriods();
        $this->student('S-1');
        $teacher = $this->teacherUser();

        $book = $this->workbookFrom($this->download($teacher, $this->key(1)));
        $book->getSheetByName('Mathematics')->setCellValue('C6', 30);

        $this->upload($teacher, $book, $this->key(2))->assertSessionHasErrors('file');

        $this->assertSame(0, AssessmentScore::count());
    }

    /** The file is not a back door past the exam lock. */
    public function test_an_upload_cannot_enter_exam_marks_while_entry_is_closed(): void
    {
        $this->setUpPeriods();
        $this->student('S-1');
        $teacher = $this->teacherUser();
        $semester = $this->semester(1);
        $exam = 'exam:'.$semester->id;

        $semester->update(['exam_entry_open' => true]);
        $book = $this->workbookFrom($this->download($teacher, $exam));
        $semester->update(['exam_entry_open' => false]);

        $book->getSheetByName('Mathematics')->setCellValue('C6', 80);

        $this->upload($teacher, $book, $exam)->assertSessionHasErrors('file');

        $this->assertSame(0, AssessmentScore::count());
    }

    /** A teacher's file only reaches subjects they teach, whatever it claims. */
    public function test_an_upload_cannot_write_to_a_subject_the_teacher_does_not_teach(): void
    {
        $this->setUpPeriods();
        $this->student('S-1');
        $teacher = $this->teacherUser();

        $english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG']);
        $this->section->schoolClass->subjects()->attach([$english->id], ['school_id' => $this->school->id]);

        $book = $this->workbookFrom($this->download($teacher));
        $book->getSheetByName('Mathematics')->setCellValue('C6', 30);

        // Relabel the worksheet as English in the hidden record.
        $book->getSheetByName(GradeSheetWorkbook::META_SHEET)->setCellValue('B5', $english->id);

        $this->upload($teacher, $book)->assertSessionHasErrors('file');

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_a_spreadsheet_not_made_by_the_system_is_refused(): void
    {
        $this->setUpPeriods();
        $teacher = $this->teacherUser();

        $book = new Spreadsheet;
        $book->getActiveSheet()->setCellValue('A1', 'marks');

        $this->upload($teacher, $book)->assertSessionHasErrors('file');
    }

    public function test_download_and_upload_each_need_their_own_permission(): void
    {
        $this->setUpPeriods();
        $this->student('S-1');

        $downloader = $this->teacherUser();
        $book = $this->workbookFrom($this->download($downloader));

        $noExport = $this->userFor($this->school, ['grades.enter', 'grades.approve']);
        $this->download($noExport)->assertForbidden();
        $this->upload($noExport, $book)->assertForbidden();
    }
}
