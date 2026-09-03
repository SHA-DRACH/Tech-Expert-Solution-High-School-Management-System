<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\CsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CSV import (spec section 57).
 *
 * Two deliberate choices run through this controller.
 *
 * Nothing is written until the person has seen what will happen. An upload
 * produces a preview - which rows will be created, which will be skipped, and
 * exactly why - and only a second, explicit confirmation commits it. Importing
 * hundreds of people into a live school is not something to do on one click.
 *
 * A bad row fails alone. A single mistyped date should not throw away the other
 * four hundred rows of a spreadsheet a registrar spent the morning preparing,
 * so each row is validated on its own and the failures are reported by line
 * number.
 */
class ImportController extends Controller
{
    /** What each import needs, and what it will accept. */
    protected const DEFINITIONS = [
        'teachers' => [
            'label' => 'Teachers & staff',
            'permission' => 'teachers.create',
            'required' => ['first_name', 'last_name'],
            'optional' => [
                'middle_name', 'gender', 'date_of_birth', 'phone', 'email', 'address',
                'department', 'employment_type', 'hired_on', 'experience_years', 'biography',
            ],
            'back' => 'teachers.index',
        ],
    ];

    public function create(Request $request, string $type): View
    {
        $definition = $this->definition($request, $type);

        return view('imports.create', [
            'type' => $type,
            'definition' => $definition,
            'preview' => session('import_preview'),
        ]);
    }

    /** Step one: read the file and show what would happen. */
    public function preview(Request $request, string $type, CsvImporter $importer): RedirectResponse
    {
        $definition = $this->definition($request, $type);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:5120'],
        ], [], ['file' => 'CSV file']);

        $result = $importer->read($request->file('file'));

        if ($result['error'] !== null) {
            return back()->withErrors(['file' => $result['error']]);
        }

        $missing = $importer->missingColumns($result['headings'], $definition['required']);

        if ($missing !== []) {
            return back()->withErrors([
                'file' => 'That file is missing the '.collect($missing)->join(', ').' column'
                    .(count($missing) === 1 ? '' : 's').'. The first row must be the column headings.',
            ]);
        }

        if ($result['rows']->isEmpty()) {
            return back()->withErrors(['file' => 'That file has headings but no rows.']);
        }

        $checked = $this->check($type, $result['rows']);

        /*
         | The rows are carried in the session rather than re-uploaded on
         | confirm. The file itself is not kept: an import of staff records
         | holds personal data, and leaving copies on disk after the fact is
         | how that data ends up somewhere it should not be.
         */
        return back()->with('import_preview', [
            'type' => $type,
            'valid' => $checked['valid'],
            'problems' => $checked['problems'],
        ]);
    }

    /** Step two: write the rows the preview showed. */
    public function store(Request $request, string $type, AuditLogger $audit): RedirectResponse
    {
        $definition = $this->definition($request, $type);

        $preview = session('import_preview');

        if (! is_array($preview) || ($preview['type'] ?? null) !== $type || empty($preview['valid'])) {
            return redirect()
                ->route('imports.create', $type)
                ->withErrors(['file' => 'That preview has expired. Upload the file again.']);
        }

        /*
         | Re-checked at the moment of writing, not trusted from the session.
         | A staff number or an email may have been taken by someone else in
         | the minutes between the preview and the confirmation, and the
         | session is data that came back from the browser.
         */
        $checked = $this->check($type, collect($preview['valid']));

        $created = match ($type) {
            'teachers' => $this->createTeachers(collect($checked['valid'])),
        };

        $audit->log('imported', 'Teachers', $created.' '.$definition['label'].' records were imported from a CSV file.');

        $skipped = count($preview['valid']) - $created;

        return redirect()
            ->route($definition['back'])
            ->with('status', trim(
                $created.' '.\Illuminate\Support\Str::plural('record', $created).' imported.'
                .($skipped > 0 ? " {$skipped} could no longer be imported and were skipped." : '')
            ));
    }

    /* ------------------------------------------------------------ checking */

    /**
     * Validate every row on its own.
     *
     * @return array{valid: array<int, array<string, mixed>>, problems: array<int, array{line: int, name: string, reason: string}>}
     */
    protected function check(string $type, Collection $rows): array
    {
        $valid = [];
        $problems = [];

        // Collected as we go so two rows in the same file claiming one email
        // address are caught, not just clashes with what is already stored.
        $seenEmails = [];

        foreach ($rows as $row) {
            $line = (int) ($row['__line'] ?? 0);

            $validator = Validator::make($row, $this->rulesFor($type));

            if ($validator->fails()) {
                $problems[] = [
                    'line' => $line,
                    'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) ?: '(blank)',
                    'reason' => $validator->errors()->first(),
                ];

                continue;
            }

            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($email !== '') {
                if (isset($seenEmails[$email])) {
                    $problems[] = [
                        'line' => $line,
                        'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
                        'reason' => "The email {$email} is also used on line {$seenEmails[$email]} of this file.",
                    ];

                    continue;
                }

                if (Teacher::where('email', $email)->exists()) {
                    $problems[] = [
                        'line' => $line,
                        'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
                        'reason' => "A staff record with the email {$email} already exists.",
                    ];

                    continue;
                }

                $seenEmails[$email] = $line;
            }

            $valid[] = $row;
        }

        return ['valid' => $valid, 'problems' => $problems];
    }

    protected function rulesFor(string $type): array
    {
        return match ($type) {
            'teachers' => [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other', 'male', 'female', 'other'])],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'hired_on' => ['nullable', 'date'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'department' => ['nullable', 'string', 'max:120'],
                'employment_type' => ['nullable', 'string', 'max:40'],
                'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
                'biography' => ['nullable', 'string', 'max:2000'],
            ],
        };
    }

    /* ------------------------------------------------------------ writing */

    protected function createTeachers(Collection $rows): int
    {
        $created = 0;

        DB::transaction(function () use ($rows, &$created) {
            // Departments are matched by name, case-insensitively, and one that
            // does not exist is left unset rather than invented: quietly
            // creating departments from a typo in a spreadsheet would corrupt
            // the academic structure the school set up deliberately.
            $departments = Department::pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id]);

            $prefix = request()->user()->school->numberPrefix().'-T-';
            $number = $this->highestStaffNumber($prefix);

            foreach ($rows as $row) {
                $created++;
                $number++;

                /*
                 | Every optional column is read through this, because a school's
                 | spreadsheet will simply not have most of them. Reading a
                 | missing column directly is a 500 on a file that is perfectly
                 | valid - only first_name and last_name are ever required.
                 | Blank is stored as null rather than an empty string, so
                 | "no phone number recorded" is one value and not two.
                 */
                $value = fn (string $column) => filled($row[$column] ?? null) ? trim((string) $row[$column]) : null;

                Teacher::create([
                    'staff_number' => $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                    'first_name' => $value('first_name'),
                    'middle_name' => $value('middle_name'),
                    'last_name' => $value('last_name'),
                    'gender' => $value('gender') ? ucfirst(strtolower($value('gender'))) : null,
                    'date_of_birth' => $value('date_of_birth'),
                    'phone' => $value('phone'),
                    'email' => $value('email') ? strtolower($value('email')) : null,
                    'address' => $value('address'),
                    'department_id' => $departments[strtolower((string) $value('department'))] ?? null,
                    'employment_type' => $value('employment_type'),
                    'hired_on' => $value('hired_on'),
                    'experience_years' => $value('experience_years') !== null ? (int) $value('experience_years') : null,
                    'biography' => $value('biography'),
                    'status' => 'active',
                    // Imported staff are not published to the public website
                    // until someone chooses to publish them. A bulk upload
                    // should never put a person's name and photograph on the
                    // open internet as a side effect.
                    'is_public' => false,
                ]);
            }
        });

        return $created;
    }

    /**
     * The highest staff number already taken, read once before the loop.
     *
     * Counting up from a single read rather than re-querying per row: trashed
     * records are included, so a number belonging to an archived teacher is
     * never handed to somebody else.
     */
    protected function highestStaffNumber(string $prefix): int
    {
        $highest = Teacher::withTrashed()
            ->where('staff_number', 'like', $prefix.'%')
            ->orderByDesc('staff_number')
            ->value('staff_number');

        return $highest ? (int) substr($highest, strlen($prefix)) : 0;
    }

    /** @return array<string, mixed> */
    protected function definition(Request $request, string $type): array
    {
        abort_unless(array_key_exists($type, self::DEFINITIONS), 404);

        $definition = self::DEFINITIONS[$type];

        abort_unless($request->user()->hasPermission($definition['permission']), 403);

        return $definition;
    }
}
