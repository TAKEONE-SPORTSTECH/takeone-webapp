<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Goal;
use App\Models\User;

class GoalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user or create sample goals for existing users
        $users = User::all();

        if ($users->isEmpty()) {
            return; // No users to seed goals for
        }

        foreach ($users as $user) {
            // Create sample goals
            Goal::create([
                'user_id' => $user->id,
                'title' => 'Weight Loss Goal',
                'description' => 'Reach target weight of 170 lbs.',
                'start_date' => now()->subDays(30),
                'target_date' => now()->addDays(60),
                'current_progress_value' => 175.0,
                'target_value' => 170.0,
                'status' => 'active',
                'priority_level' => 'high',
                'unit' => 'lbs',
                'icon_type' => 'target',
            ]);

            Goal::create([
                'user_id' => $user->id,
                'title' => 'Bench Press Strength',
                'description' => 'Increase bench press to 200 lbs.',
                'start_date' => now()->subDays(15),
                'target_date' => now()->addDays(45),
                'current_progress_value' => 180.0,
                'target_value' => 200.0,
                'status' => 'active',
                'priority_level' => 'medium',
                'unit' => 'lbs',
                'icon_type' => 'dumbbell',
            ]);

            Goal::create([
                'user_id' => $user->id,
                'title' => '5K Running Time',
                'description' => 'Complete 5K run in under 25 minutes.',
                'start_date' => now()->subDays(20),
                'target_date' => now()->addDays(40),
                'current_progress_value' => 27.5,
                'target_value' => 25.0,
                'status' => 'active',
                'priority_level' => 'medium',
                'unit' => 'min',
                'icon_type' => 'clock',
            ]);

            Goal::create([
                'user_id' => $user->id,
                'title' => 'Daily Steps Goal',
                'description' => 'Walk 10,000 steps per day.',
                'start_date' => now()->subDays(10),
                'target_date' => now()->addDays(20),
                'current_progress_value' => 8500.0,
                'target_value' => 10000.0,
                'status' => 'completed',
                'priority_level' => 'low',
                'unit' => 'steps',
                'icon_type' => 'target',
            ]);
        }
    }
}
