<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\GradeScale;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherQualification;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\ReferenceNumberGenerator;
use App\Support\SchoolContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Populates a school with a realistic term of data so every dashboard has
 * something true to show. Intended for local development and demonstrations,
 * not for a live school.
 */
class DemoAcademicSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $school = School::where('slug', 'grace-foundation-institution')->first();

        if ($school === null) {
            $this->command?->warn('Grace Foundation Institution not found; run the base seeder first.');

            return;
        }

        app(SchoolContext::class)->for($school, function (School $school) {
            $year = $this->academicYear($school);
            $term = $this->terms($school, $year);

            $this->gradeScale($school);
            $this->studentPermissionDefaults($school);

            $departments = $this->departments($school);
            $subjects = $this->subjects($school, $departments);
            $classes = $this->classes($school, $subjects);
            $teachers = $this->teachers($school, $departments);

            $sections = $this->sections($school, $classes, $teachers);
            $assignments = $this->teachingAssignments($school, $year, $sections, $subjects, $teachers);

            $this->timetable($school, $year, $assignments);

            $students = $this->students($school, $year, $sections);

            $this->attendance($school, $year, $term, $students);
            $this->assessments($school, $year, $term, $assignments, $students);
            $this->finance($school, $year, $term, $students);
            $this->communications($school, $sections);
        });
    }

    protected function academicYear(School $school): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['school_id' => $school->id, 'name' => '2026 / 2027'],
            [
                'starts_on' => now()->subMonths(2)->toDateString(),
                'ends_on' => now()->addMonths(9)->toDateString(),
                'is_current' => true,
            ],
        );
    }

    protected function terms(School $school, AcademicYear $year): Term
    {
        /*
         | The current term is anchored around today so the demonstration data
         | hangs together: attendance, assessments and report cards all fall
         | inside it, rather than sitting outside a term that has not started.
         */
        $definitions = [
            ['First Term', 1, now()->subMonths(2)->toDateString(), now()->addMonth()->toDateString(), true],
            ['Second Term', 2, now()->addMonths(2)->toDateString(), now()->addMonths(5)->toDateString(), false],
            ['Third Term', 3, now()->addMonths(6)->toDateString(), now()->addMonths(9)->toDateString(), false],
        ];

        $current = null;

        foreach ($definitions as [$name, $sequence, $start, $end, $isCurrent]) {
            $term = Term::firstOrCreate(
                ['academic_year_id' => $year->id, 'name' => $name],
                [
                    'school_id' => $school->id,
                    'sequence' => $sequence,
                    'starts_on' => $start,
                    'ends_on' => $end,
                    'is_current' => $isCurrent,
                ],
            );

            if ($isCurrent) {
                $current = $term;
            }
        }

        return $current;
    }

    protected function gradeScale(School $school): void
    {
        $scale = [
            ['A', 90, 100, 'Excellent', 4.0, 1],
            ['B', 80, 89, 'Very good', 3.0, 2],
            ['C', 70, 79, 'Good', 2.0, 3],
            ['D', 60, 69, 'Satisfactory', 1.0, 4],
            ['F', 0, 59, 'Fail', 0.0, 5],
        ];

        foreach ($scale as [$grade, $min, $max, $remark, $points, $sequence]) {
            GradeScale::firstOrCreate(
                ['school_id' => $school->id, 'grade' => $grade],
                ['min_score' => $min, 'max_score' => $max, 'remark' => $remark, 'points' => $points, 'sequence' => $sequence],
            );
        }
    }

    protected function studentPermissionDefaults(School $school): void
    {
        foreach (StudentPermission::ABILITIES as $ability => $definition) {
            StudentPermission::firstOrCreate(
                ['school_id' => $school->id, 'student_id' => null, 'ability' => $ability],
                ['allowed' => $definition['default']],
            );
        }
    }

    /** @return array<string, Department> */
    protected function departments(School $school): array
    {
        $names = [
            'Sciences' => 'SCI',
            'Mathematics' => 'MTH',
            'Languages' => 'LNG',
            'Social Studies' => 'SOC',
            'Information Technology' => 'ICT',
        ];

        $departments = [];

        foreach ($names as $name => $code) {
            $departments[$name] = Department::firstOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                ['code' => $code],
            );
        }

        return $departments;
    }

    /** @return array<string, Subject> */
    protected function subjects(School $school, array $departments): array
    {
        $definitions = [
            ['Mathematics', 'MTH101', 'Mathematics', true],
            ['English Language', 'ENG101', 'Languages', true],
            ['Biology', 'BIO101', 'Sciences', true],
            ['Chemistry', 'CHM101', 'Sciences', false],
            ['Physics', 'PHY101', 'Sciences', false],
            ['Information Technology', 'ICT101', 'Information Technology', true],
            ['Geography', 'GEO101', 'Social Studies', false],
            ['Social Studies', 'SOC101', 'Social Studies', true],
        ];

        $subjects = [];

        foreach ($definitions as [$name, $code, $department, $isCore]) {
            $subjects[$name] = Subject::firstOrCreate(
                ['school_id' => $school->id, 'code' => $code],
                [
                    'name' => $name,
                    'department_id' => $departments[$department]->id,
                    'is_core' => $isCore,
                ],
            );
        }

        return $subjects;
    }

    /** @return array<int, SchoolClass> */
    protected function classes(School $school, array $subjects): array
    {
        $classes = [];

        foreach (range(7, 12) as $level) {
            $class = SchoolClass::firstOrCreate(
                ['school_id' => $school->id, 'name' => "Grade {$level}"],
                ['level' => $level, 'stage' => $level <= 9 ? 'Junior High' : 'Senior High'],
            );

            // The pivot is tenant-owned too, so the school travels with the link.
            $class->subjects()->syncWithoutDetaching(
                collect($subjects)
                    ->mapWithKeys(fn (Subject $subject) => [$subject->id => ['school_id' => $school->id]])
                    ->all()
            );

            $classes[$level] = $class;
        }

        return $classes;
    }

    /** @return array<int, Teacher> */
    protected function teachers(School $school, array $departments): array
    {
        $definitions = [
            ['Grace', 'Kollie', 'Female', 'Mathematics', 'BSc Mathematics', 'University of Liberia', 2012, 11],
            ['Emmanuel', 'Toe', 'Male', 'Sciences', 'BSc Biology', 'Cuttington University', 2014, 9],
            ['Musu', 'Sirleaf', 'Female', 'Languages', 'BA English', 'University of Liberia', 2016, 7],
            ['Joseph', 'Weah', 'Male', 'Information Technology', 'BSc Computer Science', 'AME University', 2018, 5],
            ['Hawa', 'Dolo', 'Female', 'Social Studies', 'BA Social Studies', 'Cuttington University', 2015, 8],
        ];

        $teachers = [];

        foreach ($definitions as $index => [$first, $last, $gender, $department, $award, $institution, $awardedYear, $experience]) {
            $teacher = Teacher::firstOrCreate(
                ['school_id' => $school->id, 'staff_number' => sprintf('GFI-T-%03d', $index + 1)],
                [
                    'department_id' => $departments[$department]->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'phone' => '+231 77 100 '.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'email' => strtolower($first.'.'.$last).'@gracefoundation.edu.lr',
                    'employment_type' => 'Full time',
                    'hired_on' => now()->subYears($experience)->startOfYear(),
                    'experience_years' => $experience,
                    'biography' => "{$first} teaches in the {$department} department and has {$experience} years of classroom experience.",
                    'is_public' => true,
                    'status' => 'active',
                ],
            );

            TeacherQualification::firstOrCreate(
                ['school_id' => $school->id, 'teacher_id' => $teacher->id, 'title' => $award],
                [
                    'type' => 'qualification',
                    'institution' => $institution,
                    'field' => $department,
                    'awarded_year' => $awardedYear,
                    'is_public' => true,
                ],
            );

            $teachers[$index] = $teacher;
        }

        // Give the first teacher a portal account so the teacher dashboard works.
        $lead = $teachers[0];

        if ($lead->user_id === null) {
            $user = User::firstOrCreate(
                ['email' => 'teacher@gracefoundation.edu.lr'],
                [
                    'school_id' => $school->id,
                    'name' => $lead->full_name,
                    'password' => Hash::make('ChangeMe123!'),
                    'status' => 'active',
                ],
            );

            $role = \App\Models\Role::where('school_id', $school->id)->where('slug', 'teacher')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $lead->update(['user_id' => $user->id]);
        }

        return $teachers;
    }

    /** @return array<int, Section> */
    protected function sections(School $school, array $classes, array $teachers): array
    {
        $sections = [];

        foreach ($classes as $level => $class) {
            foreach (['A', 'B'] as $index => $letter) {
                $sections[] = Section::firstOrCreate(
                    ['school_class_id' => $class->id, 'name' => $level.$letter],
                    [
                        'school_id' => $school->id,
                        'class_teacher_id' => $teachers[($level + $index) % count($teachers)]->id,
                        'capacity' => 35,
                        'room' => 'Room '.$level.$letter,
                    ],
                );
            }
        }

        return $sections;
    }

    /**
     * Assigns teachers the way a small school actually does: a subject is
     * taught by the teacher who belongs to its department, across the sections
     * of the grades that department covers.
     *
     * @return array<int, TeachingAssignment>
     */
    protected function teachingAssignments(School $school, AcademicYear $year, array $sections, array $subjects, array $teachers): array
    {
        // Which teacher owns each department.
        $byDepartment = collect($teachers)->keyBy(fn (Teacher $teacher) => $teacher->department_id);

        $assignments = [];

        foreach ($subjects as $subject) {
            $teacher = $byDepartment->get($subject->department_id) ?? $teachers[0];

            // Junior-high sections take the core subjects only; senior high
            // takes everything, so the two halves of the school differ.
            $eligible = collect($sections)->filter(function (Section $section) use ($subject) {
                $level = $section->schoolClass?->level ?? 0;

                return $subject->is_core || $level >= 10;
            });

            foreach ($eligible as $section) {
                $assignments[] = TeachingAssignment::firstOrCreate(
                    [
                        'academic_year_id' => $year->id,
                        'teacher_id' => $teacher->id,
                        'section_id' => $section->id,
                        'subject_id' => $subject->id,
                    ],
                    ['school_id' => $school->id],
                );
            }
        }

        return $assignments;
    }

    protected function timetable(School $school, AcademicYear $year, array $assignments): void
    {
        // A five-period day, Monday to Friday.
        $periods = [
            ['08:00', '08:45'],
            ['08:50', '09:35'],
            ['10:00', '10:45'],
            ['10:50', '11:35'],
            ['12:00', '12:45'],
        ];

        foreach ($assignments as $index => $assignment) {
            $day = ($index % 5) + 1;
            [$start, $end] = $periods[intdiv($index, 5) % count($periods)];

            TimetableEntry::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_year_id' => $year->id,
                    'section_id' => $assignment->section_id,
                    'subject_id' => $assignment->subject_id,
                    'day_of_week' => $day,
                    'starts_at' => $start,
                ],
                [
                    'teacher_id' => $assignment->teacher_id,
                    'ends_at' => $end,
                    'room' => 'Room '.(($index % 12) + 1),
                ],
            );
        }
    }

    /** @return \Illuminate\Support\Collection<int, Student> */
    protected function students(School $school, AcademicYear $year, array $sections)
    {
        $numbers = app(ReferenceNumberGenerator::class);

        $firstNames = ['Mary', 'James', 'Sarah', 'Daniel', 'Fatu', 'Prince', 'Korto', 'Samuel', 'Bendu', 'Alfred',
            'Musu', 'Varney', 'Yatta', 'Moses', 'Kula', 'Nathaniel', 'Deddeh', 'Abraham', 'Massa', 'Elijah'];
        $lastNames = ['Doe', 'Johnson', 'Kollie', 'Freeman', 'Gbollie', 'Toe', 'Wesseh', 'Sumo', 'Cooper', 'Massaquoi'];

        $existing = Student::count();

        if ($existing >= 20) {
            return Student::with('currentEnrollment')->get();
        }

        $students = collect();

        foreach ($firstNames as $index => $firstName) {
            $lastName = $lastNames[$index % count($lastNames)];
            $section = $sections[$index % count($sections)];

            $student = Student::create([
                'school_id' => $school->id,
                'student_number' => $numbers->studentNumber($school),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $index % 2 === 0 ? 'Female' : 'Male',
                'date_of_birth' => now()->subYears(12 + ($index % 6))->subDays($index * 11),
                'nationality' => 'Liberian',
                'status' => 'active',
            ]);

            Enrollment::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'school_class_id' => $section->school_class_id,
                'section_id' => $section->id,
                'roll_number' => str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'status' => 'active',
                'enrolled_on' => $year->starts_on,
            ]);

            $students->push($student);
        }

        $this->guardians($school, $students);

        return $students;
    }

    protected function guardians(School $school, $students): void
    {
        // A parent with three children, to exercise the child selector.
        $parent = Guardian::firstOrCreate(
            ['school_id' => $school->id, 'email' => 'parent@gracefoundation.edu.lr'],
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+231 77 200 0001',
                'occupation' => 'Trader',
                'address' => 'Sinkor, Monrovia',
                'status' => 'active',
            ],
        );

        if ($parent->user_id === null) {
            $user = User::firstOrCreate(
                ['email' => 'parent@gracefoundation.edu.lr'],
                [
                    'school_id' => $school->id,
                    'name' => 'John Doe',
                    'password' => Hash::make('ChangeMe123!'),
                    'status' => 'active',
                ],
            );

            $role = \App\Models\Role::where('school_id', $school->id)->where('slug', 'parent-guardian')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $parent->update(['user_id' => $user->id]);
        }

        $parent->students()->syncWithoutDetaching(
            $students->take(3)->mapWithKeys(fn (Student $student, int $index) => [
                $student->id => [
                    'relationship' => $index === 0 ? 'Father' : 'Father',
                    'is_primary' => true,
                    'can_view_academics' => true,
                    'can_view_finance' => true,
                ],
            ])->all()
        );

        // Give the first of those children a student portal account.
        $child = $students->first();

        if ($child && $child->user_id === null) {
            $user = User::firstOrCreate(
                ['email' => 'student@gracefoundation.edu.lr'],
                [
                    'school_id' => $school->id,
                    'name' => $child->full_name,
                    'password' => Hash::make('ChangeMe123!'),
                    'status' => 'active',
                ],
            );

            $role = \App\Models\Role::where('school_id', $school->id)->where('slug', 'student')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $child->update(['user_id' => $user->id]);
        }

        // Every remaining student gets their own guardian.
        foreach ($students->skip(3) as $index => $student) {
            $guardian = Guardian::firstOrCreate(
                ['school_id' => $school->id, 'email' => "guardian{$index}@example.lr"],
                [
                    'first_name' => ['Martha', 'Peter', 'Rebecca', 'Joseph', 'Comfort'][$index % 5],
                    'last_name' => $student->last_name,
                    'phone' => '+231 77 300 '.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'status' => 'active',
                ],
            );

            $guardian->students()->syncWithoutDetaching([
                $student->id => ['relationship' => 'Guardian', 'is_primary' => true],
            ]);
        }
    }

    protected function attendance(School $school, AcademicYear $year, Term $term, $students): void
    {
        if (AttendanceRecord::count() > 0) {
            return;
        }

        // The last four weeks of school days.
        $days = collect(range(0, 27))
            ->map(fn (int $offset) => now()->subDays($offset))
            ->filter(fn ($date) => $date->isWeekday())
            ->values();

        foreach ($students as $studentIndex => $student) {
            $sectionId = $student->currentEnrollment?->section_id
                ?? $student->enrollments()->value('section_id');

            foreach ($days as $dayIndex => $date) {
                // A deterministic pattern: most days present, a few absences.
                $seed = ($studentIndex * 7 + $dayIndex * 3) % 20;

                $status = match (true) {
                    $seed === 0 => 'absent',
                    $seed === 1 => 'late',
                    $seed === 2 => 'excused',
                    default => 'present',
                };

                AttendanceRecord::create([
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'section_id' => $sectionId,
                    'academic_year_id' => $year->id,
                    'term_id' => $term->id,
                    'recorded_on' => $date->toDateString(),
                    'status' => $status,
                ]);
            }
        }
    }

    protected function assessments(School $school, AcademicYear $year, Term $term, array $assignments, $students): void
    {
        if (Assessment::count() > 0) {
            return;
        }

        // One approved test per section/subject for a handful of assignments.
        foreach (array_slice($assignments, 0, 24) as $index => $assignment) {
            $assessment = Assessment::create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'section_id' => $assignment->section_id,
                'subject_id' => $assignment->subject_id,
                'teacher_id' => $assignment->teacher_id,
                'title' => ['First Class Test', 'Mid-Term Test', 'Assignment One'][$index % 3],
                'type' => ['test', 'test', 'assignment'][$index % 3],
                'max_score' => 100,
                'weight' => 1,
                'starts_at' => now()->subDays(($index % 10) + 4),
                'ends_at' => now()->subDays(($index % 10) + 3)->setTime(16, 0),
                // Most are approved; a couple wait for the academic office.
                'status' => $index % 8 === 0 ? 'submitted' : 'approved',
                'submitted_at' => now()->subDays(($index % 10) + 2),
                'approved_at' => $index % 8 === 0 ? null : now()->subDays(($index % 10) + 1),
            ]);

            $sectionStudents = $students->filter(
                fn (Student $student) => $student->enrollments()->where('section_id', $assignment->section_id)->exists()
            );

            foreach ($sectionStudents as $studentIndex => $student) {
                AssessmentScore::create([
                    'school_id' => $school->id,
                    'assessment_id' => $assessment->id,
                    'student_id' => $student->id,
                    'score' => 55 + (($studentIndex * 13 + $index * 7) % 45),
                ]);
            }
        }
    }

    protected function finance(School $school, AcademicYear $year, Term $term, $students): void
    {
        if (Invoice::count() > 0) {
            return;
        }

        $numbers = 1;

        foreach ($students as $index => $student) {
            $items = [
                ['Tuition', 'Termly tuition', 15000_00],
                ['Registration', 'Registration fee', 2500_00],
                ['ICT', 'Computer laboratory', 1500_00],
                ['Sports', 'Sports and recreation', 1000_00],
            ];

            $total = array_sum(array_column($items, 2));

            $invoice = Invoice::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'invoice_number' => sprintf('INV-%s-%05d', now()->year, $numbers++),
                'issued_on' => now()->subDays(30),
                'due_on' => now()->addDays(15),
                'total_minor' => $total,
                'status' => 'issued',
            ]);

            foreach ($items as [$category, $description, $amount]) {
                InvoiceItem::create([
                    'school_id' => $school->id,
                    'invoice_id' => $invoice->id,
                    'category' => $category,
                    'description' => $description,
                    'amount_minor' => $amount,
                ]);
            }

            // Roughly two thirds have paid something.
            if ($index % 3 !== 0) {
                $paid = $index % 2 === 0 ? $total : intdiv($total, 2);

                Payment::create([
                    'school_id' => $school->id,
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'guardian_id' => $student->guardians()->value('guardians.id'),
                    'receipt_number' => sprintf('RCP-%s-%05d', now()->year, $invoice->id),
                    'amount_minor' => $paid,
                    'method' => ['Cash', 'Mobile money', 'Bank transfer'][$index % 3],
                    'paid_on' => now()->subDays(($index % 20) + 1),
                ]);

                $invoice->refreshTotals();
            }
        }
    }

    protected function communications(School $school, array $sections): void
    {
        $announcements = [
            ['Term fees due 30 September', 'Parents are reminded that termly fees are due by the end of September. Payment can be made at the school office or by mobile money.', 'fees', ['parents'], false],
            ['Mid-term examinations begin 12 October', 'Mid-term examinations for all grades begin on Monday 12 October. Timetables have been shared with class teachers.', 'examination', ['parents', 'students', 'teachers'], false],
            ['PTA meeting on Saturday', 'The termly Parent Teacher Association meeting takes place this Saturday at 10:00 in the school hall.', 'general', ['parents', 'public'], false],
            ['School closes early on Friday', 'Following the district directive, the school will close at 12:30 on Friday. Please arrange collection accordingly.', 'emergency', ['parents', 'students', 'teachers', 'staff'], true],
        ];

        foreach ($announcements as $index => [$title, $body, $category, $audience, $emergency]) {
            Announcement::firstOrCreate(
                ['school_id' => $school->id, 'title' => $title],
                [
                    'body' => $body,
                    'category' => $category,
                    'audience' => $audience,
                    'published_at' => now()->subDays($index + 1),
                    'status' => 'published',
                    'is_emergency' => $emergency,
                ],
            );
        }

        $events = [
            ['Parent Teacher Association meeting', 'pta', now()->addDays(5)->setTime(10, 0), 'School hall', true],
            ['Mid-term examinations', 'examination', now()->addDays(12)->setTime(8, 0), 'All classrooms', true],
            ['Inter-house sports day', 'sports', now()->addDays(26)->setTime(9, 0), 'School field', true],
            ['Graduation ceremony', 'graduation', now()->addMonths(4)->setTime(11, 0), 'School hall', true],
        ];

        foreach ($events as [$title, $category, $startsAt, $location, $isPublic]) {
            Event::firstOrCreate(
                ['school_id' => $school->id, 'title' => $title],
                [
                    'category' => $category,
                    'starts_at' => $startsAt,
                    'ends_at' => (clone $startsAt)->addHours(3),
                    'location' => $location,
                    'is_public' => $isPublic,
                    'status' => 'scheduled',
                ],
            );
        }
    }
}
