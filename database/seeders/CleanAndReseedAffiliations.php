<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanAndReseedAffiliations extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Cleaning old affiliation data...');

        // Delete in correct order due to foreign keys
        DB::table('skill_acquisitions')->delete();
        DB::table('affiliation_media')->delete();
        DB::table('club_member_subscriptions')->delete();
        DB::table('club_package_activities')->delete();
        DB::table('club_affiliations')->delete();
        DB::table('club_packages')->delete();
        DB::table('club_activities')->delete();

        $this->command->info('✓ Old data cleaned successfully!');
        $this->command->info('');

        // Now run the affiliations seeder
        $this->call(AffiliationsDataSeeder::class);
    }
}
