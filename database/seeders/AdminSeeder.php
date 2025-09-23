<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultProfilePhoto = 'profile_photos/user.png';

        $admins = [
            [
                'email' => 'nishat15-12132@diu.edu.bd',
                'name' => 'Admin 1',
                'password' => '123456789',
                'role' => 'admin',
                'profile_photo' => asset($defaultProfilePhoto),
            ],
            [
                'email' => 'demoad007@gmail.com',
                'name' => 'Admin',
                'password' => '123456789',
                'role' => 'admin',
                'profile_photo' => asset($defaultProfilePhoto),
            ],
          
        ];

        foreach ($admins as $adminData) {
            $admin = User::firstOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'password' => Hash::make($adminData['password']),
                    'role' => $adminData['role'],
                    'profile_photo' => $adminData['profile_photo'],
                ]
            );

            if ($admin->wasRecentlyCreated) {
                $this->command->info("Admin {$adminData['name']} created successfully.");
            } else {
                $this->command->info("Admin {$adminData['name']} already exists.");
            }
        }
    }
}
