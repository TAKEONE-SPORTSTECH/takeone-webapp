<?php

namespace App\Ranks\Commands;

use App\Clubs\Models\ClubActivity;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Support\ActivityPackages;
use Illuminate\Console\Command;

/**
 * Link existing club activities to their sport packages.
 * Dry run by default; --apply writes. Never touches a club's own choice.
 */
class LinkActivitiesCommand extends Command
{
    protected $signature = 'ranks:link-activities {--apply : Write the links (default is a dry run)}';

    protected $description = 'Match existing club activities to their sport package (dry run unless --apply)';

    public function handle(ActivityPackages $links): int
    {
        $rows = [];
        foreach (ClubActivity::orderBy('tenant_id')->orderBy('id')->get() as $a) {
            $current = ActivityPackage::where('activity_id', $a->id)->first();
            $hit = $current?->matched_by === 'club' ? null : $links->match($a->getRawOriginal('name'), $a->getRawOriginal('style'));
            $rows[] = [$a->id, $a->tenant_id, $a->name, $current?->matched_by === 'club' ? 'club choice (kept)' : ($hit ? $hit[0].' · '.$hit[1] : '— no package')];
            if ($this->option('apply') && $current?->matched_by !== 'club') {
                $links->autoLink($a);
            }
        }
        $this->table(['activity', 'club', 'name', 'package'], $rows);
        $this->info($this->option('apply') ? 'Links written.' : 'Dry run — nothing written. Use --apply to write.');

        return self::SUCCESS;
    }
}
