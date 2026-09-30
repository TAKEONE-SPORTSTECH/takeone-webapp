<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 1.1 — additive on top of the 1.0 tables.
 *
 *  · Templates belong to an OWNER (a school, a club, one event …). One template per
 *    (owner, type) instead of one per type for the whole application. The owner is a
 *    plain (type, id) pair the host decides; ('', 0) is the application-wide template,
 *    which is exactly what every 1.0 template was, so existing rows keep working.
 *  · A template chooses its BACKGROUND: a built-in (plain, guilloche, watermark, motif),
 *    uploaded artwork (the FileStore slot "background"), or blank for pre-printed paper,
 *    plus a print offset in millimetres for that paper.
 *  · Both tables get a random uuid, so a host can address them in URLs without the
 *    auto-increment id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('owner_type', 60)->default('')->after('uuid');
            $table->unsignedBigInteger('owner_id')->default(0)->after('owner_type');
            $table->string('background', 16)->default('plain')->after('show_photo');
            $table->string('accent', 7)->nullable()->after('background');
            $table->smallInteger('print_offset_x')->default(0)->after('accent');
            $table->smallInteger('print_offset_y')->default(0)->after('print_offset_x');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        foreach (['certificate_templates', 'certificates'] as $name) {
            DB::table($name)->whereNull('uuid')->orderBy('id')->pluck('id')
                ->each(fn ($id) => DB::table($name)->where('id', $id)->update(['uuid' => (string) Str::uuid()]));
        }

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->unique('uuid');
            $table->dropUnique(['type']);
            $table->unique(['owner_type', 'owner_id', 'type'], 'certificate_templates_owner_type_unique');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropUnique('certificate_templates_owner_type_unique');
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'owner_type', 'owner_id', 'background', 'accent', 'print_offset_x', 'print_offset_y']);
            $table->unique('type');
        });
    }
};
