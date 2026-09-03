<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\SchoolContext;
use Database\Seeders\DemoAcademicSeeder;
use Database\Seeders\GraceFoundationSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loads every signed-in page against a fully seeded school. This is the net
 * that catches a missing view, a bad route name or an undefined variable.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(GraceFoundationSeeder::class);
        $this->seed(DemoAcademicSeeder::class);
        $this->seed(WebsiteContentSeeder::class);

        $this->school = School::where('slug', 'grace-foundation-institution')->firstOrFail();
    }

    protected function administrator(): User
    {
        $user = User::where('email', 'admin@gracefoundation.edu.lr')->firstOrFail();

        return $user->fresh();
    }

    public static function adminPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'students' => ['students.index'],
            'guardians' => ['guardians.index'],
            'teachers' => ['teachers.index'],
            'academics' => ['academics.index'],
            'attendance' => ['attendance.index'],
            'admissions' => ['admissions.index'],
            'invoices' => ['invoices.index'],
            'payments' => ['payments.index'],
            'record payment' => ['payments.create'],
            'announcements' => ['announcements.index'],
            'new announcement' => ['announcements.create'],
            'events' => ['events.index'],
            'parent requests' => ['requests.index'],
            'reports' => ['reports.index'],
            'assessments' => ['assessments.index'],
            'grade approvals' => ['grades.approvals'],
            'examinations' => ['examinations.index'],
            'report cards' => ['reportcards.index'],
            'timetable' => ['timetable.index'],
            'fee structures' => ['fees.index'],
            'messages' => ['messages.index'],
            'notifications' => ['notifications.index'],
            'website' => ['website.index'],
            'expenses' => ['expenses.index'],
            'documents' => ['documents.index'],
            'users' => ['users.index'],
            'roles' => ['roles.index'],
            'student permissions' => ['students.permissions'],
            'settings' => ['settings.school.edit'],
            'audit' => ['audit.index'],
        ];
    }

    /** @dataProvider adminPages */
    public function test_admin_pages_load(string $routeName): void
    {
        $this->actingAs($this->administrator())
            ->get(route($routeName))
            ->assertOk();
    }

    public function test_parent_portal_pages_load(): void
    {
        $parent = User::where('email', 'parent@gracefoundation.edu.lr')->firstOrFail();

        foreach (['parent.dashboard', 'parent.grades', 'parent.attendance', 'parent.fees', 'parent.teachers', 'parent.requests'] as $routeName) {
            $this->actingAs($parent)->get(route($routeName))->assertOk();
        }
    }

    public function test_student_portal_pages_load(): void
    {
        $student = User::where('email', 'student@gracefoundation.edu.lr')->firstOrFail();

        foreach ([
            'student.dashboard', 'student.grades', 'student.attendance',
            'student.timetable', 'student.assignments',
        ] as $routeName) {
            $this->actingAs($student)->get(route($routeName))->assertOk();
        }
    }

    public function test_teacher_portal_pages_load(): void
    {
        $teacher = User::where('email', 'teacher@gracefoundation.edu.lr')->firstOrFail();

        foreach (['teaching.dashboard', 'teaching.classes', 'teaching.students', 'teaching.timetable'] as $routeName) {
            $this->actingAs($teacher)->get(route($routeName))->assertOk();
        }
    }

    public function test_public_pages_load(): void
    {
        foreach ([
            'home', 'public.about', 'public.academics', 'public.contact',
            'apply', 'public.news', 'public.gallery', 'public.events',
            'public.admissions', 'public.teachers',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_portal_entry_routes_each_account_to_its_own_portal(): void
    {
        $this->actingAs(User::where('email', 'parent@gracefoundation.edu.lr')->firstOrFail())
            ->get(route('portal'))->assertRedirect(route('parent.dashboard'));

        $this->actingAs(User::where('email', 'student@gracefoundation.edu.lr')->firstOrFail())
            ->get(route('portal'))->assertRedirect(route('student.dashboard'));

        $this->actingAs(User::where('email', 'teacher@gracefoundation.edu.lr')->firstOrFail())
            ->get(route('portal'))->assertRedirect(route('teaching.dashboard'));
    }
}
