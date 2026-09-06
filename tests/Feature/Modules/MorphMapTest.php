<?php

namespace Tests\Feature\Modules;

use App\Members\Models\User;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The morph map is what lets a model move between module folders without
 * orphaning history.
 *
 * Without it, a polymorphic `*_type` column stores the fully-qualified class
 * name, welding the database to the PHP namespace: move the class and every
 * historical row points at nothing. No error is raised — the history just stops
 * resolving — which is exactly the kind of breakage that is discovered months
 * later.
 *
 * These tests hold both halves honest: that the aliases are wired, and that code
 * writes them through getMorphClass() rather than hand-writing a class name into
 * a mapped column. That second mistake is silent and has already shipped once:
 * a vouch was inserted with the class name while the relation that counts vouches
 * queried the alias, so the record could never reach its verification threshold.
 */
class MorphMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_mapped_class_exists_and_is_a_model(): void
    {
        foreach (MorphMap::map() as $alias => $class) {
            $this->assertTrue(class_exists($class), "Morph alias '{$alias}' points at a missing class {$class}.");
            $this->assertTrue(is_subclass_of($class, Model::class), "Morph alias '{$alias}' points at {$class}, which is not an Eloquent model.");
        }
    }

    public function test_aliases_are_unique_and_slug_shaped(): void
    {
        $map = MorphMap::map();

        $this->assertSame(
            count($map),
            count(array_unique(array_values($map))),
            'Two morph aliases point at the same class, so a stored value is ambiguous.'
        );

        foreach (array_keys($map) as $alias) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $alias, "Morph alias '{$alias}' is not snake_case.");
            $this->assertStringNotContainsString('\\', $alias, "Morph alias '{$alias}' looks like a class name — the whole point is that it is not one.");
        }
    }

    /** The alias is what a model reports, which is what lands in the column. */
    public function test_a_mapped_model_reports_its_alias_not_its_class_name(): void
    {
        foreach (MorphMap::map() as $alias => $class) {
            $this->assertSame(
                $alias,
                (new $class)->getMorphClass(),
                "{$class} does not report its mapped alias — the map is not registered, or something overrides getMorphClass()."
            );
        }
    }

    /** A stored alias resolves back to the model, in both directions. */
    public function test_every_alias_round_trips(): void
    {
        foreach (MorphMap::map() as $alias => $class) {
            $this->assertSame(
                $class,
                \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($alias),
                "Stored value '{$alias}' does not resolve back to {$class}."
            );
        }
    }

    /**
     * A real write through a real morph column, end to end. Sanctum's
     * `tokenable` is the highest-stakes one on the platform: get this wrong and
     * a deleted user's API tokens are left behind, because the cleanup query
     * looks for a value that is no longer what gets stored.
     */
    public function test_a_sanctum_token_stores_the_alias_and_the_user_cleanup_finds_it(): void
    {
        $user = User::factory()->create();
        $user->createToken('test');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => 'user',
            'tokenable_id' => $user->id,
        ]);

        $user->delete();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    /**
     * Static guard for the silent mistake: a class name hand-written into a
     * column the morph map governs.
     *
     * Only the genuine morphTo columns are checked. `user_notifications.subject_type`
     * is deliberately excluded — it is a free-form tag column (it also holds
     * 'post', 'order', 'class', 'product'), read back with the same literal it
     * was written with, and is not a polymorphic relation.
     */
    public function test_no_source_file_writes_a_class_name_into_a_mapped_morph_column(): void
    {
        $morphColumns = ['vouchable_type', 'tokenable_type', 'owner_type'];

        $offenders = [];

        foreach ($this->phpFilesUnder(app_path()) as $file) {
            $source = file_get_contents($file);

            foreach ($morphColumns as $column) {
                if (preg_match("/'{$column}'\s*(=>|,)\s*[^)\n]*::class/", $source, $m)) {
                    $offenders[] = str_replace(base_path().'/', '', $file).' — '.trim($m[0]);
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['A class name is being written into a morph column. Use $model->getMorphClass() instead:'],
            $offenders,
        )));
    }

    /** @return iterable<string> */
    private function phpFilesUnder(string $dir): iterable
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
