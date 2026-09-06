<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere else to keep the video.
 *
 * ── Why this exists ─────────────────────────────────────────────────────────
 *
 * The platform is about to hold media that dwarfs everything else in it: a
 * five-minute bout filmed at 1080p is larger than the entire database. That
 * cannot live on the application server's disk, and it must not be a
 * deployment-time decision baked into config either — the box that runs a
 * competition in one hall has different storage attached to it than the box
 * that runs the next one.
 *
 * So storage is a THING YOU ATTACH, not a setting you configure once. A row
 * here is one attached place: a NAS share, a mounted volume. There can be
 * several, and there can be NONE — which is the default and a perfectly good
 * answer. With nothing attached, media lives on local disk exactly as it would
 * have anyway; attach a vault and new media goes there instead. Detach it and
 * the platform carries on, minus whatever was on that vault.
 *
 * ── The two drivers, and why the difference matters more than it looks ──────
 *
 * `mount` — the share is already mounted into the filesystem (CIFS/NFS, done
 *   once by the box's fstab). The vault can be READ IN PLACE, so a video is
 *   streamed straight off it and never occupies our disk at all. This is the
 *   one that actually delivers "don't store video on our storage".
 *
 * `smb`  — we talk SMB ourselves, one file at a time. Nothing can be read in
 *   place, so playing a video means copying the whole thing down first. Still
 *   useful (an archive vault, a NAS nobody will mount for us), but it is cold
 *   storage with a local cache, and the UI says so rather than pretending.
 *
 * ── Not secrets in the repo ────────────────────────────────────────────────
 *
 * The password is stored encrypted (`encrypted` cast on the model) and is
 * `$hidden`, so it cannot be serialised into a JSON response by accident. It is
 * write-only from the browser's side: you can replace it, never read it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_vaults', function (Blueprint $table) {
            $table->id();

            // The public handle. Vaults are referenced from admin URLs, and an
            // auto-increment id in one of those tells an onlooker how many
            // storage targets exist and in what order they were added.
            $table->uuid('uuid')->unique();

            $table->string('name');

            // mount | smb  — see the header. Kept as a string rather than an
            // enum so adding a driver (s3, sftp) is one class and no migration.
            $table->string('driver', 16);

            // ── Where it is ────────────────────────────────────────────────
            // A `mount` vault needs only mount_path. An `smb` vault needs the
            // host/share/credentials and ignores mount_path. Nullable across
            // the board because which columns are required is the DRIVER's
            // business, enforced in validation, not the table's.
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('share')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();   // encrypted at rest
            $table->string('domain')->nullable();

            // The absolute path a `mount` vault is mounted at.
            $table->string('mount_path')->nullable();

            // A subfolder inside the vault to confine ourselves to, so a share
            // that holds other things is not taken over wholesale.
            $table->string('root_path')->nullable();

            // ── How it is used ─────────────────────────────────────────────
            // Highest priority wins for NEW writes. Reads never consult this —
            // a stored file records the vault it is on and is read from there,
            // which is what makes attaching a second vault safe.
            $table->integer('priority')->default(0);

            $table->boolean('enabled')->default(true);

            // "Keep serving what is on it, put nothing new there." The state a
            // vault goes into when it is being retired, so it can be drained
            // without a window where its files are unreachable.
            $table->boolean('read_only')->default(false);

            // ── What we last saw ──────────────────────────────────────────
            // Cached so the admin page can show the truth without probing a
            // possibly-dead NAS on every render, and so a write path can decide
            // in microseconds whether to even try.
            $table->string('last_status', 12)->default('unknown'); // unknown|online|offline
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedBigInteger('free_bytes')->nullable();
            $table->unsignedBigInteger('total_bytes')->nullable();

            $table->timestamps();

            $table->index(['enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_vaults');
    }
};
