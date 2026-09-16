<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\User;

/**
 * Every parent page, for a parent with more than one child.
 *
 * Laravel only refuses a lazy-loaded relation when the model came from a list
 * of several. Every earlier parent test used a single child, so a page that
 * forgot to load the child's class passed them all - and then returned a 500
 * to a real parent with three children on /parent/assignments.
 */
class ParentWithSeveralChildrenTest extends PeriodGradingTestCase
{
    public function test_every_parent_page_opens_for_each_of_several_children(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);

        $guardian = Guardian::create([
            'school_id' => $this->school->id, 'user_id' => $user->id,
            'first_name' => 'John', 'last_name' => 'Doe', 'phone' => '+231770000000',
        ]);

        $children = collect([
            $this->student('S-1', 'Mary'),
            $this->student('S-2', 'James'),
            $this->student('S-3', 'Sarah'),
        ]);

        foreach ($children as $child) {
            $guardian->students()->attach($child->id, [
                'relationship' => 'Father', 'can_view_academics' => true, 'can_view_finance' => true,
            ]);
        }

        $pages = [
            'parent.dashboard', 'parent.grades', 'parent.assignments', 'parent.attendance',
            'parent.fees', 'parent.teachers', 'parent.requests', 'parent.schedule',
        ];

        foreach ($children as $child) {
            foreach ($pages as $page) {
                $this->actingAs($user)
                    ->get(route($page, ['child' => $child->id]))
                    ->assertOk();
            }

            $this->actingAs($user)
                ->get(route('parent.schedule.download', ['child' => $child->id]))
                ->assertOk();
        }
    }
}
