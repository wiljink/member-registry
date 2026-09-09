<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ict@orointegrated.coop'],
            [
                'name' => 'Registry Admin',
                'password' => Hash::make('password'),   // CHANGE after first login
            ],
        );
    }
}
