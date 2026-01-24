<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (!$user) {
            return; // Skip if test data not found
        }

        $attendanceRecords = [
            [
                'member_id' => $user->id,
                'session_type' => 'Personal Training',
                'trainer_name' => 'John Smith',
                'session_datetime' => now()->subDays(1)->setTime(10, 0),
                'status' => 'completed',
                'notes' => 'Great session, focused on upper body strength',
            ],
            [
                'member_id' => $user->id,
                'session_type' => 'Group Fitness',
                'trainer_name' => 'Sarah Johnson',
                'session_datetime' => now()->subDays(2)->setTime(18, 30),
                'status' => 'completed',
                'notes' => 'High-intensity cardio workout',
            ],
            [
                'member_id' => $user->id,
                'session_type' => 'Yoga',
                'trainer_name' => 'Mike Wilson',
                'session_datetime' => now()->subDays(3)->setTime(9, 0),
                'status' => 'no_show',
                'notes' => 'Member did not attend scheduled session',
            ],
            [
                'member_id' => $user->id,
                'session_type' => 'Pilates',
                'trainer_name' => 'Emily Davis',
                'session_datetime' => now()->subDays(4)->setTime(17, 0),
                'status' => 'completed',
                'notes' => 'Core strengthening and flexibility',
            ],
            [
                'member_id' => $user->id,
                'session_type' => 'Personal Training',
                'trainer_name' => 'John Smith',
                'session_datetime' => now()->subDays(5)->setTime(14, 0),
                'status' => 'completed',
                'notes' => 'Lower body strength training',
            ],
            [
                'member_id' => $user->id,
                'session_type' => 'Group Fitness',
                'trainer_name' => 'Sarah Johnson',
                'session_datetime' => now()->subDays(6)->setTime(19, 0),
                'status' => 'no_show',
                'notes' => 'Late cancellation',
            ],
        ];

        foreach ($attendanceRecords as $record) {
            Attendance::create($record);
        }
    }
}
