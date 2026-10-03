<?php

namespace Tests\Feature\Federations;

use App\Federations\Models\Federation;
use App\Federations\Services\FederationDirectory;
use App\Parties\Authority;
use App\Parties\Backfill;
use App\Parties\Shadow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PLAN.md Phase 4, from the federation module's side: App\Parties\Authority
 * must land every person on the same body, with the same manager rights, as
 * FederationDirectory::access() does today — and shadow mode records nothing
 * while they agree.
 */
class FederationAccessShadowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authority_lands_everyone_where_the_directory_does(): void
    {
        $this->seedRoles();
        Shadow::flush();

        $head = $this->createUser();
        $subHead = $this->createUser();
        $officer = $this->createUser();
        $scoped = $this->createUser();
        $stranger = $this->createUser();
        $super = $this->createUser();
        $this->makeSuperAdmin($super);

        $fed = Federation::create(['owner_user_id' => $head->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);
        Federation::create(['owner_user_id' => $subHead->id, 'parent_id' => $fed->id, 'name' => 'Sub', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);
        Federation::create(['owner_user_id' => $stranger->id, 'name' => 'Pending', 'country' => 'BH', 'sport' => 'karate', 'status' => 'pending']);
        DB::table('federation_staff')->insert([
            ['federation_id' => $fed->id, 'user_id' => $officer->id, 'role' => 'Secretary', 'sports' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['federation_id' => $fed->id, 'user_id' => $scoped->id, 'role' => 'Karate desk', 'sports' => json_encode(['Karate']), 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(Backfill::class)->run(true);
        config(['parties.shadow' => true]);

        foreach ([$head, $subHead, $officer, $scoped, $stranger, $super] as $u) {
            $old = app(FederationDirectory::class)->access($u);   // also runs the shadow comparison
            $new = app(Authority::class)->federationAccess($u);

            $this->assertSame($old === null, $new === null, "presence differs for user {$u->id}");
            if ($old) {
                $this->assertSame($old['root']->id, $new['root'], "root differs for user {$u->id}");
                $this->assertSame($old['manager'], $new['manager'], "manager differs for user {$u->id}");
            }
        }

        $this->assertSame(0, DB::table('party_shadow_disagreements')->count());
    }
}
