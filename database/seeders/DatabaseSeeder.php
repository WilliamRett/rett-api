<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Collaborator;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'email.rett@outlook.com.br'],
            [
                'name'              => 'William Rett',
                'password'          => Hash::make('secret123'),
                'email_verified_at' => now(),
                'remember_token'    => Str::random(10),
            ]
        );

        $this->command?->info("User seeded: {$user->email} | password: secret123");

        $count = 20;
        Collaborator::factory()
            ->count($count)
            ->create(['user_id' => $user->id]);

        $this->command?->info("{$count} colaboradores criados para o user_id={$user->id}");
    }
}
