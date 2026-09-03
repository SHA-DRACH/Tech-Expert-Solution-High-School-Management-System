<?php

namespace Tests\Feature;

use App\Http\Middleware\DiscardUnusableRememberCookie;
use App\Models\School;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A "keep me signed in" cookie that no longer identifies anybody.
 *
 * `SessionGuard::userFromRecaller()` finishes with
 *
 *     hash_equals($this->hashPasswordForCookie($userPassword), $recallerHash)
 *         || hash_equals($userPassword, $recallerHash)
 *
 * and `$userPassword` is null whenever `retrieveByToken()` matched nobody, so
 * the second call raises a TypeError and the request dies with a 500 before
 * reaching any controller.
 *
 * `remember_token` is null on a freshly seeded user, so re-seeding, restoring a
 * backup, or recreating an account all leave real browsers holding a cookie
 * that resolves to nothing. The failure was total: every request 500'd,
 * **including `/logout`**, so the person could not even sign out to clear the
 * cookie that was breaking them.
 *
 * These tests drive the guard directly rather than going through `$this->get()`.
 * That is deliberate: an HTTP-level test of this happened to return 200 whether
 * the protection was present or not, so it proved nothing. Asserting against
 * the guard is what actually fails when the middleware is removed.
 */
class StaleRememberCookieTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function guard()
    {
        return Auth::guard('web');
    }

    /** The cookie Laravel writes: id|token|hashOfThePasswordHash. */
    protected function recallerFor(User $user, string $token): string
    {
        return $user->getAuthIdentifier().'|'.$token.'|'
            .$this->guard()->hashPasswordForCookie($user->getAuthPassword());
    }

    /** A request carrying a recaller cookie, as the browser would send it. */
    protected function requestWith(string $cookie): Request
    {
        return Request::create('/', 'GET', [], [$this->guard()->getRecallerName() => $cookie]);
    }

    /** Run the middleware over a request and hand back what it passed on. */
    protected function throughMiddleware(Request $request): Request
    {
        app(DiscardUnusableRememberCookie::class)->handle($request, function (Request $passed) {
            return new Response();
        });

        return $request;
    }

    protected function staleUser(): User
    {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'active',
            'remember_token' => 'the-token-the-browser-still-has',
        ]);

        // Exactly what a re-seed or a restored backup leaves behind: the row is
        // there, the cookie is there, and the token that joined them is gone.
        $user->forceFill(['remember_token' => null])->save();

        return $user;
    }

    /* ------------------------------------------------- the bug being fixed */

    public function test_the_unprotected_guard_really_does_throw_on_a_stale_cookie(): void
    {
        $user = $this->staleUser();

        $this->guard()->setRequest($this->requestWith(
            $this->recallerFor($user, 'the-token-the-browser-still-has')
        ));

        // Characterising the framework behaviour the middleware exists for. If
        // this ever stops throwing, the middleware can go.
        $this->expectException(\TypeError::class);

        $this->guard()->user();
    }

    /* ----------------------------------------------------------- the fix */

    public function test_a_cookie_whose_token_no_longer_matches_is_discarded(): void
    {
        $user = $this->staleUser();

        $request = $this->throughMiddleware($this->requestWith(
            $this->recallerFor($user, 'the-token-the-browser-still-has')
        ));

        $this->assertNull(
            $request->cookies->get($this->guard()->getRecallerName()),
            'The unusable cookie must be gone from this request, not only from the browser.',
        );

        // And the guard is now safe to ask.
        $this->guard()->setRequest($request);

        $this->assertNull($this->guard()->user());
    }

    public function test_a_cookie_for_a_deleted_account_is_discarded(): void
    {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'active',
            'remember_token' => 'still-in-the-browser',
        ]);

        $cookie = $this->recallerFor($user, 'still-in-the-browser');

        $user->forceDelete();

        $request = $this->throughMiddleware($this->requestWith($cookie));

        $this->assertNull($request->cookies->get($this->guard()->getRecallerName()));

        $this->guard()->setRequest($request);

        $this->assertNull($this->guard()->user());
    }

    public function test_a_malformed_cookie_is_discarded(): void
    {
        $request = $this->throughMiddleware($this->requestWith('not-a-recaller-at-all'));

        $this->assertNull($request->cookies->get($this->guard()->getRecallerName()));
    }

    public function test_the_browser_is_told_to_forget_it(): void
    {
        $user = $this->staleUser();

        $this->throughMiddleware($this->requestWith(
            $this->recallerFor($user, 'the-token-the-browser-still-has')
        ));

        // Otherwise the same broken cookie comes back on the next request and
        // the person is protected but never actually fixed.
        // getName(), not firstWhere('name'): a Symfony Cookie keeps its name
        // private, so a property lookup silently finds nothing and the
        // assertion passes on a null it should have caught.
        $queued = collect(\Illuminate\Support\Facades\Cookie::getQueuedCookies())
            ->first(fn ($cookie) => $cookie->getName() === $this->guard()->getRecallerName());

        $this->assertNotNull($queued, 'A forget-cookie must be queued for the browser.');

        // Cookie::forget() queues a null value with an expiry in the past.
        $this->assertNull($queued->getValue());
        $this->assertLessThan(time(), $queued->getExpiresTime());
    }

    public function test_the_middleware_is_actually_registered_on_the_web_group(): void
    {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);

        $property = (new \ReflectionClass($kernel))->getProperty('middlewareGroups');
        $property->setAccessible(true);

        $web = $property->getValue($kernel)['web'];

        $position = array_search(DiscardUnusableRememberCookie::class, $web, true);

        $this->assertNotFalse($position, 'The protection is worthless if it is not in the pipeline.');

        /*
         | Order is the whole thing. Before EncryptCookies it would read the
         | still-encrypted cookie, fail to parse it, and throw away every "keep
         | me signed in" cookie in the school. After ResolveSchoolContext - the
         | first middleware to ask who the user is - the guard would already
         | have thrown.
         */
        $this->assertGreaterThan(
            array_search(\Illuminate\Cookie\Middleware\EncryptCookies::class, $web, true),
            $position,
            'Must run after EncryptCookies, or it reads an encrypted value and discards every cookie.',
        );

        $this->assertLessThan(
            array_search(\App\Http\Middleware\ResolveSchoolContext::class, $web, true),
            $position,
            'Must run before anything resolves the user, or the guard throws first.',
        );
    }

    /* -------------------------------------------------------- the guard rail */

    public function test_a_usable_cookie_is_left_completely_alone(): void
    {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'active',
            'remember_token' => 'a-token-that-matches',
        ]);

        $cookie = $this->recallerFor($user, 'a-token-that-matches');

        $request = $this->throughMiddleware($this->requestWith($cookie));

        /*
         | Discarding an unusable cookie must not become discarding every
         | cookie. That would silently disable "keep me signed in" for the whole
         | school - a quieter failure than the 500, and therefore a worse one.
         */
        $this->assertSame($cookie, $request->cookies->get($this->guard()->getRecallerName()));

        $this->guard()->setRequest($request);

        $this->assertTrue($user->is($this->guard()->user()));
    }

    public function test_a_request_with_no_remember_cookie_is_untouched(): void
    {
        $request = Request::create('/', 'GET');

        $this->throughMiddleware($request);

        $this->assertNull($request->cookies->get($this->guard()->getRecallerName()));
        $this->assertEmpty(\Illuminate\Support\Facades\Cookie::getQueuedCookies());
    }

    /* --------------------------------------------------------- end to end */

    public function test_logout_works_for_a_signed_in_user_carrying_a_stale_cookie(): void
    {
        $user = $this->staleUser();

        /*
         | The part that mattered most. A 500 on every request is bad; a 500 on
         | logout is a trap, because clearing the cookie is the one thing that
         | would have fixed it and signing out is how a person does that.
         */
        $this->actingAs($user)
            ->withCookie(
                $this->guard()->getRecallerName(),
                $this->recallerFor($user, 'the-token-the-browser-still-has'),
            )
            ->post(route('logout'))
            ->assertRedirect();

        $this->assertGuest();
    }
}
