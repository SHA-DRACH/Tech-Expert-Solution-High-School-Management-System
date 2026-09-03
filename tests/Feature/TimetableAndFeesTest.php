<?php

namespace Tests\Feature;

use App\Actions\RaiseInvoices;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec sections 31 (timetable) and 39 (fee structures). */
class TimetableAndFeesTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected SchoolClass $class;

    protected Section $section;

    protected Subject $subject;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(9),
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'name' => 'First Term',
            'sequence' => 1,
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 8', 'level' => 8]);

        $this->section = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $this->class->id,
            'name' => '8A',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Mathematics',
            'code' => 'MTH101',
        ]);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
        ]);
    }

    protected function lesson(array $overrides = []): array
    {
        return array_merge([
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '08:45',
            'room' => 'Room 1',
        ], $overrides);
    }

    /* ------------------------------------------------------------ timetable */

    public function test_a_lesson_can_be_added_to_the_timetable(): void
    {
        $this->actingAs($this->administratorFor($this->school))
            ->post(route('timetable.store'), $this->lesson())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('timetable_entries', [
            'section_id' => $this->section->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_a_class_cannot_be_double_booked(): void
    {
        $admin = $this->administratorFor($this->school);

        $this->actingAs($admin)->post(route('timetable.store'), $this->lesson());

        $other = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG101']);

        // Overlaps 08:00-08:45 with a different subject, teacher and room.
        $this->actingAs($admin)
            ->post(route('timetable.store'), $this->lesson([
                'subject_id' => $other->id,
                'teacher_id' => null,
                'room' => 'Room 9',
                'starts_at' => '08:30',
                'ends_at' => '09:15',
            ]))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, TimetableEntry::count());
    }

    public function test_a_teacher_cannot_be_in_two_places_at_once(): void
    {
        $admin = $this->administratorFor($this->school);

        $this->actingAs($admin)->post(route('timetable.store'), $this->lesson());

        $otherSection = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $this->class->id,
            'name' => '8B',
        ]);

        $this->actingAs($admin)
            ->post(route('timetable.store'), $this->lesson([
                'section_id' => $otherSection->id,
                'room' => 'Room 4',
            ]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, TimetableEntry::count());
    }

    public function test_a_room_cannot_hold_two_classes_at_once(): void
    {
        $admin = $this->administratorFor($this->school);

        $this->actingAs($admin)->post(route('timetable.store'), $this->lesson());

        $otherSection = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $this->class->id,
            'name' => '8C',
        ]);

        $this->actingAs($admin)
            ->post(route('timetable.store'), $this->lesson([
                'section_id' => $otherSection->id,
                'teacher_id' => null,
                // Same room, overlapping time.
            ]))
            ->assertSessionHasErrors('room');
    }

    public function test_back_to_back_lessons_are_allowed(): void
    {
        $admin = $this->administratorFor($this->school);

        $this->actingAs($admin)->post(route('timetable.store'), $this->lesson());

        // Starts exactly when the previous one ends: not an overlap.
        $this->actingAs($admin)
            ->post(route('timetable.store'), $this->lesson([
                'starts_at' => '08:45',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TimetableEntry::count());
    }

    public function test_a_lesson_must_end_after_it_starts(): void
    {
        $this->actingAs($this->administratorFor($this->school))
            ->post(route('timetable.store'), $this->lesson(['starts_at' => '10:00', 'ends_at' => '09:00']))
            ->assertSessionHasErrors('ends_at');
    }

    /* ----------------------------------------------------------------- fees */

    protected function feeStructure(): FeeStructure
    {
        $structure = FeeStructure::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'term_id' => $this->term->id,
            'name' => 'Grade 8 — First Term',
            'is_active' => true,
        ]);

        foreach ([['Tuition', 15000], ['ICT', 1500]] as [$category, $amount]) {
            FeeItem::create([
                'school_id' => $this->school->id,
                'fee_structure_id' => $structure->id,
                'category' => $category,
                'amount_minor' => Money::toMinor($amount),
            ]);
        }

        return $structure->load('items');
    }

    protected function enrol(int $count): void
    {
        foreach (range(1, $count) as $i) {
            $student = Student::factory()->create(['school_id' => $this->school->id]);

            Enrollment::create([
                'school_id' => $this->school->id,
                'student_id' => $student->id,
                'academic_year_id' => $this->year->id,
                'school_class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'status' => 'active',
            ]);
        }
    }

    public function test_a_fee_structure_is_created_with_its_lines(): void
    {
        $this->actingAs($this->administratorFor($this->school))
            ->post(route('fees.store'), [
                'name' => 'Grade 8 — First Term',
                'school_class_id' => $this->class->id,
                'term_id' => $this->term->id,
                'items' => [
                    ['category' => 'Tuition', 'amount' => 15000],
                    ['category' => 'Sports', 'amount' => 1000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $structure = FeeStructure::firstOrFail();

        $this->assertSame(2, $structure->items()->count());
        $this->assertSame(Money::toMinor(16000), $structure->totalMinor());
    }

    public function test_a_structure_needs_at_least_one_line(): void
    {
        $this->actingAs($this->administratorFor($this->school))
            ->post(route('fees.store'), ['name' => 'Empty', 'items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_invoices_are_raised_for_every_enrolled_student(): void
    {
        $this->enrol(3);
        $structure = $this->feeStructure();

        $result = app(RaiseInvoices::class)->handle($structure);

        $this->assertSame(3, $result['created']);
        $this->assertSame(3, Invoice::count());

        $invoice = Invoice::with('items')->first();

        $this->assertSame(Money::toMinor(16500), $invoice->total_minor);
        $this->assertSame(2, $invoice->items->count());
        $this->assertSame(Money::toMinor(16500), $invoice->balanceMinor());
    }

    public function test_raising_invoices_twice_does_not_charge_a_family_twice(): void
    {
        $this->enrol(2);
        $structure = $this->feeStructure();

        app(RaiseInvoices::class)->handle($structure);
        $second = app(RaiseInvoices::class)->handle($structure);

        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(2, Invoice::count());
    }

    public function test_raising_invoices_for_an_empty_class_reports_it(): void
    {
        $structure = $this->feeStructure();

        $this->actingAs($this->administratorFor($this->school))
            ->post(route('fees.raise'), ['fee_structure_id' => $structure->id])
            ->assertSessionHasErrors('fee_structure_id');

        $this->assertSame(0, Invoice::count());
    }

    public function test_invoice_numbers_are_unique_and_sequential(): void
    {
        $this->enrol(3);
        $structure = $this->feeStructure();

        app(RaiseInvoices::class)->handle($structure);

        $numbers = Invoice::orderBy('id')->pluck('invoice_number');

        $this->assertSame($numbers->unique()->count(), $numbers->count());
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $numbers->first());
    }

    public function test_a_fee_structure_from_another_school_cannot_be_billed(): void
    {
        $otherSchool = $this->createSchool();

        $theirStructure = FeeStructure::withoutGlobalScopes()->create([
            'school_id' => $otherSchool->id,
            'academic_year_id' => $this->year->id,
            'name' => 'Theirs',
            'is_active' => true,
        ]);

        $this->actingAs($this->administratorFor($this->school))
            ->post(route('fees.raise'), ['fee_structure_id' => $theirStructure->id])
            ->assertNotFound();
    }

    public function test_a_timetable_entry_from_another_school_cannot_be_deleted(): void
    {
        $otherSchool = $this->createSchool();

        $theirEntry = TimetableEntry::withoutGlobalScopes()->create([
            'school_id' => $otherSchool->id,
            'academic_year_id' => $this->year->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '08:45',
        ]);

        $this->actingAs($this->administratorFor($this->school))
            ->delete(route('timetable.destroy', $theirEntry))
            ->assertNotFound();

        $this->assertDatabaseHas('timetable_entries', ['id' => $theirEntry->id]);
    }
}
