<?php

namespace Tests\Feature\Family;

use App\Models\Person;
use App\Models\PersonParentLink;
use App\Models\PersonUnion;
use App\Models\User;
use App\Services\KinshipService;
use Tests\TestCase;

class KinshipTest extends TestCase
{
    private KinshipService $kin;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kin   = app(KinshipService::class);
        $this->actor = $this->createUser(['full_name' => 'Actor', 'gender' => 'm']);
    }

    /** Quick account-less person. */
    private function p(string $name, ?string $gender = null): Person
    {
        return $this->kin->createPerson(['full_name' => $name, 'gender' => $gender], $this->actor);
    }

    private function label(Person $from, Person $to): ?string
    {
        return $this->kin->relationshipLabel($from, $to);
    }

    // ---------------------------------------------------------------------
    // Person nodes
    // ---------------------------------------------------------------------

    public function test_person_for_is_idempotent_and_links_account(): void
    {
        $u = $this->createUser(['full_name' => 'Jane', 'gender' => 'f']);
        $a = $this->kin->personFor($u);
        $b = $this->kin->personFor($u);

        $this->assertSame($a->id, $b->id);
        $this->assertSame($u->id, $a->user_id);
        $this->assertSame(1, Person::where('user_id', $u->id)->count());
    }

    public function test_person_for_normalizes_full_word_gender(): void
    {
        // Real accounts store gender inconsistently ('Male'/'Female'); the persons
        // table only accepts 'm'/'f', so personFor must normalise or the insert
        // violates the CHECK constraint. Simulate the legacy value in-memory.
        $male = $this->createUser(['full_name' => 'Mr', 'gender' => 'm']);
        $male->gender = 'Male';
        $this->assertSame('m', $this->kin->personFor($male)->gender);

        $female = $this->createUser(['full_name' => 'Ms', 'gender' => 'f']);
        $female->gender = 'Female';
        $this->assertSame('f', $this->kin->personFor($female)->gender);
    }

    public function test_account_less_child_is_auto_confirmed(): void
    {
        $dad = $this->p('Dad', 'm');
        $kid = $this->p('Kid', 'm');
        $link = $this->kin->addChild($dad, $kid, $this->actor);

        $this->assertSame('confirmed', $link->status);
    }

    public function test_person_cannot_be_own_parent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $x = $this->p('X');
        $this->kin->linkParent($x, $x, $this->actor);
    }

    // ---------------------------------------------------------------------
    // Reciprocal + vertical labels
    // ---------------------------------------------------------------------

    public function test_parent_child_labels_are_reciprocal_and_gendered(): void
    {
        $dad = $this->p('Dad', 'm');
        $son = $this->p('Son', 'm');
        $daughter = $this->p('Daughter', 'f');
        $this->kin->addChild($dad, $son, $this->actor);
        $this->kin->addChild($dad, $daughter, $this->actor);

        $this->assertSame('father', $this->label($son, $dad));
        $this->assertSame('son', $this->label($dad, $son));
        $this->assertSame('daughter', $this->label($dad, $daughter));
    }

    public function test_grandparent_and_great_grandparent(): void
    {
        $ggpa = $this->p('GreatGrandpa', 'm');
        $gpa  = $this->p('Grandpa', 'm');
        $dad  = $this->p('Dad', 'm');
        $kid  = $this->p('Kid', 'f');
        $this->kin->addChild($ggpa, $gpa, $this->actor);
        $this->kin->addChild($gpa, $dad, $this->actor);
        $this->kin->addChild($dad, $kid, $this->actor);

        $this->assertSame('grandfather', $this->label($kid, $gpa));
        $this->assertSame('granddaughter', $this->label($gpa, $kid));
        $this->assertSame('great-grandfather', $this->label($kid, $ggpa));
        $this->assertSame('great-granddaughter', $this->label($ggpa, $kid)); // kid is female
    }

    // ---------------------------------------------------------------------
    // Collateral labels
    // ---------------------------------------------------------------------

    public function test_siblings_uncle_niece_and_cousins(): void
    {
        $gpa  = $this->p('Grandpa', 'm');
        $dad  = $this->p('Dad', 'm');
        $bob  = $this->p('UncleBob', 'm');   // dad's brother
        $kid  = $this->p('Kid', 'm');
        $cous = $this->p('Cousin', 'f');     // bob's child
        $ckid = $this->p('CousinKid', 'm');  // cousin's child

        $this->kin->addChild($gpa, $dad, $this->actor);
        $this->kin->addChild($gpa, $bob, $this->actor);
        $this->kin->addChild($dad, $kid, $this->actor);
        $this->kin->addChild($bob, $cous, $this->actor);
        $this->kin->addChild($cous, $ckid, $this->actor);

        $this->assertSame('brother', $this->label($dad, $bob));
        $this->assertSame('uncle', $this->label($kid, $bob));
        $this->assertSame('nephew', $this->label($bob, $kid));
        $this->assertSame('1st cousin', $this->label($kid, $cous));
        $this->assertSame('1st cousin once removed', $this->label($kid, $ckid));
    }

    // ---------------------------------------------------------------------
    // Unions + in-laws
    // ---------------------------------------------------------------------

    public function test_spouse_labels(): void
    {
        $husband = $this->p('Husband', 'm');
        $wife    = $this->p('Wife', 'f');
        $this->kin->addSpouse($husband, $wife, $this->actor);

        $this->assertSame('wife', $this->label($husband, $wife));
        $this->assertSame('husband', $this->label($wife, $husband));
    }

    public function test_union_is_normalised_and_deduplicated(): void
    {
        $a = $this->p('A');
        $b = $this->p('B');
        $this->kin->addSpouse($a, $b, $this->actor);
        $this->kin->addSpouse($b, $a, $this->actor); // reversed order

        $this->assertSame(1, PersonUnion::count());
    }

    public function test_in_law_labels(): void
    {
        $gpa  = $this->p('Grandpa', 'm');
        $dad  = $this->p('Dad', 'm');
        $bob  = $this->p('UncleBob', 'm'); // dad's brother
        $mom  = $this->p('Mom', 'f');      // dad's wife
        $son  = $this->p('Son', 'm');
        $dau  = $this->p('Daughter', 'f');
        $dauHusband = $this->p('DauHusband', 'm');

        $this->kin->addChild($gpa, $dad, $this->actor);
        $this->kin->addChild($gpa, $bob, $this->actor);
        $this->kin->addSpouse($dad, $mom, $this->actor);
        $this->kin->addChild($dad, $son, $this->actor);
        $this->kin->addChild($dad, $dau, $this->actor);
        $this->kin->addSpouse($dau, $dauHusband, $this->actor);

        // Mom's relationships by marriage.
        $this->assertSame('father-in-law', $this->label($mom, $gpa)); // father of mom's spouse
        $this->assertSame('brother-in-law', $this->label($mom, $bob)); // brother of mom's spouse
        // Dad's daughter's husband.
        $this->assertSame('son-in-law', $this->label($dad, $dauHusband)); // spouse of dad's daughter
    }

    // ---------------------------------------------------------------------
    // Authenticity (pending → confirmed)
    // ---------------------------------------------------------------------

    public function test_claim_on_another_account_is_pending_until_confirmed(): void
    {
        $dadUser = $this->createUser(['full_name' => 'Dad', 'gender' => 'm']);
        $sonUser = $this->createUser(['full_name' => 'Son', 'gender' => 'm']);
        $dadP = $this->kin->personFor($dadUser);
        $sonP = $this->kin->personFor($sonUser);

        // Son claims Dad as parent — touches Dad's account → pending.
        $link = $this->kin->linkParent($dadP, $sonP, $sonUser);
        $this->assertSame('pending', $link->status);
        $this->assertNull($this->label($sonP, $dadP)); // not derivable while pending

        // Dad confirms.
        $this->kin->confirm($link, $dadUser);
        $this->assertSame('father', $this->label($sonP, $dadP));
    }

    // ---------------------------------------------------------------------
    // Headline scenario: two parents, ONE set of kids, no duplicates
    // ---------------------------------------------------------------------

    public function test_mother_attaches_to_existing_kids_without_duplication(): void
    {
        $dadUser = $this->createUser(['full_name' => 'Dad', 'gender' => 'm']);
        $momUser = $this->createUser(['full_name' => 'Mom', 'gender' => 'f']);

        // Dad sets up the family and adds the kids.
        $dadP = $this->kin->personFor($dadUser);
        $kidA = $this->kin->addChild($dadP, ['full_name' => 'Kid A', 'gender' => 'm'], $dadUser)->child;
        $kidB = $this->kin->addChild($dadP, ['full_name' => 'Kid B', 'gender' => 'f'], $dadUser)->child;
        $this->assertSame(3, Person::count()); // dad + 2 kids

        // Mom joins and marries into the family (dad confirms).
        $momP  = $this->kin->personFor($momUser);
        $union = $this->kin->addSpouse($momP, $dadP, $momUser);
        $this->kin->confirm($union, $dadUser);

        // Mom claims the SAME kids — no new person nodes created.
        $this->kin->addChild($momP, $kidA, $momUser);
        $this->kin->addChild($momP, $kidB, $momUser);

        $this->assertSame(4, Person::count()); // dad + mom + 2 kids — NOT 6
        $this->assertSame(2, $kidA->parentLinks()->where('status', 'confirmed')->count());
        $this->assertSame('father', $this->label($kidA, $dadP));
        $this->assertSame('mother', $this->label($kidA, $momP));
    }

    // ---------------------------------------------------------------------
    // Merge
    // ---------------------------------------------------------------------

    public function test_merge_collapses_duplicate_and_repoints_edges(): void
    {
        // Two separate trees each recorded the same grandfather.
        $keepGpa = $this->p('Grandpa', 'm');
        $dupGpa  = $this->p('Grandpa (dup)', 'm');
        $dadFromKeep = $this->p('DadA', 'm');
        $dadFromDup  = $this->p('DadB', 'm');
        $this->kin->addChild($keepGpa, $dadFromKeep, $this->actor);
        $this->kin->addChild($dupGpa, $dadFromDup, $this->actor);

        $this->kin->merge($keepGpa, $dupGpa);

        $this->assertSoftDeleted('persons', ['id' => $dupGpa->id]);
        // The duplicate's child now hangs off the surviving grandfather node.
        $this->assertSame('father', $this->label($dadFromDup->refresh(), $keepGpa));
        $this->assertSame(2, PersonParentLink::where('parent_person_id', $keepGpa->id)->count());
        $this->assertSame(0, PersonParentLink::where('parent_person_id', $dupGpa->id)->count());
    }

    public function test_merge_adopts_account_and_drops_self_edges(): void
    {
        $u = $this->createUser(['full_name' => 'Real', 'gender' => 'm']);
        $keep = $this->p('Keep', 'm');           // no account
        $dup  = $this->kin->personFor($u);       // has account

        // A shared child, recorded on both nodes → must not duplicate after merge.
        $child = $this->p('Child', 'f');
        $this->kin->addChild($keep, $child, $this->actor);
        $this->kin->addChild($dup, $child, $this->actor);

        $this->kin->merge($keep, $dup);

        $this->assertSame($u->id, $keep->refresh()->user_id); // account adopted
        $this->assertSame(1, PersonParentLink::where('child_person_id', $child->id)->count()); // deduped
    }
}
