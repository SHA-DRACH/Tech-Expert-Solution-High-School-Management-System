<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\WebsitePage;
use App\Support\SchoolContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Gives each school a complete website from day one.
 *
 * The copy is deliberately generic and ready to be rewritten in the website
 * editor; the point is that a newly onboarded school is never left with blank
 * public pages.
 */
class WebsiteContentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        School::all()->each(function (School $school) {
            app(SchoolContext::class)->for($school, fn () => $this->pagesFor($school));
        });
    }

    protected function pagesFor(School $school): void
    {
        foreach ($this->definitions($school) as $key => $definition) {
            WebsitePage::firstOrCreate(
                ['school_id' => $school->id, 'key' => $key],
                [
                    'title' => $definition['title'],
                    'slug' => Str::slug($definition['title']),
                    'summary' => $definition['summary'],
                    'sections' => $definition['sections'],
                    'meta_description' => Str::limit($definition['summary'], 150),
                    'is_published' => true,
                    'position' => $definition['position'],
                ],
            );
        }
    }

    /** @return array<string, array<string, mixed>> */
    protected function definitions(School $school): array
    {
        $name = $school->name;

        return [
            'home' => [
                'title' => 'Home',
                'position' => 1,
                // Deliberately not the motto: the hero prints that directly
                // above, and the two would read as the same line twice.
                'summary' => 'A junior and senior high school committed to academic excellence, strong character and service to our community.',
                'sections' => [
                    [
                        'heading' => 'Welcome to '.$name,
                        'body' => "{$name} provides a supportive, disciplined learning environment where students are prepared for higher education and for meaningful contribution to Liberia.\n\nOur teachers know their students by name and work closely with families throughout the year.",
                    ],
                ],
            ],
            'about' => [
                'title' => 'About us',
                'position' => 2,
                'summary' => 'A supportive learning community dedicated to character, achievement, and service.',
                'sections' => [
                    [
                        'heading' => 'Our story',
                        'body' => "{$name} was founded to give young Liberians an education that is rigorous, affordable and rooted in strong character.\n\nWe have grown steadily, and every year our graduates go on to further study and to work that serves their communities.",
                    ],
                    [
                        'heading' => 'Our mission',
                        'body' => 'To educate and equip young Liberians for lives of integrity, service and achievement.',
                    ],
                    [
                        'heading' => 'Our vision',
                        'body' => 'A generation of confident, capable graduates who lead and serve their communities.',
                    ],
                    [
                        'heading' => 'Our values',
                        'body' => "Excellence: high academic standards, supported by teaching that meets each student where they are.\n\nCharacter: discipline, respect and honesty held as seriously as academic results.\n\nService: an education is something to be used for the good of others.",
                    ],
                    [
                        'heading' => "Principal's message",
                        'body' => "Thank you for considering {$name} for your child.\n\nWe are a school that believes attention matters more than size. Our teachers know their students, our office answers when families call, and we would rather tell you early that something needs work than surprise you at the end of term.\n\nYou are welcome to visit and see for yourself.",
                    ],
                    [
                        'heading' => 'Facilities',
                        'body' => "Classrooms for junior and senior high, a science laboratory, a computer laboratory, a library and a sports field.\n\nFacilities are modest and well kept. We would rather maintain what we have properly than build what we cannot look after.",
                    ],
                    [
                        'heading' => 'School policies',
                        'body' => "Attendance is recorded every day and guardians are told the same day a student is absent.\n\nAssessment follows a published grading scale, and results become official only after the academic office has approved them.\n\nOur full policies on discipline, uniform and safeguarding are available from the school office on request.",
                    ],
                ],
            ],
            'academics' => [
                'title' => 'Academics',
                'position' => 3,
                'summary' => 'A balanced junior and senior high curriculum designed to prepare confident lifelong learners.',
                'sections' => [
                    [
                        'heading' => 'Junior High, Grades 7 to 9',
                        'body' => 'A broad foundation in English, mathematics, the sciences and social studies, with practical computer literacy woven through every year group.',
                    ],
                    [
                        'heading' => 'Senior High, Grades 10 to 12',
                        'body' => 'Focused study preparing students for the WASSCE and for university entrance, with pathways into the sciences, arts and commercial studies.',
                    ],
                    [
                        'heading' => 'The academic year',
                        'body' => 'Our year runs across three terms. Term dates, examination periods and report card releases are published to families through the parent portal.',
                    ],
                ],
            ],
            'admissions' => [
                'title' => 'Admissions',
                'position' => 4,
                'summary' => 'Apply online in a few minutes and track your application with the number we issue.',
                'sections' => [
                    [
                        'heading' => 'Admission requirements',
                        'body' => "We welcome applications from students entering Grades 7 to 12.

There is no entrance examination for most classes; where a place is contested we may invite the student for a short assessment and a conversation with the family.",
                    ],
                    [
                        'heading' => 'How to apply',
                        'body' => "Complete the online application form, upload your child's previous report card and supporting documents, and submit.\n\nYou will receive an application number immediately. Keep it: you will need it whenever you contact the school about the application.",
                    ],
                    [
                        'heading' => 'What we ask for',
                        'body' => "A previous report card or transcript, a birth certificate, a passport photograph, and a transfer certificate where the student is moving from another school.\n\nWe will contact you if anything is unclear or missing.",
                    ],
                    [
                        'heading' => 'What happens next',
                        'body' => 'The registrar reviews the application and verifies the documents. Once a decision is made, we contact you on the phone number or email address you provided.',
                    ],
                ],
            ],
            'contact' => [
                'title' => 'Contact us',
                'position' => 5,
                'summary' => 'Reach the school office for admissions, academic and general enquiries.',
                'sections' => [
                    [
                        'heading' => 'The school office',
                        'body' => "The office is open on weekdays during term time.\n\nFor admissions enquiries, please have your application number to hand if you have already applied.",
                    ],
                ],
            ],
        ];
    }
}
