<?php

namespace App\Support;

/**
 * The platform's permission catalogue.
 *
 * This is the single source of truth: the seeder writes these into the
 * permissions table, AuthServiceProvider registers a Gate for each one, and the
 * role editor renders them grouped exactly as listed here. Adding a capability
 * means adding it here and re-running the permission seeder, never hard-coding
 * a slug elsewhere.
 */
class Permissions
{
    /** Grants every permission in the catalogue. */
    public const ALL = '*';

    /** @var array<string, array<string, string>> group => (slug => label) */
    public const CATALOGUE = [
        'Dashboard' => [
            'dashboard.view' => 'View the dashboard',
        ],
        'Admissions' => [
            'admissions.view' => 'View admission applications',
            'admissions.review' => 'Review applications and add notes',
            'admissions.approve' => 'Approve applications',
            'admissions.reject' => 'Reject applications',
            'admissions.enroll' => 'Enroll approved applicants',
            'admissions.documents.verify' => 'Verify submitted documents',
        ],
        'Students' => [
            'students.view' => 'View students',
            'students.create' => 'Add students',
            'students.update' => 'Edit student records',
            'students.archive' => 'Archive or restore students',
            'students.export' => 'Export student data',
        ],
        'Parents and guardians' => [
            'guardians.view' => 'View parents and guardians',
            'guardians.create' => 'Add parents and guardians',
            'guardians.update' => 'Edit parent and guardian records',
            'guardians.archive' => 'Archive parent and guardian records',
        ],
        'Teachers and staff' => [
            'teachers.view' => 'View teachers and staff',
            'teachers.create' => 'Add teachers and staff',
            'teachers.update' => 'Edit teacher and staff records',
            'teachers.archive' => 'Archive teacher and staff records',
        ],
        'Academics' => [
            'academics.view' => 'View academic structure',
            'academics.manage' => 'Manage years, terms, classes, sections and subjects',
            'timetable.view' => 'View timetables',
            'timetable.manage' => 'Manage timetables',
        ],
        'Attendance' => [
            'attendance.view' => 'View attendance',
            'attendance.record' => 'Record attendance',
            'attendance.report' => 'Run attendance reports',
        ],
        'Examinations and grades' => [
            'exams.view' => 'View examinations',
            'exams.manage' => 'Manage examinations and assessments',
            'grades.enter' => 'Enter grades',
            'grades.approve' => 'Approve and publish grades',
            'reportcards.view' => 'View report cards',
            'reportcards.generate' => 'Generate report cards',
        ],
        'Finance' => [
            'fees.manage' => 'Manage fee structures',
            'invoices.manage' => 'Manage invoices',
            'payments.view' => 'View payments',
            'payments.record' => 'Record payments and issue receipts',
            'expenses.manage' => 'Manage expenses',
            'finance.report' => 'Run financial reports',
        ],
        'Communication' => [
            'announcements.manage' => 'Publish announcements',
            'events.manage' => 'Manage events',
            'messages.send' => 'Send messages',
            'requests.manage' => 'Handle parent requests',
        ],
        'Website' => [
            'website.manage' => 'Manage public website content',
        ],
        'Users' => [
            'users.view' => 'View user accounts',
            'users.create' => 'Create user accounts',
            'users.update' => 'Edit user accounts',
            'users.suspend' => 'Suspend or reactivate accounts',
        ],
        'Roles and permissions' => [
            'roles.view' => 'View roles',
            'roles.manage' => 'Create and edit roles and permissions',
        ],
        'Reports' => [
            'reports.view' => 'View reports',
            'reports.export' => 'Export and print reports',
        ],
        'Settings' => [
            'settings.manage' => 'Manage school settings and branding',
        ],
        'Audit' => [
            'audit.view' => 'View the audit trail',
        ],
    ];

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(self::flat());
    }

    /** @return array<string, string> slug => label */
    public static function flat(): array
    {
        return array_merge(...array_values(self::CATALOGUE));
    }

    public static function exists(string $slug): bool
    {
        return array_key_exists($slug, self::flat());
    }

    public static function groupOf(string $slug): ?string
    {
        foreach (self::CATALOGUE as $group => $permissions) {
            if (array_key_exists($slug, $permissions)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Default roles every school starts with, and the permissions they carry.
     *
     * @return array<string, array{name: string, description: string, permissions: array<int, string>}>
     */
    public static function defaultRoles(): array
    {
        return [
            'school-administrator' => [
                'name' => 'School Administrator',
                'description' => 'Full administrative access for this school.',
                'permissions' => [self::ALL],
            ],
            'principal' => [
                'name' => 'Principal',
                'description' => 'Academic leadership with oversight of every module.',
                'permissions' => [
                    'dashboard.view', 'admissions.view', 'admissions.review', 'admissions.approve',
                    'admissions.reject', 'students.view', 'guardians.view', 'teachers.view',
                    'academics.view', 'academics.manage', 'timetable.view', 'attendance.view',
                    'attendance.report', 'exams.view', 'exams.manage', 'grades.approve',
                    'reportcards.view', 'reportcards.generate', 'finance.report', 'announcements.manage',
                    'events.manage', 'requests.manage', 'reports.view', 'reports.export', 'audit.view',
                ],
            ],
            'vice-principal' => [
                'name' => 'Vice Principal',
                'description' => 'Supports academic leadership and daily operations.',
                'permissions' => [
                    'dashboard.view', 'admissions.view', 'admissions.review', 'students.view',
                    'guardians.view', 'teachers.view', 'academics.view', 'timetable.view',
                    'timetable.manage', 'attendance.view', 'attendance.report', 'exams.view',
                    'grades.approve', 'reportcards.view', 'announcements.manage', 'events.manage',
                    'requests.manage', 'reports.view',
                ],
            ],
            'registrar' => [
                'name' => 'Registrar',
                'description' => 'Admissions, enrollment, student records and documents.',
                'permissions' => [
                    'dashboard.view', 'admissions.view', 'admissions.review', 'admissions.approve',
                    'admissions.reject', 'admissions.enroll', 'admissions.documents.verify',
                    'students.view', 'students.create', 'students.update', 'students.archive',
                    'students.export', 'guardians.view', 'guardians.create', 'guardians.update',
                    'academics.view', 'reports.view', 'reports.export',
                ],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Fees, invoices, payments and financial reporting.',
                'permissions' => [
                    'dashboard.view', 'students.view', 'guardians.view', 'fees.manage',
                    'invoices.manage', 'payments.view', 'payments.record', 'expenses.manage',
                    'finance.report', 'reports.view', 'reports.export',
                ],
            ],
            'teacher' => [
                'name' => 'Teacher',
                'description' => 'Teaching workspace: classes, attendance, assessments and grades.',
                'permissions' => [
                    'dashboard.view', 'students.view', 'academics.view', 'timetable.view',
                    'attendance.view', 'attendance.record', 'exams.view', 'grades.enter',
                    'reportcards.view', 'messages.send',
                ],
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'General staff workspace.',
                'permissions' => ['dashboard.view', 'students.view', 'academics.view', 'timetable.view'],
            ],
            'parent-guardian' => [
                'name' => 'Parent / Guardian',
                'description' => 'Parent portal access for linked children only.',
                'permissions' => [],
            ],
            'student' => [
                'name' => 'Student',
                'description' => 'Student portal access, governed by per-student permissions.',
                'permissions' => [],
            ],
        ];
    }
}
