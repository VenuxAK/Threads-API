<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostMetaData;
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

        // Please make sure that you truncate posts collection of MongoDB
        // Post::truncate();

        $this->call([
            // UserSeeder::class,      // Run First

            // Please make sure that you comment Post's boot method
            // PostSeeder::class,      // Run Second ***

            // PostMetaDataSeeder::class,   // Run after PostSeeder done
        ]);
    }
}
