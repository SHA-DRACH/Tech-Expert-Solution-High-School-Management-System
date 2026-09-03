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
}
