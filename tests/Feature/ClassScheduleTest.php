<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * A student's weekly class schedule, for the student and for their parents.
 *
 * What each lesson must say - day, start, end, subject, teacher - and who may
 * see it: the child, and a parent cleared for that child's records. A school
 * day is a map of where a child is; it is not for anyone else.
 */
class ClassScheduleTest extends PeriodGradingTestCase
{
    protected Student $mary;

    protected function setUp(): void
    {
        parent::setUp();

        // 16 September 2026 is a Wednesday.
        $this->mary = $this->student('S-1', 'Mary');
        $history = Subject::create(['school_id' => $this->school->id, 'name' => 'History', 'code' => 'HIS']);

        $this->lesson($history, 3, '08:00', '08:45');
        $this->lesson($this->maths, 3, '08:45', '09:30');
        $this->lesson($this->maths, 1, '10:00', '10:45');
    }

    protected function lesson(Subject $subject, int $day, string $start, string $end): TimetableEntry
    {
        return TimetableEntry::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'section_id' => $this->section->id, 'subject_id' => $subject->id, 'teacher_id' => $this->teacher->id,
            'day_of_week' => $day, 'starts_at' => $start, 'ends_at' => $end, 'room' => 'Room 4',
        ]);
    }

    protected function studentUser(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $this->mary->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    protected function parentUser(Student $child, bool $academics = true): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);

        $guardian = Guardian::create([
            'school_id' => $this->school->id, 'user_id' => $user->id,
            'first_name' => 'John', 'last_name' => 'Doe', 'phone' => '+231770000000',
        ]);

        $guardian->students()->attach($child->id, [
            'relationship' => 'Father', 'is_primary' => true,
            'can_view_academics' => $academics, 'can_view_finance' => true,
        ]);

        return $user->fresh();
    }

    /* ------------------------------------------------------------ student */

    public function test_the_students_dashboard_shows_their_week_with_times_and_teachers(): void
    {
        $this->actingAs($this->studentUser())->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('My week')
            ->assertSee('History')
            ->assertSee('8:00 AM')
            ->assertSee('8:45 AM')
            ->assertSee('9:30 AM')
            ->assertSee('Grace Kollie')
            ->assertSee('Monday')
            ->assertSee('Today');
    }

    public function test_a_student_can_download_their_schedule(): void
    {
        $response = $this->actingAs($this->studentUser())->get(route('student.timetable.download'));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'sched').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();

        $rows = collect(range(6, 8))->map(fn ($r) => [
            $sheet->getCell('A'.$r)->getValue(), $sheet->getCell('B'.$r)->getValue(),
            $sheet->getCell('C'.$r)->getValue(), $sheet->getCell('D'.$r)->getValue(),
        ])->all();

        $this->assertContains(['Wednesday', '8:00 AM', '8:45 AM', 'History'], $rows);
        $this->assertContains(['Wednesday', '8:45 AM', '9:30 AM', 'Mathematics'], $rows);
    }

    /** The school can switch the download off, as with the report card. */
    public function test_the_school_can_switch_off_downloading_the_schedule(): void
    {
        StudentPermission::create([
            'school_id' => $this->school->id, 'student_id' => $this->mary->id,
            'ability' => 'download_timetable', 'allowed' => false,
        ]);

        $this->actingAs($this->studentUser())
            ->get(route('student.timetable.download'))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------- parent */

    public function test_a_parents_dashboard_shows_their_childs_week(): void
    {
        $this->actingAs($this->parentUser($this->mary))->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee("Mary's week")
            ->assertSee('History')
            ->assertSee('8:00 AM')
            ->assertSee('Grace Kollie');
    }

    public function test_a_parent_can_view_and_download_their_childs_schedule(): void
    {
        $parent = $this->parentUser($this->mary);

        $this->actingAs($parent)->get(route('parent.schedule', ['child' => $this->mary->id]))
            ->assertOk()
            ->assertSee('Mary Doe')
            ->assertSee('9:30 AM');

        $this->actingAs($parent)
            ->get(route('parent.schedule.download', ['child' => $this->mary->id]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_a_parent_not_cleared_for_the_childs_records_does_not_see_the_schedule(): void
    {
        $parent = $this->parentUser($this->mary, academics: false);

        $this->actingAs($parent)->get(route('parent.schedule'))->assertForbidden();
        $this->actingAs($parent)->get(route('parent.schedule.download'))->assertForbidden();
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertDontSee('8:45 AM');
    }

    /** A child id in the URL that is not theirs falls back to their own child. */
    public function test_a_parent_cannot_reach_another_familys_schedule(): void
    {
        $ben = $this->student('S-2', 'Ben');
        $parent = $this->parentUser($ben);

        $this->actingAs($parent)->get(route('parent.schedule', ['child' => $this->mary->id]))
            ->assertOk()
            ->assertSee('Ben Doe')
            ->assertDontSee('Mary Doe');
    }
}
