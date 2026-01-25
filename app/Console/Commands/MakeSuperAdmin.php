<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;

class MakeSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:make-super {email : The email of the user to make super admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user a super admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found!");
            return 1;
        }

        // Check if user already has super-admin role
        if ($user->hasRole('super-admin')) {
            $this->info("User '{$user->full_name}' ({$email}) is already a super admin!");
            return 0;
        }

        // Assign super-admin role
        $user->assignRole('super-admin');

        $this->info("✅ Successfully made '{$user->full_name}' ({$email}) a super admin!");
        $this->info("They can now access the admin panel at: " . url('/admin'));

        return 0;
    }
}
