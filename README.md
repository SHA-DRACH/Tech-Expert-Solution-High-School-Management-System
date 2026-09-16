# Grace School Management System (GSMS)

A multi-school management platform for Liberian high schools, built on Laravel 12,
MySQL, Livewire, Alpine.js and Tailwind CSS.

**Built by Shadrach Jimice Jr, Software Engineer.**

The first institution on the platform is **Grace Foundation Institution, Liberia**.
It is the pilot tenant, not a special case: it is provisioned through exactly the
same code path any future school will use.

---

## Getting started

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Seed the demonstration data (a full term of students, teachers, attendance,
grades and fees) with:

```bash
php artisan db:seed --class=DemoAcademicSeeder
```

### Demonstration accounts

All use the password `ChangeMe123!`. Change them before any real deployment.

| Account | Email | Lands on |
| --- | --- | --- |
| Platform administrator | `platform@gsms.test` | Platform overview |
| School administrator | `admin@gracefoundation.edu.lr` | Admin dashboard |
| Teacher | `teacher@gracefoundation.edu.lr` | Teacher portal |
| Parent | `parent@gracefoundation.edu.lr` | Parent portal |
| Student | `student@gracefoundation.edu.lr` | Student portal |

---

## How tenant isolation works

This is the most important thing to understand before changing anything.

School data is **not** separated by hand-written `where('school_id', …)` clauses
in controllers. It is enforced in three layers:

1. **`ResolveSchoolContext` middleware** runs on every web request and pins the
   tenant — from the signed-in user, or from the host for public pages. It runs
   *before* route-model binding, so binding a `{student}` from another school
   resolves to nothing and returns 404 rather than reaching a controller.
2. **`SchoolScope`** is a global Eloquent scope applied by the
   `BelongsToSchool` trait. Every tenant-owned model is filtered automatically,
   and new records are stamped with the active school on create.
3. **Policies** re-check ownership per record, so a model reached through an
   explicitly unscoped query is still refused.

`SchoolContext` has four modes. A request that cannot be attributed to a school
lands in `DENIED`, where tenant queries return nothing — it fails closed rather
than falling back to unfiltered access.

`User` and `Role` are deliberately **outside** the global scope: authentication
has to find an account by email before any tenant is known. They use explicit
`scopeInCurrentSchool()` instead, and the isolation tests cover both.

## Authorization

`App\Support\Permissions` is the single source of truth for the permission
catalogue. The seeder writes it into the database, `AuthServiceProvider`
registers a Gate per entry, and the role editor renders it grouped. Adding a
capability means adding it there — never hard-coding a slug elsewhere.

Routes are gated by `permission:` middleware and again by policies. Super
administrators bypass permission checks but never the tenant scope: inside a
selected school they see only that school.

Student portal access is separate again: `StudentAccess` resolves per-student
switches (`student_permissions`) over school-wide defaults over catalogue
defaults, so administrators control what students can open without a deployment.

## Testing

```bash
php artisan test
```

207 tests cover authentication, permission enforcement, the admissions workflow,
grade entry and approval, report card generation, timetable clash detection,
fee structures and invoicing, assignment submission, attendance alerts,
messaging, notifications, CSV exports, document management, the mobile API,
the parent and student portals, the audit trail, and — most importantly — the multi-school isolation
suite in `tests/Feature/MultiSchoolIsolationTest.php`, which proves a
fully-privileged user in one school cannot reach another school's records even
with the exact record id.

`PageSmokeTest` loads every signed-in page against a fully seeded school, which
catches missing views, bad route names and undefined variables.

## Scheduled work

One cron entry drives everything scheduled:

```bash
* * * * * cd /path/to/gsms && php artisan schedule:run >> /dev/null 2>&1
```

Currently that runs `gsms:fee-reminders` on weekday mornings, which notifies
guardians about fees due or overdue. It is safe to run by hand at any time:

```bash
php artisan gsms:fee-reminders --dry-run
```

The command skips any invoice already chased inside the cooldown window, so a
daily schedule never sends the same family the same message twice.

## Backups

```bash
php artisan gsms:backup            # take one now
php artisan gsms:backup --keep=30  # keep a month of history
```

Runs nightly from the scheduler. Dumps go to the **private** disk under
`storage/app/private/backups`, older files are pruned, and the result is
recorded so School settings can show whether backups are healthy or overdue.
Restoring is an ordinary `mysql < dump.sql`.

## Settings

`/settings` is the hub. Anything a school configures is reachable from there,
including the handful of settings that live with the screen that uses them —
the grading scale sits with examinations, portal access with students, what the
public site shows with the website editor. Those are linked rather than moved,
so "where do I change X" has one answer without breaking links people already
have.

### Academic years and terms

`/settings/academic-years`. Enrolment, attendance, marks, report cards, fee
structures and invoices are all filed against a year and a term, so this is the
one settings screen the rest of the platform genuinely depends on. Until it
existed, years and terms could only be created by a seeder, which meant a live
school could not roll into its next academic year at all.

The rules it enforces are what keep the data answerable:

- Years may not overlap, or "which year is this date in?" has no answer.
- A term must fall inside its year, and terms in a year may not overlap or share
  a position.
- Exactly one year is current, and the current term must live inside it. Moving
  the school into a new year clears any term left current in the old one, so new
  attendance and new marks are never filed against a year the school has left.
- Nothing holding records is deleted. A year or term with enrolments, invoices,
  assessments or report cards refuses deletion and says what it holds; only a
  genuinely empty one can be removed.

Gated on `academics.manage` rather than `settings.manage`: the registrar who
runs the calendar is usually not the person who owns the branding.

### The school's own preferences

`App\Services\SchoolSettings` holds the catalogue, in four groups — academics
and attendance, finance, admissions, notifications. Each key is stored as one
row in `school_settings`, tenant-scoped like everything else.

**Every setting in the catalogue is read by something.** A setting nothing
consumes reads as a promise the software does not keep, so the catalogue is
deliberately short and each entry has a consumer:

| Setting | Read by |
| --- | --- |
| `grading_pass_mark` | Report cards and grade summaries |
| `attendance_school_days` | Attendance percentages and absence reports |
| `attendance_notify_guardians` | Whether saving a register alerts families |
| `reportcard_show_position` / `_show_attendance` / `_remark` | The printed report card |
| `finance_currency` | `App\Support\Money`, so every amount on the platform |
| `finance_invoice_due_days` | `RaiseInvoices`, when no due date is given |
| `finance_reminder_days_before` / `_cooldown_days` | The `gsms:fee-reminders` schedule |
| `finance_receipt_footer` | The printed receipt |
| `admissions_open` / `_closed_message` | The public application form |
| `admission_document_types` | The upload slots on the form *and* the public admissions page |
| `sms_attendance_alerts` / `sms_fee_reminders` | Whether an alert also goes by text |

Three things about this are load-bearing.

`SchoolSettings` is **stateless**, for the reason recorded on `PublicVisibility`:
Laravel caches a controller on its `Route`, so state cached on an injected
service outlives the request that built it and an administrator can save a
change that appears to do nothing.

Saving one group touches **only that group's keys**. An unticked checkbox is not
submitted at all, so a form that wrote every key it did not mention would
quietly switch off the settings on the other tabs.

`admission_document_types` keeps the key it has always had, rather than being
renamed into the group's naming scheme. The public application form and the
public admissions page already read that row, and renaming it would silently
reset every school's list back to the defaults.

The currency is resolved through `School::currencySymbol()` and memoised **on
the model instance**, which lives exactly one request — so `Money::format()` can
be called in a loop over an invoice table without a query per row, while a
change made in settings still takes effect on the very next page load. It is not
memoised on the service or on `Money` itself, which would outlive the request.

## Editing and removing records

Every management screen has full create, edit and remove. One rule governs the
"remove" half throughout: **anything that anchors history is archived, never
deleted, and archiving refuses outright when removing the record would strand
something that depends on it.**

| Record | Removing it means | Refused when |
| --- | --- | --- |
| Student | Archived and soft-deleted; restorable | — (the record keeps its enrolments, marks, attendance and invoices) |
| Guardian | Archived and soft-deleted; restorable | They are still the only contact for a child |
| Class / section | Archived | Enrolments still refer to it |
| Subject | Archived | Assessments still refer to it |
| Department | Archived; its subjects and staff become unassigned | — |
| Academic year / term | Deleted | It holds enrolments, invoices, assessments or report cards |
| Announcement | Archived; restorable | — |
| Event | **Cancelled** or deleted — see below | — |
| Expense | Deleted, with the amount in the audit trail | — |
| Staff | Archived and soft-deleted; keeps its employment status | — |
| User account | Suspended, never deleted | — |

Two details worth knowing:

**A student's number is not editable.** It is printed on report cards, quoted on
invoices, and is what uploaded marks are matched against; changing it would
silently orphan every one of those references.

**An event is cancelled, not deleted, once families know about it.** Cancelling
leaves it on the calendar marked cancelled, which is what a parent who already
has it in their diary needs to see. Deleting is for the one entered by mistake,
and both are offered.

**Editing an announcement does not re-notify.** Families were told when it was
published, and sending the same message again because a typo was fixed trains
people to ignore the notifications that matter. Something genuinely new is a new
announcement.

**Correcting an expense records both figures.** An expense that changes after
the fact is exactly what an auditor asks about, and "it says 4,500 now" is not
an answer without the before.

## Giving a member of staff a login

Adding a teacher creates the *person*, not an account — the two are separate
records, and a staff record with no account cannot sign in.

There was no way to connect them. The account form offered a guardian link and a
student link and **no teacher link at all**, so a school could add a teacher and
then find nothing in the application that would let that teacher in. Five of the
pilot school's six teachers had no login and nothing on their records said so.

Both routes now exist:

- **From the staff record.** A teacher with no account shows a *"This member of
  staff cannot sign in"* panel with email, role and password fields, offered
  where the gap is actually noticed.
- **From Users → Create account.** Choosing the Teacher role reveals a staff
  picker listing only staff who have no account yet.

The staff link is **required** for a teacher account, not optional.
`AssessmentPolicy` resolves the teacher through the user id; an account pointing
at no staff record finds nothing and is refused, so it would look correct in the
user list and be useless in the classroom.

A teacher who can sign in still needs a **teaching assignment** before they can
record anything — the assignments screen names anyone who has none.

## Results and the gradebook

`App\Services\Gradebook` is the single place results are calculated, and one
rule runs through all of it: **only approved marks are results**. A mark a
teacher has entered but the academic office has not signed off must never reach
a parent, never count towards an average and never appear in the gradebook —
it may still change. Every query filters on `status = approved`, and that filter
lives in one service precisely because three copies of it would be three chances
to forget it.

Three things about the arithmetic are deliberate:

- Subject averages are **weighted** by each assessment's weight, so a final
  examination counts for more than a class exercise.
- They are computed from **percentages**, so a paper out of 40 and one out of
  100 can be averaged together at all.
- The overall average is the **mean of the subject averages**, not of every
  individual mark. Otherwise a subject that happened to be assessed six times
  would count six times as heavily towards the term result as one assessed
  twice, which is not what a report card means by "average".

Class position is computed, never stored, so it always agrees with the marks
currently approved. Students on the same average share a position and the next
position skips accordingly — ranking two identical results differently would be
an invention rather than a measurement.

### The pass mark comes from the grade scale

`Gradebook::passMark()` reads the published grade scale, treating the bottom
band as the fail band, and falls back to the `grading_pass_mark` setting only
for a school that has not set a scale up.

They were two sources of truth for the same thing, and it produced a real
contradiction: with a pass mark of 50 and a scale whose bottom band was F for
0–59, a student on 58% was shown as "above the pass mark" and graded **F** on
the same row. A parent reading that cannot tell whether their child passed,
which is the one thing the row exists to say. Where the two still disagree the
gradebook says so plainly rather than reconciling it silently — only an
administrator can decide which figure is right.

A school wanting two failing grades (E and F) is not expressible this way; that
needs a flag on the band itself.

### Who sees what

| Screen | Who | Shows |
| --- | --- | --- |
| `/gradebook` | `reportcards.view` | A class and term, every student, per-subject averages, overall average, grade, position |
| `/gradebook/students/{student}` | `reportcards.view` | One student's term in full — every mark behind every subject average, and each term of the year |
| Parent portal → Grades | The guardian, if cleared for academics | The same per-term table for their own children only |

An administrator may correct an approved mark; a teacher may not. That asymmetry
is the point: after approval the teacher's route to changing a mark is to have
the assessment rejected and re-entered, while the academic office can fix a
transcription error directly. A correction **requires a reason**, which is stored
with the old and new value in the audit trail. Without the why, an audit entry
only proves that somebody did it.

## Teaching assignments

`/teaching-assignments`. `AssessmentPolicy` decides whether a teacher may enter
marks by reading this table, so until an assignment exists a teacher cannot
record a single grade. It had no screen at all — assignments arrived only from a
seeder — which meant a real school could not give a new teacher a class.

A teacher may hold any number of assignments: several subjects in one section,
one subject across several sections, or both. Ticking several classes and
several subjects creates every combination in one go, and re-submitting is safe
because a combination that already exists is left alone rather than duplicated.

Removing an assignment removes the teacher's authority to enter marks for it and
**never the marks themselves** — those belong to the student, not to whoever was
teaching at the time.

The screen names any teacher with nothing to teach, because an unassigned
teacher signs in to an empty workspace and the reason is never visible from
their side of the screen.

## Uploading marks

A teacher uploads a class's marks at `/assessments/{assessment}/marks/import`,
after downloading a mark sheet that already has the class in it.

**Marks are matched by student number, never by name.** Two children called Mary
Doe in one section is not an edge case in a Liberian school, and awarding one of
them the other's mark is the worst thing this feature could do.

**A blank score is "not marked yet", not zero.** A child who has not sat the
paper must not be failed by a spreadsheet.

**Uploading locks the marks.** Confirming writes the scores and submits the
assessment for approval in the same transaction, so the teacher can no longer
change them — the policy requires an editable assessment and a submitted one is
not. If a mark is wrong, the academic office rejects the assessment, which
returns it to the teacher to correct: a reviewable route, rather than a quiet
edit after the fact. This is said on the upload screen *before* the upload, not
buried in a confirmation dialog afterwards.

Like every other import, nothing is written until the person has seen a preview
of exactly what will happen, and a bad row fails alone.

## My account

`/profile`, for every signed-in person — administrator, teacher, parent or
student. Deliberately behind no permission slug: a parent holds no permissions
at all and must still be able to correct their own name and change their own
password. Nobody can edit anyone else's account from here; managing *other*
people's accounts is `users.update`, elsewhere.

Changing a password requires the current one even though the person is signed
in. That is what stops an unattended, unlocked screen from becoming a permanent
account takeover, and it is why it is a separate form rather than a field on the
profile.

The page also shows the union of every permission the account's roles add up to
— the list the system itself enforces. "What am I allowed to do here?" is a fair
question, and answering it plainly saves a support call.

## The assistant

A panel on every signed-in screen. Three rules shape it, and none is negotiable.

**It answers from the school's own data, through the same authorization as every
screen.** Answers run as the person asking: a parent asking about fees gets their
own children's fees, and a question they hold no permission for is refused with a
plain explanation. It is a faster route to what someone may already see, never a
second route around the permission system.

**Nothing leaves the building unless the school says so.** The default provider
(`App\Services\Assistant\LocalAssistant`) runs entirely on this server — it
recognises what is being asked and answers from the database. A hosted model is
a separate provider a school opts into via `config/assistant.php`, the same seam
shape as the SMS gateway, because sending a child's marks or a family's debts to
a third party is the school's decision rather than a default to inherit. The
panel states which provider is answering.

**It says when it does not know.** An assistant that invents a plausible
attendance figure is worse than none, because a figure with no source is
indistinguishable from one with a source until somebody acts on it. Unrecognised
questions get "I don't know" and a list of what can be asked. A failed network
request is reported as a failure to reach the server, never rendered as an
answer.

It answers on students, staff, attendance, fees, results, admissions, the
approval queue and the school calendar, and links to the screen each figure came
from.

Intent matching is ordered, and the ordering matters: "how many students owe
fees" mentions both students and fees, and fees is what is being asked. The
calendar intent is matched on specific phrases rather than a bare "term",
because almost every question about a child mentions the term it happened in —
a loose match there answered "how is my child doing this term?" with the term
dates.

Set `ASSISTANT_ENABLED=false` to remove it entirely; no route is left reachable.

## Users, roles and CSV

### Who can do what

Every management route is behind a permission slug, and `School Administrator`
is the only default role holding `users.*`, `teachers.update`, `teachers.archive`,
`roles.manage` or `settings.manage`. Anyone else gets them only if an
administrator grants them through **Roles & permissions**. (`academics.manage`
is the one deliberate exception: `Principal` holds it too, because the principal
runs the school year.)

### Accounts

`/users` lists every account with the roles it holds, filterable by role and by
status, with a count per role above the table. Opening an account shows its
roles **and the union of every permission they add up to** — the list that is
actually enforced — so "what can this person reach?" is answerable without
reading three role definitions.

Accounts are **suspended, never deleted**. A login is attached to marks entered,
payments received and audit entries; removing it would leave that history
pointing at nobody. The same reasoning applies to staff records, which are
soft-deleted and restorable, and which keep their employment status through
archiving so the record still says *why* someone left.

Role ids arriving from the edit form are re-resolved against the current school
before anything is synced, so an id belonging to another tenant does not resolve
and the request is rejected rather than silently granting a foreign role.

### Searching

`App\Support\Search` backs every listing. A plain `LIKE %term%` fails people in
three ways, and this fixes all three:

- It matches **the start of any word**, so "ko" finds Grace **Ko**llie without
  dragging in Jac**ko**n. Two letters is enough to narrow a list, which is the
  point of typing into a search box.
- It **splits what you typed**. "gr ko" and "kollie grace" both find Grace
  Kollie; every word has to match somewhere, but not all in the same column.
  Searching a full name used to find nothing, because no single column held
  both halves of it.
- It matches **identifiers by fragment**, because nobody types a student number
  from the beginning — they read the last few digits off a form.

Searching users reaches the account name, the email, the account id and the
linked staff or student number, since "the id" means whichever of those the
person is holding.

`%`, `_` and `\` are stripped from search input rather than escaped: they are
LIKE wildcards and SQLite applies no default escape character, so a portable
escape would mean raw SQL on every clause. A search that leaves no usable token
matches **nothing** rather than everything — answering a search for "%" with the
entire school is precisely what stripping the wildcard was meant to prevent.

The box filters as you type (`x-ui.live-search`), debounced, and restores focus
and caret position after the reload — without that restore the second letter
lands nowhere and the feature is worse than the button was. The `$nextTick` in
its `x-init` is required, not decorative: `x-init` runs before Alpine registers
`x-ref`, so reading the ref directly finds nothing. Without JavaScript it is
still an ordinary GET form with a button.

### Import and export

CSV export covers students, guardians, teachers, users, attendance, payments and
outstanding fees. Each carries the permission of the screen it comes from,
honours whatever filters that screen is showing, and is written to the audit
trail, because exporting is how data leaves the building.

Import is at `/imports/{type}` (teachers today). Two rules shape it:

- **Nothing is written until you have seen what will happen.** An upload
  produces a preview — which rows will be created, which will be skipped and
  exactly why — and only a second, explicit confirmation commits it. The rows
  are re-checked at the moment of writing rather than trusted from the session.
- **A bad row fails alone.** One mistyped date must not throw away the other
  four hundred rows of a spreadsheet a registrar spent the morning preparing, so
  each row is validated on its own and failures are reported by line number.

The importer reads the same column names the exporter writes, so a school can
export what it has, edit it in a spreadsheet and import it back. Headings are
matched loosely — `First Name`, `first_name` and `FIRST NAME` are the same
column — and only `first_name` and `last_name` are ever required. A department
that does not already exist is left unset rather than created, so a typo cannot
invent one. Imported staff are never published to the public website: a bulk
upload must not put someone's name and photograph on the open internet as a
side effect.

## The public website

Every school gets a complete public site at its own host, covering spec sections
7 to 11: home, about, academics, admissions, teachers, news, events, gallery and
contact. `WebsiteContentSeeder` writes real starter copy for each page when a
school is provisioned, so a newly onboarded school is never left with blank
public pages; everything it writes is editable afterwards in the website editor.

### What is published is the school's decision

`App\Services\PublicVisibility` holds eleven switches — teachers, news, events,
gallery, announcements, statistics, departments, subjects, calendar, classes and
fees — set under **Website → What is published**.

**`fees` defaults to off.** Publishing a fee schedule is a commercial decision,
so the platform will not make it for a school by omission. The other switches
default on.

A switch that is off does three things, not one: the section disappears from the
page, the page itself returns 404, and the navigation link is removed so no dead
link is left behind. `PublicWebsiteTest` covers each of those.

`PublicVisibility` is deliberately **stateless** — it re-reads the setting on
every call. An earlier version memoised the resolved set on the instance, and
because Laravel caches a controller on its `Route`, that cache outlived the
request: an administrator could switch a section off and the site would keep
serving the hidden content until the process restarted. Do not add a cache here
without giving it a way to be invalidated.

### Motion

The public site uses AOS for scroll reveals, Swiper for sliders and an
IntersectionObserver for the animated counters. Three things about that setup
are load-bearing:

- **Do not import the standalone `@alpinejs/*` plugin packages.** Livewire's ESM
  bundle already ships Alpine with collapse, focus, intersect and persist.
  Importing them again pulls a second copy of Alpine into the build and throws
  `Cannot redefine property: $persist` during `Livewire.start()` — before the
  `DOMContentLoaded` listener is attached, so AOS never initialises and every
  reveal on the page stays permanently invisible.
- **The `<body>` must not have a fixed height.** Combined with `overflow-x: clip`
  from the stylesheet, a constrained height turns the body into the vertical
  scroll container, the window stops scrolling, and again nothing reveals. Use
  `min-h-screen`.
- **AOS caches every element's offset at init** and only recalculates on resize.
  `app.js` re-measures on `load`, on `document.fonts.ready`, on a debounced
  `ResizeObserver` over the body and on `livewire:navigated`, because anything
  that changes the page height afterwards leaves sections below the fold anchored
  to the wrong place and they never trigger.

Counters have a `settle()` backstop on a timer and on `visibilitychange`, since
`requestAnimationFrame` is throttled in background tabs and would otherwise leave
a KPI frozen at a wrong number rather than an unfinished animation.

## SMS

Attendance alerts can go out by text as well as in-app, but **no provider is
connected**: choosing and contracting an SMS gateway is the school's decision.

What ships is the seam a provider plugs into — `App\Services\Sms\SmsGateway`,
with a log-only default so nothing silently pretends to deliver. To connect a
real provider, implement that one method, register the class in
`config/sms.php`, and set `SMS_ENABLED=true`. Nothing that sends a message has
to change.

## The mobile API

Token-authenticated endpoints under `/api` for the planned Flutter application,
using Sanctum. They reuse the same services and authorization rules as the web
portals rather than reimplementing them: the per-child academic and finance
flags, the tenant scope and the "resolve the child through the guardian's own
relationships" rule all apply identically.

The middleware order there matters and is deliberate: authenticate, *then*
resolve the tenant from the authenticated user, *then* bind route models — so a
`{child}` is bound within the right school.

## Project layout

| Path | Purpose |
| --- | --- |
| `app/Support/SchoolContext.php` | The active tenant, and the only thing scopes read |
| `app/Support/Permissions.php` | The permission catalogue |
| `app/Models/Concerns/BelongsToSchool.php` | Makes a model tenant-owned |
| `app/Models/Concerns/RecordsAuditTrail.php` | Automatic audit logging |
| `app/Services/` | Business logic kept out of controllers |
| `app/Actions/` | Multi-step operations (provisioning, account creation) |
| `resources/views/components/ui/` | The shared interface component library |
| `resources/views/portals/` | Parent, student and teacher portals |
| `resources/views/public/` | The public website (spec sections 7 to 11) |
| `app/Services/PublicVisibility.php` | Which public sections a school publishes |
| `app/Services/SchoolSettings.php` | The catalogue of settings a school decides for itself |
| `app/Support/Search.php` | The word-prefix search behind every listing |
| `app/Services/CsvImporter.php` / `CsvExporter.php` | CSV in and out |
| `app/Services/Gradebook.php` | Results and averages — approved marks only |
| `app/Services/Assistant/` | The assistant, and the seam a hosted model plugs into |

Controllers stay thin: validation lives in Form Requests, authorization in
policies, and business logic in `app/Services` and `app/Actions`, so the same
logic can be exposed through an API for the planned Flutter application.

## Conventions worth knowing

- Money is stored in **minor units** (cents) as integers. Use `App\Support\Money`
  to format it; never do arithmetic on formatted strings.
- The grade-level table is `school_classes` behind a `SchoolClass` model,
  because `Class` is a reserved word in PHP.
- Student and application numbers come from `ReferenceNumberGenerator`, which
  uses a locked counter row per school and year — counting existing rows would
  reissue a number after a deletion.
- Audit log entries are append-only; the model refuses updates and deletes.
- Uploaded documents go to the private disk and are only ever streamed through
  an authorized route, never linked to directly.
- The `public` filesystem disk uses a **root-relative** URL, because each school
  is served from its own host.
- Never key `updateOrCreate` on a date column. The `date` cast stores
  `2026-09-02 00:00:00`; MySQL coerces that back to a DATE when comparing but
  SQLite does not, so the lookup misses and a duplicate insert is attempted.
  Match with `whereDate()` instead.
- Two routes on the same method and URI silently replace one another, taking
  the earlier route's *name* with it. `RouteIntegrityTest` fails the build if
  that ever happens again.
- A shared Blade component that a page repeats — one form per academic year,
  say — must be given a unique `id`. `x-ui.field` and `x-ui.input` default it to
  the field name, which is right for one form per page and wrong for many: every
  label would point at the first form's field. Those repeated forms also pass
  `:remember="false"`, since old input is keyed by field name alone and one
  year's rejected dates would otherwise reappear inside every other year's form.
- Reads inside a transaction see that transaction's own uncommitted writes. A
  loop that allocates sequential numbers must therefore count up from a single
  read taken *before* the loop, not re-query a MAX() per row and add an offset.
- A stale "keep me signed in" cookie used to 500 every request, `/logout`
  included, so the person could not sign out to clear the cookie that was
  breaking them. `SessionGuard::userFromRecaller()` ends in
  `hash_equals($userPassword, $recallerHash)` and `$userPassword` is null
  whenever `retrieveByToken()` matched nobody — which is the normal state after
  a re-seed or a restored backup, because `remember_token` starts null.
  `DiscardUnusableRememberCookie` drops such a cookie before the guard reads it.
  Its position in the web group is load-bearing and is asserted by a test:
  after `EncryptCookies` (or it reads an encrypted value and discards *every*
  remember cookie) and before `ResolveSchoolContext` (the first middleware to
  ask who the user is).
- `withUnencryptedCookie()` in a test does not survive `EncryptCookies` — the
  middleware fails to decrypt it and sets the cookie to null, so the test
  silently exercises "no cookie at all". Use `withCookie()`, which encrypts with
  the same `CookieValuePrefix` the framework uses.
- A Symfony `Cookie` keeps its name private, so `firstWhere('name', …)` over
  `Cookie::getQueuedCookies()` always finds nothing and the assertion passes on
  a null. Match with `->first(fn ($c) => $c->getName() === …)`.
- The password reveal toggle lives in `x-ui.input`, not at each call site, so a
  new password field cannot be added without one. The plain `type` attribute
  stays `password` and Alpine drives the real type, so a browser with no
  JavaScript is left with a normal password box; the button carries `x-cloak`
  so it never appears as a control that cannot do anything. Each box gets its
  own `x-data` scope — a shared one would reveal the current password alongside
  the new one on the profile screen. `PasswordFieldTest` fails the build if any
  view ever hand-rolls a raw `<input type="password">`.
- Creating a person and creating their login are two halves of one job, and the
  optional "Portal access" block on the teacher, student and guardian forms is
  the same component (`x-forms.portal-access` + `App\Actions\GrantPortalAccess`)
  in all three, so they cannot drift into three different shapes. Filling it in
  requires `users.create` as well as the create-person permission — adding a
  person and creating an account are different authorities, and one must not
  come free with the other. The account is made inside the same transaction, so
  a failed login never leaves a half-made person behind.
- A teacher gets the sidebar, not a top bar. They used to have a five-link
  portal nav, which left someone holding `grades.enter` and `attendance.record`
  with no way to reach Assessments, Attendance, Report cards or the mark upload
  at all. Sidebar entries may carry a `when` flag for things that depend on who
  someone *is* rather than what they may do; the teacher's Overview points at
  their own dashboard, and the sidebar logo points at `portal`, which forwards
  each account to the home it actually has.
- Gallery images require both a title and a description. The title becomes the
  image's `alt` text on the public site, so an untitled photograph is one a
  screen reader announces as nothing and nobody can search for later.
- The **mark sheet** (`/mark-sheet`) is arranged the way a teacher thinks: pick
  a class and a subject and see every student against every assessment for that
  pairing. Who "does" a subject comes from the class's own subject list
  (`class_subject`) — there is no per-student subject choice, so everyone in the
  class takes everything attached to it. A teacher sees only the classes and
  subjects they are assigned to; `grades.approve` sees all of them.
  `MarkSheetController::editableAssessments()` is the single place that decides
  what is writable, so the screen never offers an input the save would refuse:
  a teacher may write while an assessment is draft or rejected, and the academic
  office may correct even an approved mark, which is audited with the before,
  the after and a reason. The individual-student panel scrolls to the matching
  box rather than posting separately — one save path, not two.
- An assessment has `starts_at` and `ends_at`, not a due date. `due_on` was
  migrated into `ends_at` (end of that day) and dropped: two columns both
  meaning "when is this due" is two answers to one question, and they drift the
  first time a screen updates one and not the other. Lateness is now measured
  against the real closing moment, so an exam that closes at 10:30 means 10:30
  rather than midnight.

## The teacher portal (spec section 27)

| Module | Where |
| --- | --- |
| Dashboard | `/teaching` — classes, subjects, students, attendance rate, pending grades, today's lessons, announcements |
| Profile | `/profile` |
| Classes | `/teaching/classes` |
| Subjects | `/teaching/subjects` |
| Students | `/teaching/students` |
| Attendance | `/attendance` (`attendance.view`) |
| Assignments | `/teaching/assignments` |
| Examinations | `/examinations` (`exams.view`) |
| Grade entry | `/mark-sheet` and `/assessments` (`grades.enter`) |
| Timetable | `/teaching/timetable` |
| Announcements | `/teaching/announcements` — reading only |
| Notifications | `/notifications` |

**"Teachers must only access authorized classes and students."** Every screen
resolves the teacher from the signed-in user and reads through
`teaching_assignments`; a section or subject id in the URL is checked against
that list rather than trusted, and asking for an unassigned class is a 403.
`TeacherPortalModulesTest` covers the boundary as well as the modules — that
subjects, students, assignments and announcements each show only what belongs
to that teacher.

Two things on the dashboard are deliberate. The attendance tile is a **rate**,
not a row count: the controller used to compute `attendanceToday` as a count of
records and then never display it, which was just as well, because "412 records"
tells a teacher nothing. And it reads **"No register taken yet"** rather than 0%
when nothing has been recorded — a class whose register has never been taken is
not a class nobody attends, and 0% would read as a crisis rather than as missing
data. A banner names how many classes still need their register today, which is
the thing a teacher is expected to do every morning and the easiest to forget.

## Grading, report cards, messaging and the trail

**Grade entry (36).** All four assessment kinds — assignment, test, exam, other
— with the type validated against `Assessment::TYPES`. The report card carries
both a **total** and an **average**: a Liberian card is read that way round, the
total being what a parent adds up against and the average what the grade comes
from. Grades and remarks both come from the school's own grade scale, never
invented in the view.

**Approval (37).** Teacher enters → submits → academic office reviews →
approves → the result becomes official → the report card is generated from it.
Two rules hold the workflow up, and both are tested: a teacher **cannot approve
their own marks** (entry and sign-off are separate duties, which is the whole
point), and marks are not a result until approved — `Gradebook` counts approved
marks only, so a submitted-but-unapproved mark contributes nothing to an average
and reaches no parent. Rejecting returns the assessment to the teacher with a
note and makes it editable again.

**Report card (38).** Every item on the spec's list is on the card, including
the **student photograph** — students had no `photo_path` column at all, and it
is the thing that makes a printed card belong to a child rather than to a row in
a table. Parents can view, print, and **download**.

The download is a self-contained HTML file, not a PDF. No PDF renderer is
installed and one cannot be added in this environment, so rather than pretend
otherwise this produces something that genuinely works: the stylesheet is inline
and photographs are base64 data URIs, so it opens with no internet connection,
on a phone as well as a laptop, and prints to paper or to PDF from any browser.
A downloaded file that loses its layout the moment it leaves the server is not a
document a family can keep. `ReportCardController::download()` is the seam to
change if a school later installs a renderer; the route, the authorization and
the filename stay as they are.

**Messaging (45).** Parent ↔ school, parent ↔ teacher, teacher ↔ administration,
and student ↔ teacher **where permitted**. That last clause was not enforced:
`MessageController` read "parents and students always have a voice", so the
per-student `send_messages` switch — which defaults to **off** — did nothing at
all. A school could turn student messaging off, see it shown as off, and every
student could still write to every teacher. It is now checked through
`StudentAccess`.

**Audit trail (58).** `audit_logs` stores user, school, action, module, the
record (`auditable_type` / `auditable_id`), timestamp, IP address, user agent,
and the values on both sides of a change. Passwords and tokens are stripped
before writing. The model is append-only and **throws** on update or delete
rather than quietly returning false — a silent failure would let calling code
believe an edit had worked.

## Subject management (spec section 29)

`/subjects`. Section 29 asks for five things on a subject — name, code,
department, **grade** and **assigned teachers** — and only the first three had
anywhere to be entered.

The missing pair mattered more than it looked. Which grades take a subject lives
in `class_subject`, and **nothing in the application wrote to that table**: it
was populated by the seeder and by nothing else. A school that added "Further
Mathematics" got a row in `subjects` belonging to no class, so it never appeared
on a mark sheet, never reached a report card, and looked for all the world like
the software had lost it. The page says so plainly when a subject has no grade
attached, rather than leaving it to be discovered weeks later.

Grades are `sync`ed, so unticking one means that grade no longer takes the
subject; marks already recorded are untouched, because they hang off the
assessment rather than off this pivot. Teachers can be assigned from the subject
as well as from Teaching assignments — "who teaches Chemistry?" and "what does
Grace teach?" are the same question asked from two ends, and a school asks both.
A subject with assessments against it refuses archiving.

## The sidebar

Nav groups collapse, with a chevron and a count of what is hidden. The group
holding the current page starts open — collapsing what someone just clicked
would lose their place — and everything else starts shut, which is the point:
the menu had grown long enough to scroll, and scrolling to reach a link you use
daily is worse than one extra click.

Which group is open is worked out **server-side**, not in Alpine, so it is right
in the first painted frame with no flicker of everything-open, and so the
correct group is open for someone with JavaScript off. A page outside the menu
falls back to opening the first group rather than presenting a wall of closed
headings with no way in.

## One shell for everybody

There used to be a second layout — a top bar with no side navigation — used by
parents and students. It meant a family saw a different application from the one
their school used, with no way to reach their own profile or notifications. It
has been deleted, and `StudentPortalModulesTest` fails the build if any view
reaches for it again.

The sidebar filters entries on permissions, and **parents and students hold no
permission slugs at all** — so an entry may set `can => null`, meaning no
permission is needed, and be revealed by `when` instead. Without that their
entire menu filtered itself away to nothing.

## The student portal (spec section 23)

| Module | Route | Governed by |
| --- | --- | --- |
| Dashboard | `/student` | — |
| Class | `/student/class` | `view_subjects` |
| Subjects | `/student/subjects` | `view_subjects` |
| Grades | `/student/grades` | `view_grades` |
| Exams | `/student/exams` | `view_grades` |
| Assignments | `/student/assignments` | `view_assignments` |
| Report cards | `/student/report-cards` | `view_report_cards` |
| Attendance | `/student/attendance` | `view_attendance` |
| Timetable | `/student/timetable` | `view_timetable` |
| Announcements | `/student/announcements` | — |
| Messages | `/messages` | `send_messages` |
| Notifications, Profile | `/notifications`, `/profile` | — |

What a student may open is the administrator's decision, held in
`StudentAccess`. The menu reads the same service the controllers enforce, so a
switched-off module is not offered as a link that would then answer 403.

**"Students must only see their own records."** Every page resolves the student
from the signed-in user and reads through their own enrolment. The class page is
deliberately a **headcount, not a roster** — a student has no business reading a
list of their classmates — and an announcement aimed at one class reaches that
class only. All of it is covered by tests that assert on the negative: another
student's report card link, another class's exam paper, a classmate's mark.

## Student permissions (spec section 24)

Twelve abilities, set school-wide at `/student-permissions` and per-student at
`/students/{student}/permissions` — configurable entirely from the interface,
with no code change.

Three of them were **decorative**: `view_profile`, `edit_profile` and
`view_fees` appeared as toggles and were read by no code anywhere, so an
administrator could switch them and watch nothing happen. `view_fees` had no
student fees page behind it at all. All three are now enforced, and
`StudentPermissionEnforcementTest` scans the catalogue against the codebase and
fails the build if a new ability is ever added and left unread — a switch that
changes nothing is worse than no switch, because it tells an administrator they
have restricted something when they have not.

Two boundaries are deliberate:

- **The password form is never gated.** Being unable to change your own password
  is an account-security problem, not a profile preference, and no school
  setting should be able to create one. `edit_profile` governs name and email.
- **`view_profile` governs the profile module, not the account menu.** The
  header still shows which account you are signed in as, because a person needs
  to know that.

With `edit_profile` off — the spec's own default — a student sees their details
read-only rather than not at all, so they can still check the school has their
name right and ask the office to fix it.

## Class management (spec section 28)

`/academic-structure`. Grades 7–12 and their sections (10A, 10B, 10C) are
created, **edited and archived** from one screen. Editing and archiving had
controller methods and routes but nothing in the interface reached them, so an
administrator who mistyped a grade name had no way to correct it.

Archiving refuses while a class still has sections or enrolments, and while a
section still has students — last year's report cards name the class a child was
in. A section cannot be moved to another school's class: the id is re-resolved
through the tenant-scoped query, so it does not resolve and the move is refused.

## Enrolment: students in classes, teachers on classes

**Nothing in the application created an enrolment.** Not the student form, not
admissions approval — the rows existed because a seeder wrote them. A school
could add a student through the interface and had no way to put them in a class,
so the child showed "Not assigned" for ever: no register, no mark sheet, no
report card, no timetable. That is now built.

A class can be chosen on the create-student form, so placing a student is not a
second step that is easy to skip, and it can be set or changed afterwards on the
student's own record, which also shows their placement history. One placement
per student per year, updated rather than added to — two would make "which class
is this child in?" unanswerable, and attendance, marks and report cards all ask
it. A placement with marks recorded against it refuses removal; changing the
class is the way to move a child.

### Subjects are recorded, not inferred

`student_subject` records what a student actually takes, per academic year.
Before, it was inferred from `class_subject`: a student took whatever their grade
offered, with no way to say otherwise. That is right for core subjects and wrong
for electives — a Grade 12 class may offer Further Mathematics to six of its
thirty students, and all thirty appeared on its mark sheet.

Enrolling attaches every subject the class offers, which is exactly what the
software used to assume, so nothing changed for a school on the day this
shipped; the existing rows were backfilled the same way. Deselecting is what
makes an elective possible. A subject the class does not run cannot be attached
to a student at all.

**The mark sheet fails safe.** A student with *no* subject rows still appears —
filtering on the pivot alone would make anyone whose subjects were never
recorded (an older record, a CSV import, a row written straight to the database)
vanish from every mark sheet in the school, silently and with nothing to explain
it. Absence of a record is not evidence that a child takes nothing.

The teacher side is the mirror image and already existed: `teaching_assignments`
holds (teacher, class, subject), set from either Teaching assignments or the
subject itself, and it is the table that authorises a teacher to enter marks.

## The question a teacher sets

An assessment recorded a title, a mark and a deadline, and nowhere to write what
the students were actually being asked to do. A teacher setting an essay had to
hand the question out on paper or read it aloud, and a parent asking "what has
she been set?" had no answer beyond its title.

Creating or editing an assessment now takes:

- **Question or instructions** — free text, shown to the student and to their
  parents.
- **Question paper** — an optional PDF or Word document, up to 8 MB.

Both are optional, and the question panel is not rendered at all when neither
was given: an empty panel would imply a question had been set and lost.

### The paper is private

It is stored on the **private** disk and only ever streamed through
`assessments.question`, which checks authorization on every fetch — the same
treatment a student's own submitted work already gets. A question paper sitting
on a guessable public URL before the exam has been sat is a different kind of
problem from an ordinary file leak.

Who may fetch it:

| Who | Why |
| --- | --- |
| Staff who may view the assessment | They set it or approve it |
| Students in the section it was set for | It is their work |
| Guardians of those students, cleared for academics | The other half of "for both parent and student to see it" |

Everyone else gets a 403, including a student in a **different section of the
same class** — a paper set for 9A is not for 9B to read before they sit it — and
a guardian who is not cleared for academic records. Both are tested.

Replacing a paper deletes the old file rather than orphaning it, and an edit
that does not touch the file leaves the attached one alone.

### Parents can see assignments at all now

Parents had no assignments view. `/parent/assignments` lists the work set for
their child's class with the question attached, and whether **their own child**
handed it in — not the rest of the class.

## Paper the school hands out

Four documents leave the building on paper: a **receipt**, an **admission
letter**, a **report card** and a **student record**. Each now prints on its own
and carries a QR code.

### Printing prints the document, not the screen

The print stylesheet hides everything and then un-hides one element:

```css
@media print {
    body * { visibility: hidden; }
    .printable, .printable * { visibility: visible; }
    .printable { position: absolute; inset: 0 auto auto 0; width: 100%; }
}
```

`visibility` rather than `display` because the printable element is nested deep
inside the application shell, and `display: none` on its ancestors would take it
down with them. Marking the document `.printable` is the whole of the work; the
sidebar, the header and the buttons stop being printed.

### The code verifies, it does not encode

The QR code holds a **URL**, not the document's contents. A receipt is only a
claim that money was paid; encoding the amount into the code would be the same
unverifiable claim in a second alphabet. Scanning lands on `/verify/{type}/{ref}`
— public on purpose, because the person checking is usually a parent at a
counter or a bursar at another school, and neither has an account here.

What the check shows is the design problem. The rule: **enough to confirm the
paper in your hand, nothing you could not already read off it.**

| Document | Confirms | Never shows |
| --- | --- | --- |
| Receipt | Number, amount, date, method | Balance, address |
| Admission letter | Application number, status | Guardian details |
| Report card | That it was issued, and when | Any mark, average or position |
| Student record | That the number is real, and enrolment status | Class, results, contacts |

Names are masked to "Mary D." everywhere. Someone who guesses a reference learns
nothing; someone holding the paper can confirm every line on it.

A **draft** report card carries no code. It can still change and the family has
not been given it, so printing a code would promise a check the school cannot
honour. An admission letter does not exist at all until the application is
approved or enrolled — handing a family a document saying they have a place they
have not been given is not a formatting problem.

The SVG is inline and has its XML declaration stripped: the report-card download
opens with no internet connection, and a `<?xml?>` part-way down an HTML document
is invalid.

## Scholarships

A scholarship is a **standing decision about a child's fees**, not an adjustment
typed into one invoice — so it has its own record. That distinction is what lets
it survive the invoice it discounted, be answerable a year later ("who approved
this?"), and apply again automatically the next time fees are raised.

Stored as **either a percentage or a fixed amount**, because schools use both
("half fees", "L$5,000 off boarding") and converting one to the other at the
point of award breaks silently the moment fees change.

`RaiseInvoices` applies them. Awards stack — a sponsor's bursary alongside a
staff-child discount is ordinary — but never past the value of the bill, or the
balance goes negative and reads as the school owing the family money. The
invoice **names the award** that discounted it, because "why is this bill
smaller?" is the first question anyone asks of a discounted one.

An award is **ended, never deleted** (§47, §71.10). The invoices it already
discounted are still on the books, and an audit that cannot explain why a bill
was smaller is not an audit. *Suspended* is deliberately separate: a school
pausing an award pending a sponsor's payment keeps the record and gets it back
without retyping it.

## Recording a payment against proof

Cash is handed over the counter and the receipt is the proof. A bank transfer,
mobile-money payment or cheque happened somewhere the school cannot see, and the
slip the family brings in is the only thing tying it to this school — so for
those three methods a **transaction reference is required**, enforced on the
server and signalled on the form before the bursar takes the money.

The student selector shows **name · class · student number — invoice, balance**.
Two children called Mary Doe is not an edge case, and taking money against the
wrong one is not a mistake the receipt reveals.

## The registrar's desk

`/registrar`. Everything here already existed across six modules; the work of
the office is one person at a counter holding one child's paperwork, so it is
gathered into one place. **No new authority is invented** — every action
re-checks the permission of the module behind it, so putting a link on this page
cannot widen what anyone can reach.

It adds the two things that were genuinely missing: a **student record that
prints**, and a way to **link a parent to a child** from the child's page rather
than by editing the parent and hunting for the child.

Linking is its own action rather than a field on the student form, because the
link carries its own terms — who is primary, and what they may see. Burying
those in a general edit is how a parent ends up able to read a child's finances
because somebody was updating an address. Naming a new primary contact demotes
the old one: "who do we ring first?" needs exactly one answer.

Class sizes are counted in the database, excluding withdrawn and archived
students — a roll that counts children who left flatters the number, and seating
gets planned from it. The hub also surfaces two pieces of *work*, not statistics:
students not yet placed in a class, and students with nobody to telephone.

The student list gained a **class filter**, and it filters on *this year's*
placement — a child in Grade 9 last year and Grade 10 now belongs under one of
them, not both. The CSV export honours it too; a file that quietly holds more
students than the list it came from is worse than no export.

## Things that were silently broken

**A "full access" role stopped being full access.** `Permissions::ALL` was
expanded into concrete rows when a school was provisioned, so every permission
added to the platform afterwards reached only schools created later. Existing
school administrators simply did not have it — no error, no 403, the module was
just absent from their sidebar. A role defined as *everything* is a statement
about the catalogue, not a list frozen on the day the school was created, so
provisioning now tops those roles up. Roles with a named list are still left
alone; that list is a decision the school made.

**A route name collision.** Two routes named `documents.verify` — a new public
QR endpoint and a pre-existing staff action. Nothing 404s, both URLs keep
working, and only `route('documents.verify')` silently moves to whichever was
registered last; the failure surfaced in an unrelated test. `RouteIntegrityTest`
caught duplicate method+URI but not duplicate *names*, so it does now.

**The print stylesheet was not in the build.** The CSS was in `resources/css`,
the tests passed on the markup, and the pages had `.printable` on them — but
`public/build` was stale, so printing a receipt still printed the whole
application. Nothing in the test suite can see this; it needs a browser and a
rebuild.

## Periods, semesters and the grade sheet

A Liberian high school keeps its year in **six marking periods**, three to a
**semester**, with an **examination** at the end of each semester. Before every
period closes there is a period test, and alongside it quizzes, assignments and
attendance.

### How it is stored

A period is a `terms` row with a `semester` number (1–3 → first semester,
4–6 → second). Not a new table: every mark, report card, invoice and attendance
figure already hangs off `term_id`, and a second table meaning the same thing
would split the school's history in two.

A semester is its own row (`semesters`) because it owns something a period does
not: the examination, and **whether teachers may currently enter its marks**.
That switch is an administrative decision taken on a particular day, so it is
recorded with who took it.

A semester exam is an assessment with `semester_id` set and `term_id` null. It
belongs to the semester, not to the 3rd or 6th period, so it is counted once —
in the semester average — and never skews a period grade.

**Periods & semesters** (`/academic-periods`) sets a year up. A year still
keeping terms is converted in place: its terms become the first periods, in
order, so everything filed against them stays put. Dates start spread evenly
across the year; the administrator sets the real ones, because only the school
knows whether a period is a month or a month and a half. The current period
becomes the one containing today — a converted "Third Term" would otherwise be
the current 3rd period in September.

### The arithmetic

| | |
| --- | --- |
| Period grade | marks obtained ÷ marks possible × 100 |
| Semester average | (1st + 2nd + 3rd period + semester exam) ÷ 4 |
| Yearly average | (first semester + second semester) ÷ 2 |

What each part is marked out of — period test 40, quiz 20, assignment 20,
attendance 20, exam 100 by default — is a school setting (Settings ›
Academics). **No weights**: an early version weighted each piece of work, and an
older assignment carrying a weight of 1 turned 34 + 17 + 18 + 19 into 87.53
instead of 88. Found in the browser, fixed, and tested.

**An average is blank until everything in it exists.** A semester average from
two periods and no exam looks official and is not. The same goes for a missing
mark in work the class sat: that child's period grade is blank, not a grade
built from different work to everyone else's.

The official record — parents, the default year view — counts **approved marks
only**, and a period with work still awaiting approval has no official grade
yet. Teachers see their working figure, labelled provisional.

All of it lives in `App\Services\PeriodGrades`, so the screen, the year view
and the Excel file cannot disagree.

### Entering marks

**Grade sheet** (`/grade-sheet`): choose a class, a subject and a period, and
every student who takes the subject is listed with a box for each part of the
period grade. There is no set-up step — the first mark typed creates the
assessment behind its column. The teacher dashboard lists each class they mark,
how many students have marks this period, and a button straight in.

Every write — typed or uploaded — goes through `App\Services\GradeSheet::write()`,
which is **all or nothing**: one refused mark and nothing is saved, with every
problem listed. A column says *why* it is locked:

- not your class and subject;
- the period has not started;
- submitted for approval, or approved;
- **exam entry is closed** — until someone holding `grades.exam_entry` opens it
  on Periods & semesters. Opening notifies every teacher and is audited.
  Closing keeps what was entered.

The academic office (`grades.approve`) is bound by none of these; correcting an
approved mark is audited as a correction.

### Excel: download, fill in, upload

One workbook per class and period, **a worksheet per subject** the person marks.
Row per student, column per assessment, a `Period grade` formula that matches the
screen. Student number and name are locked; mark cells accept only 0 to the
maximum. A hidden worksheet records which class and period the file is for.

On the way back in:

- rows are matched by **student number**, never by name or position;
- a file for a different class or period is refused — the hidden label is only
  used to catch that mistake; it authorises nothing;
- a worksheet relabelled to a subject the teacher does not teach is refused;
- the file goes through the same locks as the screen, so it is not a way past a
  closed exam column;
- one bad mark anywhere and nothing from the file is kept.

`grades.export` and `grades.import` are separate permissions, both in the
Teacher role by default.

## Class schedules for students and parents

A student's week — day, start and end time, subject, teacher, room — is on the
student dashboard and the parent dashboard (per child), each with a full page,
**Print**, and **Download** as Excel. Today is highlighted, and so is the lesson
happening now. Days follow the school's teaching days, plus any day that has a
lesson anyway, so a Saturday class is never hidden.

A student's download can be switched off per student (`download_timetable`). A
parent sees a child's schedule only if the school has cleared them for that
child's academic records: where a child is, hour by hour, deserves the same
protection as their results.

## Delegation: admissions head, HR, and a hole that is now closed

Every school now starts with **Admissions Head** and **HR Officer** roles, which
the administrator can widen or narrow in Roles & permissions.

Setting those up exposed a real hole. Anyone with `users.update` could give any
account — **their own included** — the School Administrator role, and anyone
with `roles.manage` could add any permission to a role they held. Delegating a
small job delegated the whole school.

`App\Support\Delegation`: **nobody may grant a privileged permission they do not
hold**, and nobody may edit or suspend an account that holds one they do not.
Privileged (`Permissions::PRIVILEGED`) means control over accounts, roles,
settings, money, the audit trail, or signing off results. Ordinary working
permissions are left out deliberately — the first version checked every
permission, and an HR officer could no longer give a new teacher the Teacher
role. `DelegationTest` was checked by switching the guard off: five tests fail.

## Also fixed along the way

**Marking views leaked across classes.** An account with `grades.enter` and no
teacher record counted as *unrestricted*, and the mark sheet showed it every
class's students and marks. Having no teacher record now narrows an account to
no classes. Saving was never affected.

**New permissions and existing roles.** A role defined as full access tops up
when the catalogue grows; a named role such as Teacher does not, by design.
Existing schools' Teacher roles therefore need `grades.export` and
`grades.import` added in Roles & permissions (done for the Grace Foundation dev
database).
