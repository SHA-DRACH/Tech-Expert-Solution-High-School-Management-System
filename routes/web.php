<?php

use App\Http\Controllers\AcademicCalendarController;
use App\Http\Controllers\AcademicsController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GradeApprovalController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MarkImportController;
use App\Http\Controllers\MarkSheetController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParentRequestController;
use App\Http\Controllers\PlatformSchoolController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\StudentAssignmentController;
use App\Http\Controllers\Portal\StudentPortalController;
use App\Http\Controllers\Portal\TeacherPortalController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAdmissionController;
use App\Http\Controllers\PublicSchoolController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPermissionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeachingAssignmentController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WebsiteContentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
| Served for whichever school owns the current host. ResolveSchoolContext
| runs on every web request and pins the tenant before anything is queried.
*/

Route::get('/', [PublicSchoolController::class, 'home'])->name('home');
Route::get('/about', [PublicSchoolController::class, 'about'])->name('public.about');
Route::get('/academics', [PublicSchoolController::class, 'academics'])->name('public.academics');
Route::get('/contact', [PublicSchoolController::class, 'contact'])->name('public.contact');

Route::get('/admissions', [PublicSchoolController::class, 'admissions'])->name('public.admissions');
Route::get('/teachers-and-staff', [PublicSchoolController::class, 'teachers'])->name('public.teachers');

Route::get('/news', [PublicSchoolController::class, 'news'])->name('public.news');
Route::get('/news/{slug}', [PublicSchoolController::class, 'newsPost'])->name('public.news.show');
Route::get('/gallery', [PublicSchoolController::class, 'gallery'])->name('public.gallery');
Route::get('/events', [PublicSchoolController::class, 'events'])->name('public.events');
Route::get('/apply', [PublicAdmissionController::class, 'create'])->name('apply');
Route::post('/apply', [PublicAdmissionController::class, 'store'])->middleware('throttle:5,1')->name('apply.store');
Route::get('/apply/complete', [PublicAdmissionController::class, 'complete'])->name('apply.complete');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Signed-in area
|--------------------------------------------------------------------------
| Gated twice: 'permission' middleware keeps the request out of the
| controller, and policies re-check ownership record by record.
*/

Route::middleware(['auth', 'school.context'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    /* ---------------------------------------------------------------- */
    /* Portals: parent, student and teacher                             */
    /* ---------------------------------------------------------------- */

    // Sends each account to the portal that matches its role.
    Route::get('/portal', PortalController::class)->name('portal');

    /*
    | The question paper for a piece of work. Outside the permission groups
    | because the people who most need it — the students it was set for and
    | their guardians — hold no assessment permissions at all; the controller
    | checks that they are the family it was set for.
    */
    Route::get('/assessments/{assessment}/question', [AssessmentController::class, 'downloadQuestion'])
        ->name('assessments.question');

    /*
    | "My account". Deliberately behind no permission slug: every signed-in
    | person may edit their own profile, including a parent who holds no
    | permissions at all, and nobody may edit anyone else's from here.
    | Managing *other* people's accounts is users.update, elsewhere.
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo');

    /*
    | The assistant. Answers run as the person asking, so it can never reach
    | data they could not open directly.
    */
    Route::post('/assistant/ask', [AssistantController::class, 'ask'])
        ->middleware('throttle:20,1')
        ->name('assistant.ask');

    Route::get('/assistant/suggestions', [AssistantController::class, 'suggestions'])->name('assistant.suggestions');

    Route::prefix('parent')->name('parent.')->group(function () {
        Route::get('/', [ParentPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/grades', [ParentPortalController::class, 'grades'])->name('grades');
        Route::get('/assignments', [ParentPortalController::class, 'assignments'])->name('assignments');
        Route::get('/attendance', [ParentPortalController::class, 'attendance'])->name('attendance');
        Route::get('/fees', [ParentPortalController::class, 'fees'])->name('fees');
        Route::get('/teachers', [ParentPortalController::class, 'teachers'])->name('teachers');
        Route::get('/requests', [ParentPortalController::class, 'requests'])->name('requests');
        Route::post('/requests', [ParentPortalController::class, 'storeRequest'])->name('requests.store');
    });

    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/', [StudentPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/grades', [StudentPortalController::class, 'grades'])->name('grades');
        Route::get('/attendance', [StudentPortalController::class, 'attendance'])->name('attendance');
        Route::get('/timetable', [StudentPortalController::class, 'timetable'])->name('timetable');

        // Assignments: viewing and submitting are separately switchable.
        Route::get('/assignments', [StudentAssignmentController::class, 'index'])->name('assignments');
        Route::post('/assignments/{assessment}', [StudentAssignmentController::class, 'store'])->name('assignments.submit');

        /*
        | Spec section 23. Each resolves the student from the signed-in user and
        | reads through their own enrolment, so nothing here can reach another
        | student's records — the sentence that section ends on.
        */
        Route::get('/subjects', [StudentPortalController::class, 'subjects'])->name('subjects');
        Route::get('/class', [StudentPortalController::class, 'schoolClass'])->name('class');
        Route::get('/exams', [StudentPortalController::class, 'exams'])->name('exams');
        Route::get('/report-cards', [StudentPortalController::class, 'reportCards'])->name('reportcards');
        Route::get('/announcements', [StudentPortalController::class, 'announcementsPage'])->name('announcements');
        Route::get('/fees', [StudentPortalController::class, 'fees'])->name('fees');
    });

    Route::get('/submissions/{submission}/download', [StudentAssignmentController::class, 'download'])
        ->name('submissions.download');

    Route::prefix('teaching')->name('teaching.')->group(function () {
        Route::get('/', [TeacherPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/classes', [TeacherPortalController::class, 'classes'])->name('classes');
        Route::get('/students', [TeacherPortalController::class, 'students'])->name('students');
        Route::get('/timetable', [TeacherPortalController::class, 'timetable'])->name('timetable');

        /*
        | Spec section 27. Each of these resolves the teacher from the signed-in
        | user and reads only through their teaching assignments, so a section
        | or subject id in the URL cannot widen what they reach.
        */
        Route::get('/subjects', [TeacherPortalController::class, 'subjects'])->name('subjects');
        Route::get('/assignments', [TeacherPortalController::class, 'assignmentsIndex'])->name('assignments');
        Route::get('/announcements', [TeacherPortalController::class, 'announcements'])->name('announcements');
    });

    /* ---------------------------------------------------------------- */
    /* Students and families                                            */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:students.view')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    });

    Route::middleware('permission:students.create')->group(function () {
        Route::get('/students/create/new', [StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    });

    Route::middleware('permission:students.update')->group(function () {
        Route::get('/student-permissions', [StudentPermissionController::class, 'index'])->name('students.permissions');
        Route::put('/student-permissions', [StudentPermissionController::class, 'updateDefaults'])->name('students.permissions.defaults');
        Route::get('/students/{student}/permissions', [StudentPermissionController::class, 'edit'])->name('students.permissions.edit');
        Route::put('/students/{student}/permissions', [StudentPermissionController::class, 'update'])->name('students.permissions.update');

        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');

        /*
        | Placing a student in a class, and the subjects they take there.
        | Nothing in the application created an enrolment before this: the rows
        | existed because a seeder wrote them, so a student added through the
        | interface stayed "not assigned" for ever.
        */
        Route::post('/students/{student}/enrolment', [EnrollmentController::class, 'store'])->name('students.enrol');
        Route::put('/students/{student}/subjects', [EnrollmentController::class, 'updateSubjects'])->name('students.subjects.update');
        Route::delete('/enrolments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('students.enrolment.destroy');
    });

    /*
    | Archiving, not deleting. A student record anchors enrolments, marks,
    | attendance, invoices and report cards; removing it would leave all of
    | that pointing at nobody.
    */
    Route::middleware('permission:students.archive')->group(function () {
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::post('/students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore');
    });

    Route::middleware('permission:guardians.view')->group(function () {
        Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
        Route::get('/guardians/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
    });

    Route::middleware('permission:guardians.create')->group(function () {
        Route::get('/guardians/create/new', [GuardianController::class, 'create'])->name('guardians.create');
        Route::post('/guardians', [GuardianController::class, 'store'])->name('guardians.store');
    });

    Route::middleware('permission:guardians.update')->group(function () {
        Route::get('/guardians/{guardian}/edit', [GuardianController::class, 'edit'])->name('guardians.edit');
        Route::put('/guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
    });

    // Refused while the guardian is still the only contact for a child.
    Route::middleware('permission:guardians.archive')->group(function () {
        Route::delete('/guardians/{guardian}', [GuardianController::class, 'destroy'])->name('guardians.destroy');
        Route::post('/guardians/{guardian}/restore', [GuardianController::class, 'restore'])->name('guardians.restore');
    });

    /* ---------------------------------------------------------------- */
    /* Teachers and academics                                           */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:teachers.view')->group(function () {
        Route::get('/teachers', [TeacherController::class, 'index'])->name('teachers.index');
        Route::get('/teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show');
    });

    Route::middleware('permission:teachers.create')->group(function () {
        Route::get('/teachers/create/new', [TeacherController::class, 'create'])->name('teachers.create');
        Route::post('/teachers', [TeacherController::class, 'store'])->name('teachers.store');
    });

    Route::middleware('permission:teachers.update')->group(function () {
        Route::get('/teachers/{teacher}/edit', [TeacherController::class, 'edit'])->name('teachers.edit');
        Route::put('/teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');

        Route::post('/teachers/{teacher}/qualifications', [TeacherController::class, 'storeQualification'])
            ->name('teachers.qualifications.store');
    });

    /*
    | Archiving, not deleting. A teacher's name is attached to marks, lessons
    | and report cards; the record is soft-deleted so that history still
    | resolves, and can be restored.
    */
    /*
    | Giving a member of staff a login, from their own record. Gated on
    | users.create, not teachers.update: it creates an account, and that is a
    | different kind of authority from editing a staff record.
    */
    Route::post('/teachers/{teacher}/login', [TeacherController::class, 'createLogin'])
        ->middleware('permission:users.create')
        ->name('teachers.login.store');

    Route::middleware('permission:teachers.archive')->group(function () {
        Route::delete('/teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');
        Route::post('/teachers/{teacher}/restore', [TeacherController::class, 'restore'])->name('teachers.restore');
    });

    /*
    | CSV import. Uploading previews; a second, explicit confirmation writes.
    | The permission for each import type is declared in the controller.
    */
    Route::prefix('imports')->name('imports.')->group(function () {
        Route::get('/{type}', [ImportController::class, 'create'])->name('create');
        Route::post('/{type}/preview', [ImportController::class, 'preview'])->name('preview');
        Route::post('/{type}', [ImportController::class, 'store'])->name('store');
    });

    // Distinct from the public /academics page, which is the website's
    // programmes page and must keep that URL.
    Route::get('/academic-structure', [AcademicsController::class, 'index'])
        ->middleware('permission:academics.view')
        ->name('academics.index');

    Route::middleware('permission:academics.manage')->group(function () {
        Route::post('/academic-structure/classes', [AcademicsController::class, 'storeClass'])->name('academics.classes.store');
        Route::post('/academic-structure/sections', [AcademicsController::class, 'storeSection'])->name('academics.sections.store');
        Route::post('/academic-structure/subjects', [AcademicsController::class, 'storeSubject'])->name('academics.subjects.store');
        Route::post('/academic-structure/departments', [AcademicsController::class, 'storeDepartment'])->name('academics.departments.store');

        /*
        | Editing and archiving the structure. Archiving refuses outright while
        | a record still anchors enrolments or marks: last year's report cards
        | name the class a child was in, and removing it would leave them
        | pointing at nothing.
        */
        Route::put('/academic-structure/classes/{schoolClass}', [AcademicsController::class, 'updateClass'])->name('academics.classes.update');
        Route::delete('/academic-structure/classes/{schoolClass}', [AcademicsController::class, 'destroyClass'])->name('academics.classes.destroy');

        Route::put('/academic-structure/sections/{section}', [AcademicsController::class, 'updateSection'])->name('academics.sections.update');
        Route::delete('/academic-structure/sections/{section}', [AcademicsController::class, 'destroySection'])->name('academics.sections.destroy');

        Route::put('/academic-structure/subjects/{subject}', [AcademicsController::class, 'updateSubject'])->name('academics.subjects.update');
        Route::delete('/academic-structure/subjects/{subject}', [AcademicsController::class, 'destroySubject'])->name('academics.subjects.destroy');

        Route::put('/academic-structure/departments/{department}', [AcademicsController::class, 'updateDepartment'])->name('academics.departments.update');
        Route::delete('/academic-structure/departments/{department}', [AcademicsController::class, 'destroyDepartment'])->name('academics.departments.destroy');

        /*
        | Who teaches what. AssessmentPolicy reads this table to decide whether
        | a teacher may enter marks, so without it a teacher has no subjects and
        | can record nothing.
        */
        Route::post('/teaching-assignments', [TeachingAssignmentController::class, 'store'])->name('assignments.store');
        Route::delete('/teaching-assignments/{assignment}', [TeachingAssignmentController::class, 'destroy'])->name('assignments.destroy');
    });

    Route::get('/teaching-assignments', [TeachingAssignmentController::class, 'index'])
        ->middleware('permission:academics.view')
        ->name('assignments.index');

    /*
    | Subject management (section 29): name, code, department, the grades that
    | take it, and who teaches it. The last two had nowhere to be entered -
    | `class_subject` in particular had no writer anywhere in the application,
    | so a newly created subject reached no mark sheet at all.
    */
    Route::get('/subjects', [SubjectController::class, 'index'])
        ->middleware('permission:academics.view')
        ->name('subjects.index');

    Route::middleware('permission:academics.manage')->group(function () {
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
        Route::post('/subjects/{subject}/teachers', [SubjectController::class, 'assignTeacher'])->name('subjects.teachers.store');
    });

    /* ---------------------------------------------------------------- */
    /* Examinations, grades and report cards                            */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:exams.view,grades.enter,grades.approve')->group(function () {
        Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::get('/assessments/{assessment}/scores', [AssessmentController::class, 'scores'])->name('assessments.scores');
    });

    /*
    | A teacher uploading a class's marks as a spreadsheet. Confirming the
    | upload writes the marks and submits them for approval in one transaction,
    | which locks them: the policy behind these routes requires the assessment
    | to still be editable, and a submitted one is not.
    */
    Route::middleware('permission:grades.enter')->group(function () {
        Route::get('/assessments/{assessment}/marks/import', [MarkImportController::class, 'create'])->name('assessments.marks.import');
        Route::get('/assessments/{assessment}/marks/template', [MarkImportController::class, 'template'])->name('assessments.marks.template');
        Route::post('/assessments/{assessment}/marks/preview', [MarkImportController::class, 'preview'])->name('assessments.marks.preview');
        Route::post('/assessments/{assessment}/marks', [MarkImportController::class, 'store'])->name('assessments.marks.store');
    });

    /*
    | The mark sheet: one class, one subject, every student, every assessment.
    | Open to whoever may enter marks or approve them; which rows are writable
    | is decided per assessment inside the controller.
    */
    Route::middleware('permission:grades.enter,grades.approve')->group(function () {
        Route::get('/mark-sheet', [MarkSheetController::class, 'index'])->name('marks.index');
        Route::post('/mark-sheet', [MarkSheetController::class, 'store'])->name('marks.store');
    });

    /*
    | The gradebook: every approved result in one table, per class and term.
    | Correcting an approved mark is gated on grades.approve, not grades.enter -
    | changing a mark after sign-off belongs with the office that signed it off.
    */
    Route::middleware('permission:reportcards.view')->group(function () {
        Route::get('/gradebook', [GradebookController::class, 'index'])->name('gradebook.index');
        Route::get('/gradebook/students/{student}', [GradebookController::class, 'show'])->name('gradebook.student');
    });

    Route::middleware('permission:grades.approve')->group(function () {
        Route::put('/gradebook/scores/{score}', [GradebookController::class, 'updateScore'])->name('gradebook.scores.update');
        Route::delete('/gradebook/scores/{score}', [GradebookController::class, 'destroyScore'])->name('gradebook.scores.destroy');
    });

    Route::middleware('permission:grades.enter,exams.manage')->group(function () {
        Route::get('/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
        Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/assessments/{assessment}/edit', [AssessmentController::class, 'edit'])->name('assessments.edit');
        Route::put('/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
        Route::put('/assessments/{assessment}/scores', [AssessmentController::class, 'saveScores'])->name('assessments.scores.save');
        Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit'])->name('assessments.submit');
        Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
    });

    Route::middleware('permission:grades.approve')->group(function () {
        Route::get('/grade-approvals', [GradeApprovalController::class, 'index'])->name('grades.approvals');
        Route::get('/grade-approvals/{assessment}', [GradeApprovalController::class, 'show'])->name('grades.review');
        Route::post('/grade-approvals/{assessment}/approve', [GradeApprovalController::class, 'approve'])->name('grades.approve');
        Route::post('/grade-approvals/{assessment}/reject', [GradeApprovalController::class, 'reject'])->name('grades.reject');
    });

    Route::get('/examinations', [ExaminationController::class, 'index'])
        ->middleware('permission:exams.view')
        ->name('examinations.index');

    Route::middleware('permission:exams.manage')->group(function () {
        Route::post('/examinations', [ExaminationController::class, 'store'])->name('examinations.store');
        Route::put('/examinations/{examination}', [ExaminationController::class, 'update'])->name('examinations.update');
        Route::put('/grading-scale', [ExaminationController::class, 'updateScale'])->name('examinations.scale');
    });

    Route::get('/report-cards', [ReportCardController::class, 'index'])
        ->middleware('permission:reportcards.view')
        ->name('reportcards.index');

    // Individual cards are also opened by parents and students, so the
    // controller decides access rather than a permission on the route.
    Route::get('/report-cards/{reportCard}', [ReportCardController::class, 'show'])->name('reportcards.show');

    /*
    | Section 38: parents can view, download and print. Authorization is the
    | same as viewing - the controller re-checks it - plus a student's own
    | `download_report_card` permission where the account is a student's.
    */
    Route::get('/report-cards/{reportCard}/download', [ReportCardController::class, 'download'])->name('reportcards.download');

    Route::middleware('permission:reportcards.generate')->group(function () {
        Route::post('/report-cards/generate', [ReportCardController::class, 'generate'])->name('reportcards.generate');
        Route::post('/report-cards/publish', [ReportCardController::class, 'publish'])->name('reportcards.publish');
        Route::put('/report-cards/{reportCard}', [ReportCardController::class, 'update'])->name('reportcards.update');
    });
    /* ---------------------------------------------------------------- */
    /* Attendance                                                       */
    /* ---------------------------------------------------------------- */

    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->middleware('permission:attendance.view')
        ->name('attendance.index');

    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->middleware('permission:attendance.record')
        ->name('attendance.store');

    /* ---------------------------------------------------------------- */
    /* Admissions                                                       */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:admissions.view')->group(function () {
        Route::get('/admission-applications', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('/admission-applications/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
        Route::get('/admission-applications/documents/{document}/download', [AdmissionController::class, 'downloadDocument'])
            ->name('admissions.documents.download');
    });

    Route::patch('/admission-applications/{admission}', [AdmissionController::class, 'update'])
        ->middleware('permission:admissions.review')
        ->name('admissions.update');

    Route::patch('/admission-applications/documents/{document}', [AdmissionController::class, 'reviewDocument'])
        ->middleware('permission:admissions.documents.verify')
        ->name('admissions.documents.review');

    /* ---------------------------------------------------------------- */
    /* Finance                                                          */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/invoices', [FinanceController::class, 'invoices'])->name('invoices.index');
        Route::get('/payments', [FinanceController::class, 'payments'])->name('payments.index');
        Route::get('/payments/{payment}/receipt', [FinanceController::class, 'receipt'])->name('payments.receipt');
    });

    Route::middleware('permission:payments.record')->group(function () {
        Route::get('/payments/create', [FinanceController::class, 'createPayment'])->name('payments.create');
        Route::post('/payments', [FinanceController::class, 'storePayment'])->name('payments.store');
    });

    /* ---------------------------------------------------------------- */
    /* Timetable                                                        */
    /* ---------------------------------------------------------------- */

    Route::get('/timetable', [TimetableController::class, 'index'])
        ->middleware('permission:timetable.view')
        ->name('timetable.index');

    Route::middleware('permission:timetable.manage')->group(function () {
        Route::post('/timetable', [TimetableController::class, 'store'])->name('timetable.store');
        Route::put('/timetable/{timetableEntry}', [TimetableController::class, 'update'])->name('timetable.update');
        Route::delete('/timetable/{timetableEntry}', [TimetableController::class, 'destroy'])->name('timetable.destroy');
    });

    /* ---------------------------------------------------------------- */
    /* Fee structures                                                   */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:fees.manage')->group(function () {
        Route::get('/fee-structures', [FeeStructureController::class, 'index'])->name('fees.index');
        Route::post('/fee-structures', [FeeStructureController::class, 'store'])->name('fees.store');
        Route::put('/fee-structures/{feeStructure}', [FeeStructureController::class, 'update'])->name('fees.update');
        Route::delete('/fee-structures/{feeStructure}', [FeeStructureController::class, 'destroy'])->name('fees.destroy');
    });

    Route::post('/fee-structures/raise-invoices', [FeeStructureController::class, 'raiseInvoices'])
        ->middleware('permission:invoices.manage')
        ->name('fees.raise');
    /* ---------------------------------------------------------------- */
    /* Documents                                                        */
    /* ---------------------------------------------------------------- */

    // Access is decided per document by DocumentPolicy: staff by permission,
    // and a student or their guardian for that student's own file.
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::patch('/documents/{document}', [DocumentController::class, 'verify'])->name('documents.verify');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::post('/document-types', [DocumentController::class, 'storeType'])->name('documents.types.store');
        Route::delete('/document-types/{documentType}', [DocumentController::class, 'destroyType'])->name('documents.types.destroy');
    });
    /* ---------------------------------------------------------------- */
    /* Expenses and exports                                             */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:expenses.manage')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // Each export carries the same permission as the screen it comes from.
    Route::prefix('exports')->name('exports.')->group(function () {
        Route::get('/students', [ExportController::class, 'students'])->name('students');
        Route::get('/teachers', [ExportController::class, 'teachers'])->name('teachers');
        Route::get('/users', [UserManagementController::class, 'export'])->name('users');
        Route::get('/guardians', [ExportController::class, 'guardians'])->name('guardians');
        Route::get('/attendance', [ExportController::class, 'attendance'])->name('attendance');
        Route::get('/payments', [ExportController::class, 'payments'])->name('payments');
        Route::get('/outstanding-fees', [ExportController::class, 'outstanding'])->name('outstanding');
    });
    /* ---------------------------------------------------------------- */
    /* Communication                                                    */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:announcements.manage')->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::post('/announcements/{announcement}/restore', [AnnouncementController::class, 'restore'])->name('announcements.restore');
    });

    Route::middleware('permission:events.manage')->group(function () {
        // Distinct from the public /events page, which lists the calendar for
        // families. Two routes on the same method and URI would silently
        // replace one another, taking the earlier route's name with it.
        Route::get('/school-events', [EventController::class, 'index'])->name('events.index');
        Route::post('/school-events', [EventController::class, 'store'])->name('events.store');
        Route::put('/school-events/{event}', [EventController::class, 'update'])->name('events.update');

        // Cancelling keeps it on the calendar saying so, which is what families
        // who already have it in their diary need. Deleting is for a mistake.
        Route::post('/school-events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
        Route::delete('/school-events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    });

    Route::middleware('permission:requests.manage')->group(function () {
        Route::get('/parent-requests', [ParentRequestController::class, 'index'])->name('requests.index');
        Route::patch('/parent-requests/{parentRequest}', [ParentRequestController::class, 'respond'])->name('requests.respond');
    });

    /* ---------------------------------------------------------------- */
    /* Messaging and notifications                                      */
    /* ---------------------------------------------------------------- */

    // Access is decided inside the controller: parents and students always
    // have a voice, staff need the messages.send permission.
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{messageThread}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{messageThread}/reply', [MessageController::class, 'reply'])->name('messages.reply');
    Route::post('/messages/{messageThread}/close', [MessageController::class, 'close'])->name('messages.close');

    // Notifications belong to the person, so every signed-in account has them.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::get('/notifications/{notification}', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    /* ---------------------------------------------------------------- */
    /* Website CMS                                                      */
    /* ---------------------------------------------------------------- */

    Route::middleware('permission:website.manage')->group(function () {
        Route::get('/website', [WebsiteContentController::class, 'index'])->name('website.index');
        Route::get('/website/pages/{websitePage}', [WebsiteContentController::class, 'editPage'])->name('website.pages.edit');
        Route::put('/website/pages/{websitePage}', [WebsiteContentController::class, 'updatePage'])->name('website.pages.update');

        Route::post('/website/news', [WebsiteContentController::class, 'storeNews'])->name('website.news.store');
        Route::put('/website/news/{newsPost}', [WebsiteContentController::class, 'updateNews'])->name('website.news.update');
        Route::delete('/website/news/{newsPost}', [WebsiteContentController::class, 'destroyNews'])->name('website.news.destroy');

        Route::post('/website/gallery', [WebsiteContentController::class, 'storeGallery'])->name('website.gallery.store');
        Route::delete('/website/gallery/{galleryItem}', [WebsiteContentController::class, 'destroyGallery'])->name('website.gallery.destroy');

        /*
        | The gallery on its own screen. It was a tab inside the website
        | editor, which put uploading a photograph three clicks deep in a page
        | about page content.
        */
        Route::get('/gallery-manager', [GalleryController::class, 'index'])->name('gallery.index');
        Route::post('/gallery-manager', [GalleryController::class, 'store'])->name('gallery.store');
        Route::put('/gallery-manager/{galleryItem}', [GalleryController::class, 'update'])->name('gallery.update');
        Route::delete('/gallery-manager/{galleryItem}', [GalleryController::class, 'destroy'])->name('gallery.destroy');

        Route::put('/website/social-links', [WebsiteContentController::class, 'updateSocialLinks'])->name('website.social.update');
        Route::put('/website/visibility', [WebsiteContentController::class, 'updateVisibility'])->name('website.visibility');
    });
    /* ---------------------------------------------------------------- */
    /* Reports, users, roles, settings, audit                           */
    /* ---------------------------------------------------------------- */

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');

    Route::get('/users', [UserManagementController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');

    Route::middleware('permission:users.create')->group(function () {
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    });

    Route::patch('/users/{user}/status', [UserManagementController::class, 'toggleStatus'])
        ->middleware('permission:users.suspend')
        ->name('users.status');

    /*
    | Viewing and editing one account. Declared after /users/create so the
    | literal segment is never swallowed by the {user} wildcard.
    */
    Route::get('/users/{user}', [UserManagementController::class, 'show'])
        ->middleware('permission:users.view')
        ->name('users.show');

    Route::middleware('permission:users.update')->group(function () {
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::get('/audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SchoolSettingsController::class, 'index'])->name('settings.index');

        Route::get('/settings/school', [SchoolSettingsController::class, 'edit'])->name('settings.school.edit');
        Route::put('/settings/school', [SchoolSettingsController::class, 'update'])->name('settings.school.update');

        /*
        | The school's own preferences, one tab per group. `{group}` is checked
        | against the catalogue in the controller, so an unknown group is a 404
        | rather than an empty page.
        */
        Route::get('/settings/preferences/{group}', [SchoolSettingsController::class, 'editGroup'])->name('settings.group.edit');
        Route::put('/settings/preferences/{group}', [SchoolSettingsController::class, 'updateGroup'])->name('settings.group.update');
    });

    /*
    | Academic years and terms. Gated on academics.manage rather than
    | settings.manage: the registrar who runs the calendar is usually not the
    | person who owns the school's branding.
    */
    Route::middleware('permission:academics.manage')->group(function () {
        Route::get('/settings/academic-years', [AcademicCalendarController::class, 'index'])->name('settings.years.index');
        Route::post('/settings/academic-years', [AcademicCalendarController::class, 'storeYear'])->name('settings.years.store');
        Route::put('/settings/academic-years/{academicYear}', [AcademicCalendarController::class, 'updateYear'])->name('settings.years.update');
        Route::post('/settings/academic-years/{academicYear}/current', [AcademicCalendarController::class, 'makeYearCurrent'])->name('settings.years.current');
        Route::delete('/settings/academic-years/{academicYear}', [AcademicCalendarController::class, 'destroyYear'])->name('settings.years.destroy');

        Route::post('/settings/academic-years/{academicYear}/terms', [AcademicCalendarController::class, 'storeTerm'])->name('settings.terms.store');
        Route::put('/settings/terms/{term}', [AcademicCalendarController::class, 'updateTerm'])->name('settings.terms.update');
        Route::post('/settings/terms/{term}/current', [AcademicCalendarController::class, 'makeTermCurrent'])->name('settings.terms.current');
        Route::delete('/settings/terms/{term}', [AcademicCalendarController::class, 'destroyTerm'])->name('settings.terms.destroy');
    });

    /*
    | Platform administration. The School policy restricts these to super
    | administrators; no permission slug can grant them.
    */
    Route::prefix('platform')->name('platform.')->group(function () {
        Route::get('/schools', [PlatformSchoolController::class, 'index'])->name('schools');
        Route::post('/schools/{school}/select', [PlatformSchoolController::class, 'select'])->name('schools.select');
        Route::post('/schools/clear', [PlatformSchoolController::class, 'clearSelection'])->name('schools.clear');
    });
});
