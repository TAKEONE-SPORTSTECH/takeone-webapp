<?php

namespace Tests\Feature\Family;

use App\Models\Person;
use App\Models\PersonParentLink;
use App\Models\UserRelationship;
use App\Services\FamilyService;
use App\Services\KinshipService;
use Tests\TestCase;

class FamilyTreeControllerTest extends TestCase
{
    private KinshipService $kin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kin = app(KinshipService::class);
    }

    public function test_family_page_requires_auth(): void
    {
        $this->get('/me/family')->assertRedirect('/login');
    }

    public function test_page_loads_and_seeds_root_person(): void
    {
        $user = $this->createUser(['full_name' => 'Root', 'gender' => 'm']);

        $this->actingAs($user)->get('/me/family')->assertOk();

        // Visiting the page lazily created the viewer's own tree node.
        $this->assertDatabaseHas('persons', ['user_id' => $user->id]);
    }

    public function test_mobile_view_renders_through_its_shell(): void
    {
        $user = $this->createUser(['full_name' => 'Root', 'gender' => 'f']);

        $this->actingAs($user)
             ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile'])
             ->get('/me/family')
             ->assertOk()
             ->assertSee('id="ft-viewport"', false)   // the tree canvas mounted
             ->assertSee('FamilyTree.mount', false);   // the renderer bootstrapped
    }

    public function test_data_endpoint_returns_neighborhood_with_labels(): void
    {
        $user = $this->createUser(['full_name' => 'Me', 'gender' => 'm']);
        $me   = $this->kin->personFor($user);
        $dad  = $this->kin->addParent($me, ['full_name' => 'Dad', 'gender' => 'm'], $user)->parent;
        $this->kin->addChild($me, ['full_name' => 'Kid', 'gender' => 'f'], $user);

        $res = $this->actingAs($user)->getJson('/me/family/data')->assertOk();

        $res->assertJsonPath('root', $me->id)
            ->assertJsonPath('focus', $me->id);

        $names = collect($res->json('nodes'))->pluck('label', 'name');
        $this->assertSame('you', $names['Me']);
        $this->assertSame('father', $names['Dad']);
        $this->assertSame('daughter', $names['Kid']);

        // Avatar key is present (null when no photo) and internal fields are stripped.
        $node = collect($res->json('nodes'))->firstWhere('name', 'Dad');
        $this->assertArrayHasKey('avatar', $node);
        $this->assertArrayNotHasKey('user_photo', $node);
    }

    public function test_cannot_focus_a_stranger(): void
    {
        $user     = $this->createUser(['full_name' => 'Me']);
        $stranger = $this->kin->createPerson(['full_name' => 'Stranger'], $this->createUser());

        $this->actingAs($user)
             ->getJson('/me/family/data?focus=' . $stranger->id)
             ->assertStatus(404);
    }

    public function test_add_relative_creates_new_person_and_edge(): void
    {
        $user = $this->createUser(['full_name' => 'Me', 'gender' => 'm']);
        $me   = $this->kin->personFor($user);

        $this->actingAs($user)->postJson('/me/family/relative', [
            'focus_person_id' => $me->id,
            'type'            => 'child',
            'full_name'       => 'New Kid',
            'gender'          => 'm',
            'birth_date'      => '2015-01-01',
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('status', 'confirmed');

        $kid = Person::where('full_name', 'New Kid')->first();
        $this->assertNotNull($kid);
        $this->assertDatabaseHas('person_parent_links', [
            'parent_person_id' => $me->id,
            'child_person_id'  => $kid->id,
            'status'           => 'confirmed',
        ]);
    }

    public function test_add_relative_validates_name(): void
    {
        $user = $this->createUser();
        $me   = $this->kin->personFor($user);

        $this->actingAs($user)->postJson('/me/family/relative', [
            'focus_person_id' => $me->id,
            'type'            => 'child',
        ])->assertStatus(422)->assertJsonValidationErrors(['full_name']);
    }

    public function test_cannot_add_relative_to_stranger_focus(): void
    {
        $user     = $this->createUser();
        $stranger = $this->kin->createPerson(['full_name' => 'Stranger'], $this->createUser());

        $this->actingAs($user)->postJson('/me/family/relative', [
            'focus_person_id' => $stranger->id,
            'type'            => 'child',
            'full_name'       => 'X',
        ])->assertStatus(403);
    }

    public function test_registered_dependent_appears_in_the_tree(): void
    {
        $guardian = $this->createUser(['full_name' => 'Dad', 'gender' => 'm']);
        app(FamilyService::class)->createDependent($guardian, [
            'full_name'         => 'Registered Kid',
            'gender'            => 'Male',
            'birthdate'         => '2015-01-01',
            'nationality'       => 'BHR',
            'relationship_type' => 'son',
        ]);

        // Loading the tree syncs guardianship → the kid shows up as a "son".
        $nodes = $this->actingAs($guardian)->getJson('/me/family/data')->assertOk()->json('nodes');
        $kid = collect($nodes)->firstWhere('name', 'Registered Kid');

        $this->assertNotNull($kid);
        $this->assertSame('son', $kid['label']);
    }

    public function test_adding_child_in_tree_registers_a_real_dependent(): void
    {
        $user = $this->createUser(['full_name' => 'Me', 'gender' => 'm', 'nationality' => 'BHR']);
        $me   = app(KinshipService::class)->personFor($user);

        $this->actingAs($user)->postJson('/me/family/relative', [
            'focus_person_id' => $me->id,
            'type'            => 'child',
            'full_name'       => 'Zaid',
            'gender'          => 'm',
        ])->assertOk()->assertJsonPath('success', true);

        // It became a real family member (User + guardianship), not just a node.
        $kid = \App\Models\User::where('full_name', 'Zaid')->first();
        $this->assertNotNull($kid);
        $this->assertDatabaseHas('user_relationships', [
            'guardian_user_id'  => $user->id,
            'dependent_user_id' => $kid->id,
            'relationship_type' => 'son',
        ]);
        // …and it's wired into the tree.
        $this->assertDatabaseHas('person_parent_links', [
            'parent_person_id' => $me->id,
            'status'           => 'confirmed',
        ]);
        $this->assertDatabaseHas('persons', ['user_id' => $kid->id]);
    }

    public function test_pending_request_can_be_confirmed_by_counterpart(): void
    {
        $dadUser = $this->createUser(['full_name' => 'Dad', 'gender' => 'm']);
        $sonUser = $this->createUser(['full_name' => 'Son', 'gender' => 'm']);
        $dadP = $this->kin->personFor($dadUser);
        $sonP = $this->kin->personFor($sonUser);

        // Son claims Dad → pending (touches Dad's account).
        $link = $this->kin->linkParent($dadP, $sonP, $sonUser);
        $this->assertSame('pending', $link->status);

        // The requester (son) cannot confirm his own request.
        $this->actingAs($sonUser)->postJson('/me/family/respond', [
            'edge_type' => 'parent', 'edge_id' => $link->id, 'action' => 'confirm',
        ])->assertStatus(403);

        // Dad (counterpart) can.
        $this->actingAs($dadUser)->postJson('/me/family/respond', [
            'edge_type' => 'parent', 'edge_id' => $link->id, 'action' => 'confirm',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('person_parent_links', ['id' => $link->id, 'status' => 'confirmed']);
    }

    public function test_data_flags_answerable_pending_edges(): void
    {
        $dadUser = $this->createUser(['full_name' => 'Dad', 'gender' => 'm']);
        $sonUser = $this->createUser(['full_name' => 'Son', 'gender' => 'm']);
        $dadP = $this->kin->personFor($dadUser);
        $sonP = $this->kin->personFor($sonUser);
        $link = $this->kin->linkParent($dadP, $sonP, $sonUser); // son requested

        // From Dad's view the pending edge is answerable; from Son's it is not.
        $dadEdge = collect($this->actingAs($dadUser)->getJson('/me/family/data')->json('parentEdges'))
            ->firstWhere('id', $link->id);
        $this->assertTrue($dadEdge['can_respond']);

        $sonEdge = collect($this->actingAs($sonUser)->getJson('/me/family/data')->json('parentEdges'))
            ->firstWhere('id', $link->id);
        $this->assertFalse($sonEdge['can_respond']);
    }
}
