<?php

namespace App\Services\Assistant;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Services\Gradebook;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * The assistant that ships with the platform, and the default.
 *
 * It runs entirely on this server. It recognises what is being asked, runs the
 * corresponding query through the same tenant scope and permission checks as
 * the screens, and reports what it found. No question, and no answer, leaves
 * the school.
 *
 * That makes it narrower than a hosted model, and deliberately so. Every answer
 * it gives is a real figure with a real source behind it, and when it does not
 * recognise a question it says so and points at the screen that would answer it,
 * rather than producing a fluent sentence with an invented number in it. For a
 * system holding children's marks and families' debts, "I don't know, try here"
 * is the correct failure.
 */
class LocalAssistant implements AssistantProvider
{
    public function __construct(private readonly Gradebook $gradebook) {}

    public function name(): string
    {
        return 'Built-in (runs on this server, nothing is sent anywhere)';
    }

    public function answer(User $user, string $question): array
    {
        $q = Str::lower($question);

        foreach ($this->intents() as $intent) {
            if ($this->matches($q, $intent['any'])) {
                return $this->{$intent['handler']}($user, $q);
            }
        }

        return $this->unknown($user);
    }

    /**
     * Intent definitions, checked in order.
     *
     * Order matters: "how many students owe fees" mentions both students and
     * fees, and the fees answer is the one being asked for, so money is tested
     * before headcount.
     */
    protected function intents(): array
    {
        return [
            ['any' => ['owe', 'outstanding', 'unpaid', 'arrears', 'debt', 'balance'], 'handler' => 'outstanding'],
            ['any' => ['collected', 'paid so far', 'revenue', 'income', 'how much have we'], 'handler' => 'collected'],
            ['any' => ['attendance', 'absent', 'present', 'absentee'], 'handler' => 'attendance'],
            ['any' => ['awaiting approval', 'to approve', 'pending grade', 'grades waiting', 'unapproved'], 'handler' => 'awaitingApproval'],
            [
                'any' => [
                    'average', 'result', 'grade', 'mark', 'score', 'performance',
                    // How a parent actually phrases it. Without these, "how is
                    // my child doing this term?" was answered with the term
                    // dates, because it contains the word "term" - a question
                    // about a child answered with a calendar entry.
                    'doing', 'progress', 'how is my', 'how are my', 'report',
                ],
                'handler' => 'results',
            ],
            ['any' => ['application', 'admission', 'applicant', 'apply'], 'handler' => 'admissions'],
            ['any' => ['how many teacher', 'teaching staff', 'staff count', 'teachers do we', 'number of teachers'], 'handler' => 'teachers'],
            ['any' => ['how many student', 'student count', 'enrolled', 'enrolment', 'enrollment', 'students do we'], 'handler' => 'students'],
            /*
             | Deliberately specific rather than a bare "term". Almost every
             | question about a child mentions the term it happened in, so a
             | loose match here swallows questions meant for other intents.
             */
            ['any' => ['which term', 'what term', 'academic year', 'what year', 'calendar', 'term dates', 'term start', 'term end'], 'handler' => 'calendar'],
            ['any' => ['what can you', 'help', 'what do you do'], 'handler' => 'capabilities'],
        ];
    }

    protected function matches(string $question, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($question, $needle)) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------ answers */

    protected function students(User $user): array
    {
        if (! $user->hasPermission('students.view')) {
            return $this->refused('student numbers');
        }

        $active = Student::where('status', 'active')->count();
        $total = Student::count();

        return $this->answered(
            "There are {$active} active students on the roll"
                .($total > $active ? ", and {$total} student records in total including archived ones." : '.'),
            [['label' => 'Students', 'href' => route('students.index')]],
        );
    }

    protected function teachers(User $user): array
    {
        if (! $user->hasPermission('teachers.view')) {
            return $this->refused('staff numbers');
        }

        $active = Teacher::where('status', 'active')->count();

        return $this->answered(
            "There are {$active} active ".Str::plural('teacher', $active).' on the staff list.',
            [['label' => 'Teachers & staff', 'href' => route('teachers.index')]],
        );
    }

    protected function outstanding(User $user): array
    {
        if (! $user->hasPermission('payments.view')) {
            return $this->guardianFees($user) ?? $this->refused('fee balances');
        }

        $invoices = Invoice::outstanding()->get();
        $total = $invoices->sum(fn (Invoice $invoice) => $invoice->balanceMinor());
        $families = $invoices->filter(fn (Invoice $i) => $i->balanceMinor() > 0)->unique('student_id')->count();

        return $this->answered(
            Money::format($total).' is outstanding across '.$families.' '.Str::plural('student', $families).'.',
            [['label' => 'Invoices', 'href' => route('invoices.index')]],
        );
    }

    protected function collected(User $user): array
    {
        if (! $user->hasPermission('payments.view')) {
            return $this->guardianFees($user) ?? $this->refused('payment totals');
        }

        $total = \App\Models\Payment::sum('amount_minor');

        return $this->answered(
            Money::format($total).' has been received in payments.',
            [['label' => 'Payments', 'href' => route('payments.index')]],
        );
    }

    /** A parent asking about money gets their own children's, and only theirs. */
    protected function guardianFees(User $user): ?array
    {
        $guardian = $user->guardianProfile;

        if ($guardian === null) {
            return null;
        }

        $children = $guardian->students()->get()
            ->filter(fn (Student $child) => $guardian->canViewFinanceFor($child));

        if ($children->isEmpty()) {
            return $this->answered('You are not currently cleared to see fee information. The school office can change that.');
        }

        $lines = $children->map(function (Student $child) {
            $balance = Invoice::where('student_id', $child->id)->get()
                ->sum(fn (Invoice $invoice) => $invoice->balanceMinor());

            return $child->full_name.': '.Money::format($balance)
                .($balance > 0 ? ' outstanding' : ' — nothing outstanding');
        });

        return $this->answered(
            $lines->join("\n"),
            [['label' => 'Fees', 'href' => route('parent.fees')]],
        );
    }

    protected function attendance(User $user): array
    {
        $guardian = $user->guardianProfile;

        if ($guardian !== null) {
            $children = $guardian->students()->get()
                ->filter(fn (Student $child) => $guardian->canViewAcademicsFor($child));

            if ($children->isEmpty()) {
                return $this->answered('You are not currently cleared to see attendance. The school office can change that.');
            }

            return $this->answered(
                $children->map(fn (Student $c) => $c->full_name.': '.($c->attendanceRate() ?? 0).'% attendance')->join("\n"),
                [['label' => 'Attendance', 'href' => route('parent.attendance')]],
            );
        }

        if (! $user->hasPermission('attendance.view')) {
            return $this->refused('attendance');
        }

        $records = AttendanceRecord::where('recorded_on', '>=', now()->subDays(30)->toDateString())->get();

        if ($records->isEmpty()) {
            return $this->answered('No attendance has been recorded in the last 30 days.');
        }

        $present = $records->whereIn('status', ['present', 'late'])->count();
        $rate = round(($present / $records->count()) * 100);

        return $this->answered(
            "Attendance over the last 30 days is {$rate}%, from ".number_format($records->count()).' records.',
            [['label' => 'Attendance', 'href' => route('attendance.index')]],
        );
    }

    protected function awaitingApproval(User $user): array
    {
        if (! $user->hasPermission('grades.approve')) {
            return $this->refused('the approval queue');
        }

        $count = Assessment::where('status', 'submitted')->count();

        return $this->answered(
            $count === 0
                ? 'Nothing is waiting for approval.'
                : "{$count} ".Str::plural('assessment', $count).' submitted by teachers '
                    .($count === 1 ? 'is' : 'are').' waiting for approval.',
            [['label' => 'Grade approvals', 'href' => route('grades.approvals')]],
        );
    }

    protected function results(User $user): array
    {
        $guardian = $user->guardianProfile;

        if ($guardian !== null) {
            $term = Term::active();

            if ($term === null) {
                return $this->answered('The school has not set a current term yet, so there are no results to show.');
            }

            $children = $guardian->students()->get()
                ->filter(fn (Student $child) => $guardian->canViewAcademicsFor($child));

            if ($children->isEmpty()) {
                return $this->answered('You are not currently cleared to see academic records. The school office can change that.');
            }

            $lines = $children->map(function (Student $child) use ($term) {
                $results = $this->gradebook->termResults($child, $term);

                return $results['average'] === null
                    ? $child->full_name.': no approved results for '.$term->name.' yet.'
                    : $child->full_name.': '.$results['average'].'% average in '.$term->name
                        .($results['grade'] ? ' (grade '.$results['grade'].')' : '');
            });

            return $this->answered(
                $lines->join("\n"),
                [['label' => 'Results', 'href' => route('parent.grades')]],
            );
        }

        if (! $user->hasPermission('reportcards.view')) {
            return $this->refused('results');
        }

        return $this->answered(
            'Results are shown per class and term in the gradebook, with each student\'s average. '
                .'Only approved marks are counted.',
            [['label' => 'Gradebook', 'href' => route('gradebook.index')]],
        );
    }

    protected function admissions(User $user): array
    {
        if (! $user->hasPermission('admissions.view')) {
            return $this->refused('admissions');
        }

        $pending = Admission::whereIn('status', ['submitted', 'pending', 'under_review', 'documents_required'])->count();

        return $this->answered(
            $pending === 0
                ? 'There are no applications waiting for a decision.'
                : "{$pending} ".Str::plural('application', $pending).' '.($pending === 1 ? 'is' : 'are').' waiting for a decision.',
            [['label' => 'Admissions', 'href' => route('admissions.index')]],
        );
    }

    protected function calendar(User $user): array
    {
        $year = AcademicYear::active();
        $term = Term::active();

        if ($year === null) {
            return $this->answered('No academic year has been set up yet.');
        }

        return $this->answered(
            "The school is in {$year->name}"
                .($term ? ", {$term->name} ({$term->starts_on->format('j M Y')} to {$term->ends_on->format('j M Y')})." : ', with no current term set.'),
        );
    }

    protected function capabilities(User $user): array
    {
        return $this->answered(
            "I look things up in this school's own records and answer with what I find. "
                ."I only ever show what you are already allowed to see, and I run on this server - "
                ."nothing you ask me is sent anywhere else.\n\n"
                .'Try: '.collect($this->suggestions($user))->map(fn ($s) => '“'.$s.'”')->join(', ').'.',
        );
    }

    protected function unknown(User $user): array
    {
        /*
         | The honest failure. Guessing at an answer here would produce a fluent
         | sentence containing a number nobody checked, which in a system holding
         | marks and debts is worse than saying nothing.
         */
        return [
            'answer' => "I don't know that one. I can only answer from what this school has recorded, "
                ."and I would rather say so than guess at a number.\n\nTry: "
                .collect($this->suggestions($user))->map(fn ($s) => '“'.$s.'”')->join(', ').'.',
            'sources' => [],
            'handled' => false,
        ];
    }

    protected function refused(string $subject): array
    {
        return [
            'answer' => "You don't have permission to see {$subject} in this school, so I can't answer that. "
                .'An administrator can change what your account may reach.',
            'sources' => [],
            'handled' => true,
        ];
    }

    protected function answered(string $answer, array $sources = []): array
    {
        return ['answer' => $answer, 'sources' => $sources, 'handled' => true];
    }

    public function suggestions(User $user): array
    {
        if ($user->guardianProfile !== null) {
            return [
                'How is my child doing this term?',
                'What is my attendance record?',
                'Do I owe any fees?',
            ];
        }

        $suggestions = [];

        if ($user->hasPermission('students.view')) {
            $suggestions[] = 'How many students are enrolled?';
        }

        if ($user->hasPermission('payments.view')) {
            $suggestions[] = 'How much is outstanding in fees?';
        }

        if ($user->hasPermission('grades.approve')) {
            $suggestions[] = 'What grades are awaiting approval?';
        }

        if ($user->hasPermission('attendance.view')) {
            $suggestions[] = 'What is attendance like this month?';
        }

        $suggestions[] = 'Which term are we in?';

        return array_slice($suggestions, 0, 4);
    }
}
