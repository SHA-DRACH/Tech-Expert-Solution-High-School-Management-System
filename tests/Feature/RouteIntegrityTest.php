<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Guards against a class of bug this project has already hit twice.
 *
 * Registering two routes on the same method and URI does not raise an error:
 * Laravel keeps the last one and silently discards the earlier route *and its
 * name*, so `route('public.academics')` starts throwing "not defined" somewhere
 * unrelated. These tests make that failure loud and immediate.
 */
class RouteIntegrityTest extends TestCase
{
    public function test_no_two_routes_share_a_method_and_uri(): void
    {
        $seen = [];
        $clashes = [];

        foreach (Router::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $key = $method.' /'.$route->uri();

                if (isset($seen[$key])) {
                    $clashes[] = $key.'  ('.$seen[$key].' vs '.$this->describe($route).')';

                    continue;
                }

                $seen[$key] = $this->describe($route);
            }
        }

        $this->assertSame([], $clashes, "Two routes share a method and URI, so one has been silently discarded:\n".implode("\n", $clashes));
    }

    /**
     * A duplicate *name* is quieter still than a duplicate URI.
     *
     * Both routes keep working when you visit them, so nothing 404s and the
     * URI test above stays green; only `route('the.name')` moves, silently, to
     * whichever was registered last. The symptom then surfaces far from the
     * cause - a public QR-code route took over the name of an unrelated staff
     * action, and the failure appeared in the document tests.
     */
    public function test_no_two_routes_share_a_name(): void
    {
        $seen = [];
        $clashes = [];

        foreach (Router::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            if (isset($seen[$name])) {
                $clashes[] = $name.'  ('.$seen[$name].' vs '.$this->action($route).')';

                continue;
            }

            $seen[$name] = $this->action($route);
        }

        $this->assertSame([], $clashes, "Two routes share a name, so route() silently points at only one of them:\n".implode("\n", $clashes));
    }

    public function test_every_named_route_can_be_resolved_by_name(): void
    {
        $missing = [];

        foreach (Router::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            if (Router::getRoutes()->getByName($name) === null) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'These route names no longer resolve: '.implode(', ', $missing));
    }

    protected function describe(Route $route): string
    {
        return $route->getName() ?? $route->getActionName();
    }

    /** Method and URI, for naming the two sides of a name clash. */
    protected function action(Route $route): string
    {
        return implode('|', array_diff($route->methods(), ['HEAD', 'OPTIONS'])).' /'.$route->uri();
    }
}
