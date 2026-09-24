<?php

namespace App\Federations\Commands;

use App\Federations\Models\Federation;
use App\Members\Models\User;
use App\Support\Countries;
use Illuminate\Console\Command;

/**
 * Create an approved federation for an existing user. The only way in until
 * the apply/approve flow is designed.
 */
class CreateFederation extends Command
{
    protected $signature = 'federation:create
        {name : Full name, e.g. "Bahrain Taekwondo Federation"}
        {--owner= : Email of the user who will run it}
        {--short= : Short name, e.g. BTF}
        {--country= : ISO code or country name}
        {--sport= : Sport key, e.g. taekwondo}
        {--color= : Band colour as #rrggbb}';

    protected $description = 'Create an approved federation and give its dashboard to a user';

    public function handle(): int
    {
        $owner = User::where('email', (string) $this->option('owner'))->first();
        if (! $owner) {
            $this->error('No user with that --owner email.');

            return self::FAILURE;
        }

        $color = $this->option('color');
        if ($color !== null && ! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $this->error('--color must be #rrggbb.');

            return self::FAILURE;
        }

        $federation = Federation::create([
            'owner_user_id' => $owner->id,
            'name' => mb_substr(trim($this->argument('name')), 0, 150),
            'short_name' => $this->option('short') ? mb_substr($this->option('short'), 0, 20) : null,
            'country' => Countries::iso2($this->option('country')),
            'sport' => $this->option('sport') ? mb_substr(strtolower($this->option('sport')), 0, 40) : null,
            'color' => $color,
            'status' => Federation::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->info("Created {$federation->name}");
        $this->line('  '.route('federations.dashboard', $federation));

        return self::SUCCESS;
    }
}
