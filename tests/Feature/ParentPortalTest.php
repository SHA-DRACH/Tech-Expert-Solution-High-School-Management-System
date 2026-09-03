<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentPortalTest extends TestCase
{
    use RefreshDatabase;

    /** Create a signed-in guardian account with the given children. */
    protected function guardianWithChildren(School $school, array $names): array
    {
        $account = $this->userFor($school, []);

        $account->roles()->detach();
        $account->roles()->attach(
            Role::where('school_id', $school->id)->where('slug', 'parent-guardian')->firstOrFail()
        );

        $guardian = Guardian::factory()->create([
            'school_id' => $school->id,
            'user_id' => $account->id,
        ]);

        $children = collect($names)->map(function (string $name) use ($school, $guardian) {
            $student = Student::factory()->create(['school_id' => $school->id, 'first_name' => $name]);

            $guardian->students()->attach($student, [
                'relationship' => 'Parent',
                'is_primary' => true,
                'can_view_academics' => true,
                'can_view_finance' => true,
            ]);

            return $student;
        });

        return [$account->fresh(), $guardian, $children];
    }

    public function test_a_parent_sees_every_child_linked_to_them(): void
    {
        $school = $this->createSchool();

        [$account, , $children] = $this->guardianWithChildren($school, ['Maryportal', 'Jamesportal', 'Sarahportal']);

        $response = $this->actingAs($account)->get(route('parent.dashboard'))->assertOk();

        foreach ($children as $child) {
            $response->assertSee($child->first_name);
        }
    }

    public function test_a_parent_does_not_see_another_familys_child(): void
    {
        $school = $this->createSchool();

        [$account] = $this->guardianWithChildren($school, ['Ourchild']);

        $otherFamilysChild = Student::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Unrelatedchild',
        ]);

        $this->actingAs($account)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Ourchild')
            ->assertDontSee('Unrelatedchild');

        // Nor through the staff-facing student page.
        $this->actingAs($account)
            ->get(route('students.show', $otherFamilysChild))
            ->assertForbidden();
    }

    public function test_the_child_selector_only_accepts_a_child_the_parent_is_linked_to(): void
    {
        $school = $this->createSchool();

        [$account, , $children] = $this->guardianWithChildren($school, ['Firstchild', 'Secondchild']);

        $strangersChild = Student::factory()->create(['school_id' => $school->id, 'first_name' => 'Strangerchild']);

        // Asking for a child they are not linked to falls back to their own first child.
        $this->actingAs($account)
            ->get(route('parent.dashboard', ['child' => $strangersChild->id]))
            ->assertOk()
            ->assertDontSee('Strangerchild')
            ->assertSee($children->first()->first_name);
    }

    public function test_a_parent_can_switch_between_their_own_children(): void
    {
        $school = $this->createSchool();

        [$account, , $children] = $this->guardianWithChildren($school, ['Firstchild', 'Secondchild']);

        $second = $children->last();

        $this->actingAs($account)
            ->get(route('parent.dashboard', ['child' => $second->id]))
            ->assertOk()
            ->assertSee($second->student_number);
    }

    public function test_a_parent_cannot_open_the_fees_of_a_child_they_are_not_linked_to(): void
    {
        $school = $this->createSchool();

        [$account] = $this->guardianWithChildren($school, ['Ownchild']);

        $strangersChild = Student::factory()->create(['school_id' => $school->id, 'first_name' => 'Strangerchild']);

        // The selector silently falls back to their own child rather than
        // exposing anything belonging to another family.
        $this->actingAs($account)
            ->get(route('parent.fees', ['child' => $strangersChild->id]))
            ->assertOk()
            ->assertDontSee('Strangerchild');
    }

    public function test_a_parent_may_read_their_own_childs_record(): void
    {
        $school = $this->createSchool();

        [$account, , $children] = $this->guardianWithChildren($school, ['Ownchild']);

        $this->assertTrue($account->can('view', $children->first()));
    }

    public function test_a_parent_cannot_reach_staff_areas(): void
    {
        $school = $this->createSchool();

        [$account] = $this->guardianWithChildren($school, ['Anychild']);

        $this->actingAs($account)->get(route('students.index'))->assertForbidden();
        $this->actingAs($account)->get(route('users.index'))->assertForbidden();
        $this->actingAs($account)->get(route('admissions.index'))->assertForbidden();
    }

    public function test_a_student_account_only_sees_their_own_record(): void
    {
        $school = $this->createSchool();

        $account = $this->userFor($school, []);
        $account->roles()->detach();
        $account->roles()->attach(
            Role::where('school_id', $school->id)->where('slug', 'student')->firstOrFail()
        );

        $own = Student::factory()->create([
            'school_id' => $school->id,
            'user_id' => $account->id,
            'first_name' => 'Mystudent',
        ]);

        $classmate = Student::factory()->create(['school_id' => $school->id, 'first_name' => 'Classmatestudent']);

        $this->actingAs($account->fresh())
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($own->student_number)
            ->assertDontSee('Classmatestudent');

        $this->actingAs($account->fresh())
            ->get(route('students.show', $classmate))
            ->assertForbidden();
    }
}
