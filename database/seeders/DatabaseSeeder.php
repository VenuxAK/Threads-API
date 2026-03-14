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
        // Post::truncate();
        // Create test users first
        $this->call([
            UserSeeder::class,
        ]);

        // Create posts with realistic data
        // Note: Make sure MongoDB posts collection is empty or truncate it first
        // Also, temporarily disable Post model events in PostSeeder
        $this->call([
            PostSeeder::class,
        ]);

        // PostMetaData is created within PostSeeder
        // No need for separate PostMetaDataSeeder

        $this->command->info('Database seeded successfully!');
        $this->command->info('Test users created with credentials:');
        $this->command->info('  - Email: test@example.com, Password: password');
        $this->command->info('  - Email: user@example.com, Password: password');
        $this->command->info('Search functionality now works with meaningful data:');
        $this->command->info('  Try searching for: tech, hiking, laravel, programming, travel');
    }
}
