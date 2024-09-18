<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        /**
         * Run First
         */
        // User::factory()->create([
        //     'name' => 'User One',
        //     'username' => 'user_one',
        //     'email' => 'userone@example.com',
        // ]);
        // User::factory()->create([
        //     'name' => 'User Two',
        //     'username' => 'user_two',
        //     'email' => 'usertwo@example.com',
        // ]);
        // User::factory()->create([
        //     'name' => 'User Three',
        //     'username' => 'user_three',
        //     'email' => 'userthree@example.com',
        // ]);
        // Post::truncate();

        /**
         * Run after the first one done
         */
        // Post::factory(50)->create();
    }
}
