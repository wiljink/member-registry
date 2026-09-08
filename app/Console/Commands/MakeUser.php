<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeUser extends Command
{
    protected $signature = 'app:make-user {name} {email} {--password=}';

    protected $description = 'Create (or update) a login account for the registry app';

    public function handle(): int
    {
        $password = $this->option('password') ?: Str::password(12);

        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->argument('name'), 'password' => Hash::make($password)],
        );

        $this->info("User {$user->email} ready.");
        if (! $this->option('password')) {
            $this->line("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
