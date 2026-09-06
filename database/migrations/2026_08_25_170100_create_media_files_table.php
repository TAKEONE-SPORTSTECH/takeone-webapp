<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per stored file, and the only thing that knows WHERE it is.
 *
 * ── Why a table, rather than a path column on each owner ────────────────────
 *
 * Because storage is attachable. The moment a NAS can be added and removed, the
 * questions that matter are "what is on that vault?", "what is still on local
 * disk?", "what did we lose when that share went away?" — and none of those can
 * be answered by a `video_path` string scattered across half a dozen tables.
 * They are answered by a WHERE clause here.
 *
 * It also means an owner never learns about storage. A bout's recording row
 * points at a media file; whether those bytes are on a mounted NAS, an SMB
 * archive or this server's own disk is settled in one place.
 *
 * ── The columns that carry the design ──────────────────────────────────────
 *
 * `vault_id` NULL means LOCAL DISK, and that is the default state of the whole
 * platform — not a fallback, not an error. Nothing attached, everything local.
 *
 * `rel_path` is relative to whatever root the vault (or local disk) has, never
 * absolute. So a file's identity survives the share being remounted somewhere
 * else, and no stored value is ever a path an attacker could aim.
 *
 * `hls_rel_path` is the derived streaming rendition, and it is deliberately
 * separate: the source is precious and belongs on the vault, while the ladder is
 * regenerable and stays local, because serving six-second segments over SMB is
 * how you make a hall watch a spinner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();

            // The handle every URL uses. Media is served by uuid, never by id:
            // a numeric key in a video URL is an invitation to walk the library.
            $table->uuid('uuid')->unique();

            // NULL = local disk. Set = that vault. `nullOnDelete` is the whole
            // detach story: pulling a vault out leaves its rows behind pointing
            // at local disk, where a repair or re-attach can find them, rather
            // than silently deleting the record that a video ever existed.
            $table->foreignId('vault_id')->nullable()->constrained('media_vaults')->nullOnDelete();

            // What owns these bytes — a camera clip, a bout recording, a
            // thumbnail. Nullable so a file can exist before it is claimed
            // (an upload that is still arriving belongs to nobody yet).
            $table->nullableMorphs('owner');

            // clip | recording | thumb | poster | other
            $table->string('kind', 16)->default('other');

            $table->string('rel_path');
            $table->string('hls_rel_path')->nullable();

            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();

            // Probed once, at ingest. Cheap to keep, and every listing wants it.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // stored     — the bytes are where rel_path says
            // uploading  — still arriving
            // processing — transcoding to HLS
            // ready      — transcoded and streamable
            // missing    — looked for and not found (a detached vault, a deleted share)
            // failed     — ingest or transcode gave up
            $table->string('status', 12)->default('stored');
            $table->string('error')->nullable();

            // sha256 of the source, when we had a moment to compute it. The only
            // way to tell "the same bout, uploaded twice" from "two bouts".
            $table->string('checksum', 64)->nullable();

            $table->json('meta')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vault_id', 'status']);
            $table->index(['kind', 'created_at']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
