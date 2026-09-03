<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\AttendanceAlert;
use App\Notifications\FeeReminder;
use App\Notifications\ReportCardPublished;
use App\Support\SchoolContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Works out who should hear about something, and tells them.
 *
 * Keeping the audience logic here means a controller only has to say what
 * happened, not who cares about it.
 */
class Notifier
{
    public function __construct(private readonly SchoolContext $context) {}

    /** Everyone holding a given permission in the current school. */
    public function usersWithPermission(string $permission): Collection
    {
        return User::inCurrentSchool()
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', $permission))
            ->get();
    }

    /** Tell the people who can act on it. */
    public function notifyPermission(string $permission, $notification): void
    {
        $recipients = $this->usersWithPermission($permission);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }

    /** Tell one person, if they have an account at all. */
    public function notifyUser(?User $user, $notification): void
    {
        $user?->notify($notification);
    }

    /**
     * Announcements reach whichever portals they were addressed to.
     */
    public function announcementPublished(Announcement $announcement): void
    {
        $recipients = collect();

        foreach ($announcement->audience ?? [] as $audience) {
            $recipients = $recipients->concat(match ($audience) {
                'parents' => $this->guardianAccounts($announcement),
                'students' => $this->studentAccounts($announcement),
                'teachers' => $this->accountsWithRole('teacher'),
                'staff' => $this->staffAccounts(),
                default => collect(),
            });
        }

        $recipients = $recipients->unique('id');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AnnouncementPublished($announcement));
        }
    }

    /**
     * Tell a student's guardians they were marked absent or late (spec
     * section 33). Only guardians cleared for academic information hear it.
     */
    public function attendanceAlert(AttendanceRecord $record): void
    {
        $student = $record->student;

        if ($student === null) {
            return;
        }

        $recipients = $student->guardians
            ->filter(fn (Guardian $guardian) => $guardian->user_id && $guardian->pivot->can_view_academics)
            ->map(fn (Guardian $guardian) => User::find($guardian->user_id))
            ->filter()
            ->unique('id');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AttendanceAlert($record));
        }
    }

    /**
     * Remind the guardians of a student with money outstanding.
     * Skips guardians who are not cleared for financial information.
     */
    public function feeReminder(Invoice $invoice): int
    {
        $student = $invoice->student;

        if ($student === null || $invoice->balanceMinor() === 0) {
            return 0;
        }

        $recipients = $student->guardians
            ->filter(fn (Guardian $guardian) => $guardian->user_id && $guardian->pivot->can_view_finance)
            ->map(fn (Guardian $guardian) => User::find($guardian->user_id))
            ->filter()
            ->unique('id');

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new FeeReminder($invoice));

        return $recipients->count();
    }

    /** A published report card reaches the student and their guardians. */
    public function reportCardPublished(ReportCard $card): void
    {
        $student = $card->student;

        if ($student === null) {
            return;
        }

        $recipients = collect();

        if ($student->user_id) {
            $recipients->push(User::find($student->user_id));
        }

        foreach ($student->guardians as $guardian) {
            if ($guardian->user_id && $guardian->pivot->can_view_academics) {
                $recipients->push(User::find($guardian->user_id));
            }
        }

        $recipients = $recipients->filter()->unique('id');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ReportCardPublished($card));
        }
    }

    /* ------------------------------------------------------------------ */

    protected function accountsWithRole(string $slug): Collection
    {
        return User::inCurrentSchool()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('slug', $slug))
            ->get();
    }

    /** Guardians, narrowed to one section when the announcement names one. */
    protected function guardianAccounts(Announcement $announcement): Collection
    {
        $guardians = Guardian::whereNotNull('user_id')
            ->when($announcement->section_id, fn ($query) => $query->whereHas(
                'students.enrollments',
                fn ($inner) => $inner->where('section_id', $announcement->section_id)
            ))
            ->get();

        return User::whereIn('id', $guardians->pluck('user_id'))->where('status', 'active')->get();
    }

    protected function studentAccounts(Announcement $announcement): Collection
    {
        $students = Student::whereNotNull('user_id')
            ->when($announcement->section_id, fn ($query) => $query->whereHas(
                'enrollments',
                fn ($inner) => $inner->where('section_id', $announcement->section_id)
            ))
            ->get();

        return User::whereIn('id', $students->pluck('user_id'))->where('status', 'active')->get();
    }

    /** Accounts that are neither a family member nor a student. */
    protected function staffAccounts(): Collection
    {
        return User::inCurrentSchool()
            ->where('status', 'active')
            ->whereDoesntHave('studentProfile')
            ->whereDoesntHave('guardianProfile')
            ->get();
    }
}
