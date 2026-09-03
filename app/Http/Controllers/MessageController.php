<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use App\Services\StudentAccess;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controlled messaging (spec section 45).
 *
 * Two rules decide everything here:
 *
 *   1. A thread is only readable by the people listed as its participants.
 *   2. Who you may start a thread with depends on your role — a parent may
 *      write to staff and to their children's teachers, never to another
 *      parent or to a student.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->messagingUser($request);

        $threads = MessageThread::visibleTo($user)
            ->with(['participants:id,name', 'student:id,first_name,last_name'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('messages.index', [
            'threads' => $threads,
            'recipients' => $this->availableRecipients($user),
            'students' => $this->relatedStudents($user),
        ]);
    }

    public function show(Request $request, MessageThread $messageThread): View
    {
        $user = $this->messagingUser($request);

        abort_unless($messageThread->includes($user), 403, 'This conversation is not yours.');

        $messageThread->load(['messages.author:id,name', 'participants:id,name', 'student']);

        // Opening a thread marks it read for this person only.
        $messageThread->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return view('messages.show', [
            'thread' => $messageThread,
            'user' => $user,
        ]);
    }

    public function store(Request $request, SchoolContext $context): RedirectResponse
    {
        $user = $this->messagingUser($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:4000'],
            'recipient_id' => ['required', 'integer'],
            'student_id' => ['nullable', 'integer'],
        ]);

        $recipient = $this->availableRecipients($user)->firstWhere('id', $data['recipient_id']);

        abort_unless($recipient !== null, 403, 'You are not able to message that person.');

        $student = null;

        if (! empty($data['student_id'])) {
            $student = $this->relatedStudents($user)->firstWhere('id', $data['student_id']);

            abort_unless($student !== null, 403, 'That student is not linked to your account.');
        }

        $thread = DB::transaction(function () use ($data, $user, $recipient, $student, $context) {
            $thread = MessageThread::create([
                'school_id' => $context->schoolId(),
                'started_by' => $user->id,
                'student_id' => $student?->id,
                'subject' => $data['subject'],
                'status' => 'open',
                'last_message_at' => now(),
            ]);

            // Both rows carry the same pivot columns: attach() batches the
            // insert, and a batch with differing columns is rejected.
            $thread->participants()->attach([
                $user->id => ['school_id' => $thread->school_id, 'last_read_at' => now()],
                $recipient->id => ['school_id' => $thread->school_id, 'last_read_at' => null],
            ]);

            Message::create([
                'school_id' => $thread->school_id,
                'message_thread_id' => $thread->id,
                'user_id' => $user->id,
                'body' => $data['body'],
            ]);

            return $thread;
        });

        $recipient->notify(new NewMessageReceived($thread, $user));

        return redirect()
            ->route('messages.show', $thread)
            ->with('status', 'Message sent.');
    }

    public function reply(Request $request, MessageThread $messageThread): RedirectResponse
    {
        $user = $this->messagingUser($request);

        abort_unless($messageThread->includes($user), 403);
        abort_if($messageThread->status === 'closed', 422, 'This conversation has been closed.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($messageThread, $user, $data) {
            Message::create([
                'school_id' => $messageThread->school_id,
                'message_thread_id' => $messageThread->id,
                'user_id' => $user->id,
                'body' => $data['body'],
            ]);

            $messageThread->update(['last_message_at' => now()]);

            $messageThread->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        });

        // Everyone else in the thread hears about it.
        $messageThread->participants
            ->reject(fn (User $participant) => $participant->id === $user->id)
            ->each(fn (User $participant) => $participant->notify(new NewMessageReceived($messageThread, $user)));

        return back()->with('status', 'Reply sent.');
    }

    public function close(Request $request, MessageThread $messageThread): RedirectResponse
    {
        $user = $this->messagingUser($request);

        abort_unless($messageThread->includes($user), 403);

        $messageThread->update(['status' => $messageThread->status === 'closed' ? 'open' : 'closed']);

        return back()->with('status', 'Conversation '.$messageThread->status.'.');
    }

    /* ------------------------------------------------------------------ */

    protected function messagingUser(Request $request): User
    {
        $user = $request->user();

        /*
         | A student's messaging is governed by their own `send_messages`
         | permission, which defaults to OFF. This used to read "parents and
         | students always have a voice", which meant the switch on the student
         | permissions screen did nothing at all: a school could turn student
         | messaging off, see it turned off, and every student could still write
         | to every teacher. Section 45 says "student to teacher where
         | permitted", and this is where that is decided.
         */
        if ($student = $user->studentProfile) {
            abort_unless(
                app(StudentAccess::class)->allows($student, 'send_messages'),
                403,
                'Messaging is not switched on for your account. Your school can change that.'
            );

            return $user;
        }

        // A guardian always has a voice; staff need the permission.
        if ($user->guardianProfile()->exists()) {
            return $user;
        }

        abort_unless(
            $user->hasPermission('messages.send'),
            403,
            'Your role does not include messaging.'
        );

        return $user;
    }

    /**
     * Who this account may open a conversation with.
     *
     * @return Collection<int, User>
     */
    protected function availableRecipients(User $user): Collection
    {
        $guardian = $user->guardianProfile;
        $student = $user->studentProfile;

        // Staff accounts: everyone with a school account except students,
        // whose contact goes through their teachers.
        if (! $guardian && ! $student) {
            return User::inCurrentSchool()
                ->where('id', '!=', $user->id)
                ->where('status', 'active')
                ->whereDoesntHave('studentProfile')
                ->orderBy('name')
                ->get();
        }

        // A student writes to the teachers who teach them.
        if ($student) {
            return $this->teachersFor(collect([$student]));
        }

        // A parent writes to their children's teachers, plus school staff.
        $children = $guardian->students;

        $teacherUsers = $this->teachersFor($children);

        $staff = User::inCurrentSchool()
            ->where('status', 'active')
            ->whereDoesntHave('studentProfile')
            ->whereDoesntHave('guardianProfile')
            ->orderBy('name')
            ->get();

        return $teacherUsers->concat($staff)->unique('id')->values();
    }

    /**
     * The user accounts of teachers who teach any of the given students.
     *
     * @return Collection<int, User>
     */
    protected function teachersFor(Collection $students): Collection
    {
        $sectionIds = $students
            ->flatMap(fn (Student $student) => $student->enrollments->pluck('section_id'))
            ->filter()
            ->unique();

        if ($sectionIds->isEmpty()) {
            return collect();
        }

        $teacherIds = Teacher::whereHas(
            'teachingAssignments',
            fn ($query) => $query->whereIn('section_id', $sectionIds->all())
        )->pluck('user_id')->filter();

        return User::whereIn('id', $teacherIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Student> */
    protected function relatedStudents(User $user): Collection
    {
        if ($guardian = $user->guardianProfile) {
            return $guardian->students;
        }

        if ($student = $user->studentProfile) {
            return collect([$student]);
        }

        return Student::orderBy('last_name')->get();
    }
}
