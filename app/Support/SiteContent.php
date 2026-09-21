<?php

namespace App\Support;

use App\Models\School;
use App\Models\WebsitePage;

/**
 * Every piece of wording on the public website, and what it says by default.
 *
 * The website editor shows these as fields; the public pages read them through
 * `SiteContent::for()`. Nothing is written until the school changes something,
 * so a new school still gets a complete, sensible website on day one, and a
 * field cleared back to empty falls back to the default rather than leaving a
 * blank heading.
 *
 * ":school" in a default is replaced with the school's name, so the defaults
 * read correctly for any school rather than naming one.
 *
 * Each page lists its fields in the order they appear on the page, grouped by
 * the section they belong to, so the editor reads top to bottom like the page.
 */
class SiteContent
{
    /**
     * page key => [title, summary, slug, groups => [group => [key => [label, default, multiline]]]]
     *
     * @var array<string, array<string, mixed>>
     */
    public const PAGES = [
        'home' => [
            'title' => 'Home',
            'slug' => '',
            'summary' => 'A junior and senior high school committed to academic excellence, strong character and service to our community.',
            'groups' => [
                'Welcome banner' => [
                    'hero_eyebrow' => ['Small heading above the school name', 'Welcome to'],
                    'hero_primary' => ['First button', 'Apply for admission'],
                    'hero_secondary' => ['Second button', 'Parent login'],
                ],
                'About the school' => [
                    'about_eyebrow' => ['Small heading', 'About the school'],
                    'about_heading' => ['Heading', 'A place where every student is known'],
                    'about_body' => ['Text (leave a blank line between paragraphs)', ":school provides a supportive, disciplined learning environment where students are prepared for higher education and for meaningful contribution to Liberia.\n\nOur teachers know their students by name and work closely with families throughout the year. Parents follow attendance, grades, report cards and fees from any phone.", true],
                    'about_link' => ['Link text', 'Read more about us'],
                ],
                'Mission, vision and values cards' => [
                    'card_1_title' => ['Card 1 title', 'Mission'],
                    'card_1_body' => ['Card 1 text', 'To educate and equip young Liberians for lives of integrity, service and achievement.', true],
                    'card_2_title' => ['Card 2 title', 'Vision'],
                    'card_2_body' => ['Card 2 text', 'A generation of confident, capable graduates who lead and serve their communities.', true],
                    'card_3_title' => ['Card 3 title', 'Core values'],
                    'card_3_body' => ['Card 3 text', 'Excellence, character and service, held with equal seriousness.', true],
                    'card_4_title' => ['Card 4 title', 'Our people'],
                    'card_4_body' => ['Card 4 text', 'Teachers who know every student by name, and an office that answers when families call.', true],
                ],
                'Programmes' => [
                    'programmes_eyebrow' => ['Small heading', 'Academics'],
                    'programmes_heading' => ['Heading', 'Programmes we offer'],
                    'programmes_intro' => ['Introduction', 'A balanced curriculum across junior and senior high, with pathways into the sciences, arts and commercial studies.', true],
                    'programme_1_title' => ['Programme 1 name', 'Junior High'],
                    'programme_1_meta' => ['Programme 1 grades', 'Grades 7 – 9'],
                    'programme_1_body' => ['Programme 1 description', 'A broad foundation in English, mathematics, the sciences and social studies.', true],
                    'programme_2_title' => ['Programme 2 name', 'Senior High'],
                    'programme_2_meta' => ['Programme 2 grades', 'Grades 10 – 12'],
                    'programme_2_body' => ['Programme 2 description', 'Focused study preparing students for the WASSCE and for university entrance.', true],
                    'programme_3_title' => ['Programme 3 name', 'ICT & skills'],
                    'programme_3_meta' => ['Programme 3 grades', 'All grades'],
                    'programme_3_body' => ['Programme 3 description', 'Practical computer literacy and life skills woven through every year group.', true],
                    'programmes_link' => ['Link text', 'See the full curriculum'],
                ],
                'Why choose us' => [
                    'why_eyebrow' => ['Small heading', 'Why choose us'],
                    'why_heading' => ['Heading', 'What families tell us matters'],
                    'why_1_title' => ['Reason 1', 'Small classes'],
                    'why_1_body' => ['Reason 1 text', 'Teachers know every student by name and notice when something changes.', true],
                    'why_2_title' => ['Reason 2', 'Clear reporting'],
                    'why_2_body' => ['Reason 2 text', 'Attendance, grades and fees are visible to parents the moment they are recorded.', true],
                    'why_3_title' => ['Reason 3', 'Strong discipline'],
                    'why_3_body' => ['Reason 3 text', 'Character is held as seriously as academic results.', true],
                    'why_4_title' => ['Reason 4', 'Open office'],
                    'why_4_body' => ['Reason 4 text', 'Questions are answered by people, not by a queue.', true],
                ],
                'Teachers, news, events and gallery' => [
                    'teachers_eyebrow' => ['Teachers: small heading', 'Our teachers'],
                    'teachers_heading' => ['Teachers: heading', 'The people in front of the class'],
                    'teachers_link' => ['Teachers: link text', 'Meet the staff'],
                    'news_eyebrow' => ['News: small heading', 'Latest news'],
                    'news_heading' => ['News: heading', 'From around the school'],
                    'news_link' => ['News: link text', 'All news'],
                    'announcements_eyebrow' => ['Announcements: heading', 'Announcements'],
                    'events_eyebrow' => ['Events: heading', 'Upcoming events'],
                    'events_link' => ['Events: link text', 'All events'],
                    'gallery_eyebrow' => ['Gallery: small heading', 'Gallery'],
                    'gallery_heading' => ['Gallery: heading', 'School life'],
                    'gallery_link' => ['Gallery: link text', 'See the gallery'],
                ],
                'Admissions banner' => [
                    'cta_heading' => ['Heading', 'Admissions are open'],
                    'cta_body' => ['Text', "Apply online in a few minutes. Upload your child's previous report card and documents, and we will contact you once the application has been reviewed.", true],
                    'cta_primary' => ['First button', 'Start an application'],
                    'cta_secondary' => ['Second button', 'How admission works'],
                ],
                'Get in touch' => [
                    'contact_eyebrow' => ['Small heading', 'Get in touch'],
                    'contact_heading' => ['Heading', 'We are glad to hear from you'],
                    'contact_body' => ['Text', 'The office is open on weekdays during term time. For admissions enquiries, please have your application number to hand if you have already applied.', true],
                    'contact_link' => ['Link text', 'Contact the school'],
                ],
            ],
        ],

        'about' => [
            'title' => 'About us',
            'slug' => 'about',
            'summary' => 'A supportive learning community dedicated to character, achievement, and service.',
            'groups' => [
                'Staff section' => [
                    'staff_eyebrow' => ['Small heading', 'Our staff'],
                    'staff_heading' => ['Heading', 'The people who teach here'],
                    'staff_link' => ['Link text', 'Meet all our teachers'],
                ],
                'Closing banner' => [
                    'cta_heading' => ['Heading', 'Come and see the school'],
                    'cta_body' => ['Text', 'Applications are open. Start online, or contact the office to arrange a visit.', true],
                    'cta_primary' => ['First button', 'Apply for admission'],
                    'cta_secondary' => ['Second button', 'Contact the office'],
                ],
            ],
        ],

        'academics' => [
            'title' => 'Academics',
            'slug' => 'academics',
            'summary' => 'A balanced junior and senior high curriculum designed to prepare confident lifelong learners.',
            'groups' => [
                'Sections' => [
                    'classes_eyebrow' => ['Classes: small heading', 'Classes'],
                    'classes_heading' => ['Classes: heading', 'What we teach, and to whom'],
                    'subjects_covered' => ['Label above each class’s subjects', 'Subjects covered'],
                    'departments_eyebrow' => ['Departments: small heading', 'Departments'],
                    'departments_heading' => ['Departments: heading', 'How teaching is organised'],
                    'subjects_eyebrow' => ['Subjects: small heading', 'Subjects'],
                    'subjects_heading' => ['Subjects: heading', 'Every subject we offer'],
                    'calendar_eyebrow' => ['Calendar: small heading', 'Academic calendar'],
                    'calendar_heading' => ['Calendar: heading', 'Term dates'],
                ],
                'Closing banner' => [
                    'cta_heading' => ['Heading', 'Join us next term'],
                    'cta_body' => ['Text', 'See what we ask for and apply online in a few minutes.', true],
                    'cta_primary' => ['First button', 'Admissions information'],
                    'cta_secondary' => ['Second button', 'Apply online'],
                ],
            ],
        ],

        'admissions' => [
            'title' => 'Admissions',
            'slug' => 'admissions',
            'summary' => 'Apply online in a few minutes and track your application with the number we issue.',
            'groups' => [
                'Banner' => [
                    'hero_button' => ['Button', 'Apply online'],
                ],
                'How admission works' => [
                    'process_eyebrow' => ['Small heading', 'The process'],
                    'process_heading' => ['Heading', 'How admission works'],
                    'step_1_title' => ['Step 1', 'Apply online'],
                    'step_1_body' => ['Step 1 text', 'Complete the four-step form: student details, guardian details, documents, then review.', true],
                    'step_2_title' => ['Step 2', 'Get your number'],
                    'step_2_body' => ['Step 2 text', 'You receive an application number immediately. Keep it for any enquiry.', true],
                    'step_3_title' => ['Step 3', 'We verify'],
                    'step_3_body' => ['Step 3 text', 'The registrar reviews the application and checks the documents you uploaded.', true],
                    'step_4_title' => ['Step 4', 'We contact you'],
                    'step_4_body' => ['Step 4 text', 'You hear the outcome on the phone number or email address you gave us.', true],
                ],
                'Documents, classes and fees' => [
                    'documents_eyebrow' => ['Documents: small heading', 'What to bring'],
                    'documents_heading' => ['Documents: heading', 'Required documents'],
                    'documents_note' => ['Documents: note', 'Documents can be uploaded as PDF or photographs during the application, or brought to the school office.', true],
                    'classes_eyebrow' => ['Classes: small heading', 'Where you can apply'],
                    'classes_heading' => ['Classes: heading', 'Available classes'],
                    'classes_empty' => ['Classes: when none are listed', 'Contact the office to ask which classes are currently accepting applications.', true],
                    'fees_eyebrow' => ['Fees: small heading', 'School fees'],
                    'fees_heading' => ['Fees: heading', 'What it costs'],
                    'fees_note' => ['Fees: note', 'Fees are charged per term. Contact the office about payment plans or scholarships.', true],
                ],
                'Closing banner' => [
                    'cta_heading' => ['Heading', 'Ready to apply?'],
                    'cta_body' => ['Text', 'The form takes a few minutes. Nothing is submitted until you review and confirm.', true],
                    'cta_primary' => ['First button', 'Apply online'],
                    'cta_secondary' => ['Second button', 'Ask a question'],
                ],
            ],
        ],

        'teachers' => [
            'title' => 'Our teachers',
            'slug' => 'teachers-and-staff',
            'summary' => 'The people in front of the class at :school, and what they bring to it.',
            'groups' => [
                'Page' => [
                    'empty_heading' => ['When no profiles are published', 'No profiles published yet'],
                ],
                'Closing banner' => [
                    'cta_heading' => ['Heading', 'Join a school that knows your child'],
                    'cta_body' => ['Text', 'Small classes, teachers who notice, and reporting families can actually follow.', true],
                    'cta_primary' => ['First button', 'Apply for admission'],
                    'cta_secondary' => ['Second button', 'Contact the office'],
                ],
            ],
        ],

        'news' => [
            'title' => 'News',
            'slug' => 'news',
            'summary' => '',
            'groups' => [
                'Page' => [
                    'eyebrow' => ['Small heading', 'Latest from the school'],
                    'empty' => ['When there is no news', 'There is no news to show just yet.'],
                    'read_more' => ['Link on each post', 'Read more'],
                ],
            ],
        ],

        'events' => [
            'title' => 'Events',
            'slug' => 'events',
            'summary' => '',
            'groups' => [
                'Page' => [
                    'eyebrow' => ['Small heading', 'What is coming up'],
                    'empty' => ['When there are no events', 'There are no events on the calendar right now.'],
                ],
            ],
        ],

        'gallery' => [
            'title' => 'Gallery',
            'slug' => 'gallery',
            'summary' => '',
            'groups' => [
                'Page' => [
                    'eyebrow' => ['Small heading', 'School life'],
                    'empty' => ['When there are no photographs', 'Photographs will be published here soon.'],
                ],
            ],
        ],

        'contact' => [
            'title' => 'Contact us',
            'slug' => 'contact',
            'summary' => 'Reach the school office for admissions, academic and general enquiries.',
            'groups' => [
                'Page' => [
                    'overview_eyebrow' => ['Small heading above your content', 'Overview'],
                    'fallback_body' => ['Text when no content blocks are written', 'Contact the school office using the details opposite, or apply online to begin an admission application.', true],
                    'details_heading' => ['Contact details box heading', 'Get in touch'],
                ],
                'Next steps' => [
                    'next_eyebrow' => ['Small heading', 'Next steps'],
                    'next_heading' => ['Heading', 'Ready to join us?'],
                    'next_1_title' => ['Card 1 title', 'Apply online'],
                    'next_1_body' => ['Card 1 text', 'Complete the four-step admission form from any device.', true],
                    'next_2_title' => ['Card 2 title', 'Academic programmes'],
                    'next_2_body' => ['Card 2 text', 'See what we teach across junior and senior high.', true],
                    'next_3_title' => ['Card 3 title', 'Talk to us'],
                    'next_3_body' => ['Card 3 text', 'Reach the office for admissions and general enquiries.', true],
                    'next_link' => ['Link text on each card', 'Continue'],
                ],
            ],
        ],

        'online-services' => [
            'title' => 'Online services',
            'slug' => 'online-services',
            'summary' => 'Track an admission application, confirm that someone is a student of :school, check that a grade sheet or report card is genuine, and find how to pay fees and reach the office.',
            'groups' => [
                'Banner' => [
                    'eyebrow' => ['Small heading', 'No account needed'],
                ],
                'The three checks' => [
                    'application_title' => ['Application status: title', 'Check Application Status'],
                    'application_intro' => ['Application status: text', "Already applied? Track your application's progress here.", true],
                    'student_title' => ['Verify a student: title', 'Verify a Student'],
                    'student_intro' => ['Verify a student: text', 'Confirm that someone is a student of :school.', true],
                    'document_title' => ['Verify a document: title', 'Verify a Document'],
                    'document_intro' => ['Verify a document: text', 'Check a grade sheet or report card using the verification code printed at the bottom of it.', true],
                ],
                'Information cards' => [
                    'apply_title' => ['Admission: title', 'Apply online'],
                    'apply_body' => ['Admission: text', 'Fill in the application form and upload the required documents. You receive an application number straight away — keep it to check your status on this page.', true],
                    'portal_title' => ['Portal: title', 'The school portal'],
                    'portal_items' => ['Portal: what families can do (one per line)', "See grades, grade sheets and report cards\nView and download the weekly class schedule\nFollow attendance and assignments\nCheck fees, payments and receipts\nMessage teachers and the school office", true],
                    'portal_note' => ['Portal: note', 'No account yet, or forgotten your password? Contact the school office.', true],
                    'fees_title' => ['Fees: title', 'Paying fees'],
                    'fees_documents' => ['Fees: heading above the fee documents', 'Fee structure documents'],
                    'verify_title' => ['Verification: title', 'Checking our documents'],
                    'verify_body' => ['Verification: text', 'Enter the verification code from the bottom of a grade sheet or report card to see the grades the school has on record. Scan the QR code on a receipt, admission letter or student record to confirm it was issued by the school. Verifying a student shows only a shortened name, class and enrolment status.', true],
                    'contact_title' => ['Contact: title', 'Contact the office'],
                ],
            ],
        ],
    ];

    /** Custom pages the school adds itself are stored under this key prefix. */
    public const CUSTOM_PREFIX = 'custom-';

    protected array $values;

    public function __construct(protected string $pageKey, protected ?WebsitePage $page, protected ?School $school)
    {
        $this->values = $page?->texts ?? [];
    }

    public static function for(?WebsitePage $page, string $pageKey, ?School $school): self
    {
        return new self($pageKey, $page, $school);
    }

    /** A field's wording: the school's own if written, otherwise the default. */
    public function __invoke(string $key): string
    {
        $value = $this->values[$key] ?? null;

        return filled($value) ? (string) $value : $this->default($key);
    }

    /** Whether the school has written this field itself. */
    public function has(string $key): bool
    {
        return filled($this->values[$key] ?? null);
    }

    public function title(): string
    {
        return filled($this->page?->title) ? $this->page->title : $this->fill(self::PAGES[$this->pageKey]['title'] ?? '');
    }

    public function summary(): string
    {
        return filled($this->page?->summary) ? $this->page->summary : $this->fill(self::PAGES[$this->pageKey]['summary'] ?? '');
    }

    /** Paragraphs of a multi-line field, split on blank lines. */
    public function paragraphs(string $key): array
    {
        return array_values(array_filter(preg_split('/\n\s*\n/', trim($this($key)))));
    }

    /** Lines of a one-item-per-line field. */
    public function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this($key)))));
    }

    public function default(string $key): string
    {
        foreach (self::PAGES[$this->pageKey]['groups'] ?? [] as $fields) {
            if (isset($fields[$key])) {
                return $this->fill($fields[$key][1]);
            }
        }

        return '';
    }

    /** @return array<string, array<string, array{0: string, 1: string, 2?: bool}>> */
    public static function groupsFor(string $pageKey): array
    {
        return self::PAGES[$pageKey]['groups'] ?? [];
    }

    /** @return array<int, string> every field key a page accepts */
    public static function keysFor(string $pageKey): array
    {
        return collect(self::groupsFor($pageKey))->flatMap(fn (array $fields) => array_keys($fields))->all();
    }

    protected function fill(string $text): string
    {
        return str_replace(':school', $this->school?->name ?? '', $text);
    }
}
