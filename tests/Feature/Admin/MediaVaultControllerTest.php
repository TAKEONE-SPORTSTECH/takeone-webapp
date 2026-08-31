<?php

namespace Tests\Feature\Admin;

use App\Jobs\MigrateMediaToVault;
use App\Models\MediaFile;
use App\Models\MediaVault;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Attaching and editing a media vault.
 *
 * The behaviour under the microscope is the credential: it is write-only, so a
 * save that does not resupply a password must leave the stored one alone, and
 * no response may ever carry it back to the browser.
 */
class MediaVaultControllerTest extends TestCase
{
    private function superAdmin(): User
    {
        $user = $this->createUser();
        $this->makeSuperAdmin($user);

        return $user;
    }

    /** A directory the MountDriver will accept: /dev/shm is a real mount point. */
    private function mountFixture(): string
    {
        return '/dev/shm';
    }

    private function mountPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hall NAS',
            'driver' => 'mount',
            'mount_path' => $this->mountFixture(),
            'root_path' => 'takeone-vault-test',
            'priority' => 10,
            'enabled' => true,
            'read_only' => false,
        ], $overrides);
    }

    private function smbPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Office NAS',
            'driver' => 'smb',
            'host' => '192.168.0.50',
            'port' => 445,
            'share' => 'media',
            'username' => 'takeone',
            'password' => 'sup3r-s3cret-nas',
            'domain' => 'WORKGROUP',
            'enabled' => true,
            'read_only' => false,
        ], $overrides);
    }

    protected function tearDown(): void
    {
        $dir = $this->mountFixture().'/takeone-vault-test';

        if (is_dir($dir)) {
            @array_map('unlink', (array) glob($dir.'/*'));
            @rmdir($dir);
        }

        parent::tearDown();
    }

    // ── attach ───────────────────────────────────────────────────────────────

    public function test_super_admin_can_attach_a_mount_vault(): void
    {
        Queue::fake();

        $res = $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->mountPayload());

        $res->assertCreated()
            ->assertJson(['success' => true])
            ->assertJsonPath('vault.name', 'Hall NAS')
            ->assertJsonPath('vault.driver', 'mount')
            ->assertJsonPath('vault.has_password', false)
            ->assertJsonStructure(['success', 'message', 'vault' => ['uuid', 'last_status', 'last_error']]);

        $this->assertArrayNotHasKey('password', $res->json('vault'));

        $vault = MediaVault::firstWhere('name', 'Hall NAS');
        $this->assertNotNull($vault);
        $this->assertSame('/dev/shm', $vault->mount_path);
        $this->assertSame('takeone-vault-test', $vault->root_path);
        $this->assertTrue($vault->enabled);
    }

    public function test_attaching_an_smb_vault_never_echoes_the_password(): void
    {
        Queue::fake();

        $res = $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->smbPayload());

        $res->assertCreated()->assertJsonPath('vault.has_password', true);

        // Not in the projection, not in the message, not anywhere in the body.
        $this->assertStringNotContainsString('sup3r-s3cret-nas', $res->getContent());
        $this->assertArrayNotHasKey('password', $res->json('vault'));

        $vault = MediaVault::firstWhere('name', 'Office NAS');
        $this->assertSame('sup3r-s3cret-nas', $vault->password);
        // Stored encrypted, per the model's cast — the raw column is not the value.
        $this->assertNotSame('sup3r-s3cret-nas', $vault->getAttributes()['password']);
    }

    // ── the credential rule ──────────────────────────────────────────────────

    public function test_updating_without_a_password_leaves_the_stored_credential_alone(): void
    {
        Queue::fake();

        $admin = $this->superAdmin();
        $this->actingAs($admin)->postJson('/admin/storage/vaults', $this->smbPayload())->assertCreated();

        $vault = MediaVault::firstWhere('name', 'Office NAS');
        $stored = $vault->getAttributes()['password'];

        // Exactly what the page sends on an edit: the password key is dropped.
        $edit = $this->smbPayload(['name' => 'Office NAS (renamed)']);
        unset($edit['password']);

        $this->actingAs($admin)
            ->putJson('/admin/storage/vaults/'.$vault->uuid, $edit)
            ->assertOk()
            ->assertJsonPath('vault.name', 'Office NAS (renamed)')
            ->assertJsonPath('vault.has_password', true);

        $fresh = $vault->fresh();
        $this->assertSame('sup3r-s3cret-nas', $fresh->password);
        $this->assertSame($stored, $fresh->getAttributes()['password']);
    }

    public function test_an_empty_password_on_update_also_leaves_it_alone(): void
    {
        Queue::fake();

        $admin = $this->superAdmin();
        $this->actingAs($admin)->postJson('/admin/storage/vaults', $this->smbPayload())->assertCreated();

        $vault = MediaVault::firstWhere('name', 'Office NAS');

        $this->actingAs($admin)
            ->putJson('/admin/storage/vaults/'.$vault->uuid, $this->smbPayload(['password' => '']))
            ->assertOk();

        $this->assertSame('sup3r-s3cret-nas', $vault->fresh()->password);
    }

    public function test_updating_with_a_new_password_replaces_it(): void
    {
        Queue::fake();

        $admin = $this->superAdmin();
        $this->actingAs($admin)->postJson('/admin/storage/vaults', $this->smbPayload())->assertCreated();

        $vault = MediaVault::firstWhere('name', 'Office NAS');

        $res = $this->actingAs($admin)
            ->putJson('/admin/storage/vaults/'.$vault->uuid, $this->smbPayload(['password' => 'a-brand-new-one']));

        $res->assertOk();
        $this->assertStringNotContainsString('a-brand-new-one', $res->getContent());
        $this->assertSame('a-brand-new-one', $vault->fresh()->password);
    }

    // ── validation ───────────────────────────────────────────────────────────

    public function test_a_relative_mount_path_is_refused(): void
    {
        Queue::fake();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->mountPayload(['mount_path' => 'mnt/nas']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mount_path');

        $this->assertSame(0, MediaVault::count());
    }

    public function test_a_hostile_host_is_refused(): void
    {
        Queue::fake();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->smbPayload(['host' => '10.0.0.1; rm -rf /']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('host');

        $this->assertSame(0, MediaVault::count());
    }

    public function test_an_unknown_driver_is_refused(): void
    {
        Queue::fake();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->mountPayload(['driver' => 'ftp']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver');

        $this->assertSame(0, MediaVault::count());
    }

    // ── authorization ────────────────────────────────────────────────────────

    public function test_a_non_super_admin_cannot_attach_a_vault(): void
    {
        Queue::fake();

        $this->actingAs($this->createUser())
            ->postJson('/admin/storage/vaults', $this->mountPayload())
            ->assertForbidden();

        $this->assertSame(0, MediaVault::count());
    }

    public function test_a_guest_cannot_attach_a_vault(): void
    {
        Queue::fake();

        $this->postJson('/admin/storage/vaults', $this->mountPayload())->assertUnauthorized();

        $this->assertSame(0, MediaVault::count());
    }

    // ── migration hand-off ───────────────────────────────────────────────────

    public function test_a_disabled_vault_does_not_start_a_migration(): void
    {
        Queue::fake();

        $this->pendingMedia();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->mountPayload(['enabled' => false]))
            ->assertCreated();

        Queue::assertNotPushed(MigrateMediaToVault::class);
    }

    public function test_a_read_only_vault_does_not_start_a_migration(): void
    {
        Queue::fake();

        $this->pendingMedia();

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->mountPayload(['read_only' => true]))
            ->assertCreated();

        Queue::assertNotPushed(MigrateMediaToVault::class);
    }

    public function test_a_usable_vault_with_pending_media_starts_a_migration(): void
    {
        Queue::fake();

        $this->pendingMedia();

        $res = $this->actingAs($this->superAdmin())
            ->postJson('/admin/storage/vaults', $this->mountPayload())
            ->assertCreated();

        if ($res->json('vault.last_status') !== 'online') {
            $this->markTestSkipped('No writable mount point available in this environment to probe.');
        }

        Queue::assertPushed(MigrateMediaToVault::class);
    }

    private function pendingMedia(): MediaFile
    {
        return MediaFile::create([
            'vault_id' => null,
            'kind' => MediaFile::KIND_CLIP,
            'rel_path' => 'events/x/matches/1/clips/a.mp4',
            'mime' => 'video/mp4',
            'bytes' => 1024,
            'status' => MediaFile::STATUS_READY,
        ]);
    }
}
