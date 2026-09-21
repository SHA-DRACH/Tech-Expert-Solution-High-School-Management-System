<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\WebsitePage;
use App\Support\SchoolContext;
use App\Support\SiteContent;
use App\Support\SiteMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Everything on the public website is the school's to change: the wording of
 * each page, pages of its own, the menu and the footer.
 */
class WebsiteEditingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool(['name' => 'Hope Academy']);
        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function editor()
    {
        return $this->userFor($this->school, ['website.manage', 'dashboard.view']);
    }

    protected function page(string $key): WebsitePage
    {
        WebsitePage::ensureBuiltIn($this->school);

        return WebsitePage::where('key', $key)->firstOrFail();
    }

    protected function save(WebsitePage $page, array $texts, array $extra = [])
    {
        return $this->actingAs($this->editor())->put(route('website.pages.update', $page), array_merge([
            'title' => $page->title,
            'is_published' => 1,
            'texts' => $texts,
        ], $extra));
    }

    /* ------------------------------------------------------------ wording */

    public function test_every_public_page_is_listed_for_editing(): void
    {
        $response = $this->actingAs($this->editor())->get(route('website.index'))->assertOk();

        foreach (['Home', 'About us', 'Academics', 'Admissions', 'Our teachers', 'News', 'Events', 'Gallery', 'Online services', 'Contact us'] as $title) {
            $response->assertSee($title);
        }
    }

    public function test_the_page_editor_offers_every_piece_of_wording_with_its_default(): void
    {
        $this->actingAs($this->editor())->get(route('website.pages.edit', $this->page('home')))
            ->assertOk()
            ->assertSee('Page wording')
            ->assertSee('name="texts[programmes_heading]"', false)
            ->assertSee('placeholder="Programmes we offer"', false)
            ->assertSee('Hope Academy provides a supportive', false);
    }

    public function test_changed_wording_appears_on_the_public_page(): void
    {
        $this->save($this->page('home'), [
            'programmes_heading' => 'What we teach at Hope',
            'why_1_title' => 'Tiny classes',
            'cta_heading' => 'Enrolment is open for 2027',
        ])->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('What we teach at Hope')
            ->assertSee('Tiny classes')
            ->assertSee('Enrolment is open for 2027')
            ->assertDontSee('Programmes we offer');
    }

    public function test_clearing_a_field_brings_the_default_back(): void
    {
        $page = $this->page('home');
        $this->save($page, ['programmes_heading' => 'Something else']);
        $this->save($page->fresh(), ['programmes_heading' => '']);

        $this->assertArrayNotHasKey('programmes_heading', $page->fresh()->texts ?? []);
        $this->get(route('home'))->assertSee('Programmes we offer');
    }

    public function test_a_page_only_stores_fields_it_actually_has(): void
    {
        $page = $this->page('news');
        $this->save($page, ['eyebrow' => 'From our classrooms', 'programmes_heading' => 'Not a news field']);

        $this->assertSame(['eyebrow' => 'From our classrooms'], $page->fresh()->texts);
    }

    public function test_title_introduction_and_wording_are_editable_on_every_page(): void
    {
        $cases = [
            'about' => [route('public.about'), ['cta_heading' => 'Visit us any weekday']],
            'academics' => [route('public.academics'), ['cta_heading' => 'Start next term']],
            'admissions' => [route('public.admissions'), ['step_2_title' => 'Receive your number']],
            'online-services' => [route('online.index'), ['application_title' => 'Track my application']],
            'contact' => [route('public.contact'), ['next_heading' => 'Come and join us']],
        ];

        foreach ($cases as $key => [$url, $texts]) {
            $page = $this->page($key);
            $this->save($page, $texts, ['title' => "Edited {$key} title", 'summary' => "Edited {$key} intro"])->assertRedirect();

            $response = $this->get($url)->assertOk()->assertSee("Edited {$key} title")->assertSee("Edited {$key} intro");
            foreach ($texts as $value) {
                $response->assertSee($value);
            }
        }
    }

    public function test_defaults_name_the_school_rather_than_any_one_school(): void
    {
        $this->get(route('online.index'))->assertSee('Confirm that someone is a student of Hope Academy.');
    }

    /* ------------------------------------------------------- custom pages */

    public function test_the_school_can_create_publish_and_delete_its_own_page(): void
    {
        $this->actingAs($this->editor())->post(route('website.pages.store'), ['title' => 'School Rules'])->assertRedirect();

        $page = WebsitePage::where('key', SiteContent::CUSTOM_PREFIX.'school-rules')->firstOrFail();

        // A draft until it is published.
        $this->get(route('public.page', 'school-rules'))->assertNotFound();

        $this->actingAs($this->editor())->put(route('website.pages.update', $page), [
            'title' => 'School Rules', 'summary' => 'How we live together.', 'is_published' => 1,
            'sections' => [['heading' => 'Uniform', 'body' => 'Full uniform every day.']],
        ])->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->get(route('public.page', 'school-rules'))
            ->assertOk()
            ->assertSee('School Rules')
            ->assertSee('How we live together.')
            ->assertSee('Full uniform every day.');

        $this->actingAs($this->editor())->delete(route('website.pages.destroy', $page))->assertRedirect();
        $this->get(route('public.page', 'school-rules'))->assertNotFound();
    }

    public function test_built_in_pages_cannot_be_deleted(): void
    {
        $this->actingAs($this->editor())->delete(route('website.pages.destroy', $this->page('about')))->assertForbidden();

        $this->assertNotNull(WebsitePage::where('key', 'about')->first());
    }

    public function test_editing_the_website_needs_permission(): void
    {
        $outsider = $this->userFor($this->school, ['dashboard.view']);

        $this->actingAs($outsider)->post(route('website.pages.store'), ['title' => 'Sneaky'])->assertForbidden();
        $this->actingAs($outsider)->put(route('website.pages.update', $this->page('home')), ['title' => 'Hacked', 'texts' => ['cta_heading' => 'Hacked']])->assertForbidden();
        $this->actingAs($outsider)->put(route('website.menu.update'), ['menu' => []])->assertForbidden();
    }

    /* --------------------------------------------------------------- menu */

    protected function saveMenu(array $menu, ?string $footer = null)
    {
        return $this->actingAs($this->editor())->put(route('website.menu.update'), ['menu' => $menu, 'footer_text' => $footer]);
    }

    public function test_menu_links_can_be_renamed_reordered_hidden_and_added(): void
    {
        $this->saveMenu([
            ['label' => 'Welcome', 'target' => 'home', 'visible' => 1],
            ['label' => 'Join us', 'target' => 'admissions', 'visible' => 1],
            ['label' => 'About', 'target' => 'about', 'visible' => 0],
            ['label' => 'Ministry of Education', 'target' => 'url', 'url' => 'https://moe.gov.lr', 'visible' => 1],
        ], 'Serving Paynesville since 1998.')->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();
        $html = $this->get(route('home'))->assertOk()->getContent();

        $nav = substr($html, strpos($html, 'aria-label="Main"'), 4000);
        $this->assertTrue(strpos($nav, 'Welcome') < strpos($nav, 'Join us'), 'Links follow the saved order.');
        $this->assertStringContainsString('https://moe.gov.lr', $nav);
        $this->assertStringNotContainsString('>About<', preg_replace('/\s+/', '', $nav));
        $this->assertStringContainsString('Serving Paynesville since 1998.', $html);
    }

    public function test_a_menu_link_to_a_custom_page_follows_the_page(): void
    {
        $this->actingAs($this->editor())->post(route('website.pages.store'), ['title' => 'Transport']);
        $page = WebsitePage::where('key', SiteContent::CUSTOM_PREFIX.'transport')->firstOrFail();
        $page->update(['is_published' => true]);

        $this->saveMenu([['label' => 'Bus routes', 'target' => $page->key, 'visible' => 1]])->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();
        $this->get(route('home'))->assertSee(route('public.page', 'transport'))->assertSee('Bus routes');

        // Unpublished, it drops out of the menu rather than leaving a dead link.
        $page->update(['is_published' => false]);
        $this->get(route('home'))->assertDontSee('Bus routes');
    }

    /** A menu link must never run script in a visitor's browser. */
    public function test_unsafe_menu_addresses_are_refused(): void
    {
        foreach (['javascript:alert(1)', '//evil.example', 'data:text/html,hi'] as $bad) {
            $this->saveMenu([['label' => 'Bad', 'target' => 'url', 'url' => $bad, 'visible' => 1]])
                ->assertSessionHasErrors('menu.0.url');
        }

        $this->assertNull(SchoolSetting::where('key', SiteMenu::SETTING)->value('value'));
    }

    public function test_a_section_switched_off_stays_out_of_the_menu_whatever_it_says(): void
    {
        app(\App\Services\PublicVisibility::class)->update($this->school->id, []);

        $this->saveMenu([['label' => 'Our gallery', 'target' => 'gallery', 'visible' => 1]]);

        $this->app['auth']->forgetGuards();
        $this->get(route('home'))->assertDontSee('Our gallery');
    }

    public function test_the_menu_can_be_reset(): void
    {
        $this->saveMenu([['label' => 'Only link', 'target' => 'home', 'visible' => 1]]);
        $this->actingAs($this->editor())->delete(route('website.menu.reset'))->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->get(route('home'))->assertDontSee('Only link')->assertSee('Online services');
    }
}
