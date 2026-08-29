<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phones filming a mat, and what they filmed.
 *
 * A camera is not a hall screen and not a scoring console: it renders nothing,
 * it is held or clamped beside the mat, and the only thing it is entitled to do
 * is start and stop when the mat tells it to. So it gets its own fleet rather
 * than a fourth `surface` on a screen row — and one that is SPORT-NEUTRAL,
 * because pointing a lens at a bout is the same act in every sport. (Screens
 * are per-sport because what they DRAW is per-sport; a camera draws nothing.)
 *
 * Two tables, and the split is deliberate:
 *
 *   event_cameras       — the device. One row per phone, its token, which mat
 *                         and angle it is assigned to, and the last thing it
 *                         told us about itself (storage, battery, whether it is
 *                         rolling).
 *   event_camera_clips  — what it recorded. One row per bout per camera, naming
 *                         the bout and the file AS THE PHONE KNOWS IT. The
 *                         media itself never comes here; this is the index that
 *                         says which phone holds which bout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_cameras', function (Blueprint $table) {
            $table->id();

            // Null until an organiser claims it — a fresh phone belongs to
            // nobody and can be told nothing.
            $table->foreignId('event_id')->nullable()->constrained('club_events')->nullOnDelete();
            $table->string('court')->nullable();

            // 1..4. Which lens this is on the mat, so a bout filmed from three
            // angles yields three clips that can be told apart later. The cap
            // is enforced in code (CameraFleet::MAX_PER_COURT) rather than by a
            // constraint, because "four live cameras" is a rule about
            // unrevoked rows, not about every row that ever existed.
            $table->unsignedTinyInteger('angle')->nullable();

            // Same contract as a screen's token: hashed at rest, plaintext
            // handed to the device exactly once, revocable, scoped to one mat.
            $table->string('token_hash')->unique();
            $table->string('token_hint')->nullable();
            $table->string('pairing_code')->nullable()->unique();

            // What the operator calls it ("tripod left"), and what the phone
            // says it is. Both untrusted strings, shown only to organisers.
            $table->string('label')->nullable();
            $table->string('device_name')->nullable();
            $table->string('app_version')->nullable();

            // The last telemetry beat. Storage is the one that matters: a phone
            // that fills up stops being a camera silently, and the organiser
            // needs to know BEFORE the final rather than after it.
            $table->unsignedBigInteger('storage_total_bytes')->nullable();
            $table->unsignedBigInteger('storage_free_bytes')->nullable();
            $table->unsignedTinyInteger('battery_percent')->nullable();
            $table->boolean('recording')->default(false);
            $table->foreignId('recording_match_id')->nullable()->constrained('event_matches')->nullOnDelete();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'court']);
        });

        Schema::create('event_camera_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->constrained('event_cameras')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // Null is legal: a camera told to roll with nothing loaded still
            // produced a file, and losing the record of it would be worse than
            // holding one that names no bout.
            $table->foreignId('match_id')->nullable()->constrained('event_matches')->nullOnDelete();
            $table->string('court')->nullable();
            $table->unsignedTinyInteger('angle')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();

            // The phone's OWN handle on the file — a name it can find again,
            // never a path this server can read. The media stays on the device
            // until somebody deliberately moves it.
            $table->string('local_ref')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'court']);
            $table->index(['match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_camera_clips');
        Schema::dropIfExists('event_cameras');
    }
};
