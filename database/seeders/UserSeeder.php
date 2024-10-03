<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'User One',
            'username' => 'user_one',
            'email' => 'userone@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Two',
            'username' => 'user_two',
            'email' => 'usertwo@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Three',
            'username' => 'user_three',
            'email' => 'userthree@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Four',
            'username' => 'user_four',
            'email' => 'userfour@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Five',
            'username' => 'user_five',
            'email' => 'userfive@example.com',
        ]);

        User::factory()->create([
            'name' => 'User Six',
            'username' => 'user_six',
            'email' => 'usersix@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Seven',
            'username' => 'user_seven',
            'email' => 'userseven@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Eight',
            'username' => 'user_eight',
            'email' => 'usereight@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Nine',
            'username' => 'user_nine',
            'email' => 'usernine@example.com',
        ]);
        User::factory()->create([
            'name' => 'User Ten',
            'username' => 'user_ten',
            'email' => 'userten@example.com',
        ]);
    }
}
