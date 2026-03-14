<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Temporarily disable model events to avoid duplicate PostMetaData creation
        Post::flushEventListeners();

        // Get all users to assign posts to
        $users = User::all();

        if ($users->isEmpty()) {
            // Create some test users if none exist
            $users = User::factory()->count(5)->create();
            $this->command->info('Created 5 test users.');
        }

        // Define realistic posts with meaningful content and hashtags
        $posts = [
            [
                'content' => "Just launched my new startup! #startup #entrepreneur #tech #innovation #business",
                'tags' => ['startup', 'entrepreneur', 'tech', 'innovation', 'business']
            ],
            [
                'content' => "Loving the new Laravel features! #laravel #php #webdev #programming #backend",
                'tags' => ['laravel', 'php', 'webdev', 'programming', 'backend']
            ],
            [
                'content' => "Beautiful day for hiking in the mountains #hiking #nature #outdoors #adventure #mountains",
                'tags' => ['hiking', 'nature', 'outdoors', 'adventure', 'mountains']
            ],
            [
                'content' => "Working on a new React project #react #javascript #frontend #webdev #coding",
                'tags' => ['react', 'javascript', 'frontend', 'webdev', 'coding']
            ],
            [
                'content' => "Just finished reading an amazing book about AI #ai #machinelearning #tech #future #book",
                'tags' => ['ai', 'machinelearning', 'tech', 'future', 'book']
            ],
            [
                'content' => "Coffee and coding - the perfect combination #coffee #coding #programming #developer #morning",
                'tags' => ['coffee', 'coding', 'programming', 'developer', 'morning']
            ],
            [
                'content' => "Traveling to Japan next month! So excited #travel #japan #adventure #culture #exploring",
                'tags' => ['travel', 'japan', 'adventure', 'culture', 'exploring']
            ],
            [
                'content' => "Learning MongoDB for our new project #mongodb #database #nosql #backend #tech",
                'tags' => ['mongodb', 'database', 'nosql', 'backend', 'tech']
            ],
            [
                'content' => "Just completed a 10k run! #fitness #running #health #exercise #marathon",
                'tags' => ['fitness', 'running', 'health', 'exercise', 'marathon']
            ],
            [
                'content' => "Working from home today #remote #workfromhome #productivity #homeoffice #tech",
                'tags' => ['remote', 'workfromhome', 'productivity', 'homeoffice', 'tech']
            ],
            [
                'content' => "Building a REST API with Laravel Sanctum #laravel #api #rest #sanctum #backend",
                'tags' => ['laravel', 'api', 'rest', 'sanctum', 'backend']
            ],
            [
                'content' => "Photography session in the park #photography #nature #art #creative #outdoors",
                'tags' => ['photography', 'nature', 'art', 'creative', 'outdoors']
            ],
            [
                'content' => "Just deployed our new microservices architecture #microservices #docker #kubernetes #devops #cloud",
                'tags' => ['microservices', 'docker', 'kubernetes', 'devops', 'cloud']
            ],
            [
                'content' => "Weekend coding marathon #weekend #coding #programming #developer #projects",
                'tags' => ['weekend', 'coding', 'programming', 'developer', 'projects']
            ],
            [
                'content' => "Exploring new hiking trails #hiking #adventure #nature #exploring #weekend",
                'tags' => ['hiking', 'adventure', 'nature', 'exploring', 'weekend']
            ],
            [
                'content' => "Just attended an amazing tech conference #tech #conference #learning #networking #innovation",
                'tags' => ['tech', 'conference', 'learning', 'networking', 'innovation']
            ],
            [
                'content' => "Working on improving our WAF implementation #security #waf #websecurity #cybersecurity #laravel",
                'tags' => ['security', 'waf', 'websecurity', 'cybersecurity', 'laravel']
            ],
            [
                'content' => "Morning meditation session #meditation #mindfulness #health #wellness #morning",
                'tags' => ['meditation', 'mindfulness', 'health', 'wellness', 'morning']
            ],
            [
                'content' => "Building a social media app with real-time features #realtime #websockets #socialmedia #app #development",
                'tags' => ['realtime', 'websockets', 'socialmedia', 'app', 'development']
            ],
            [
                'content' => "Just learned a new algorithm! #algorithms #datastructures #programming #learning #cs",
                'tags' => ['algorithms', 'datastructures', 'programming', 'learning', 'cs']
            ],
        ];

        $createdCount = 0;

        // Create posts
        foreach ($posts as $postData) {
            // Randomly assign to a user
            $user = $users->random();

            // Random date within last 30 days
            $createdAt = now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            try {
                // Create the post
                $post = Post::create([
                    'content' => $postData['content'],
                    'tags' => $postData['tags'],
                    'user_id' => $user->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Create post metadata with random interaction counts
                PostMetaData::create([
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'likes_count' => rand(0, 100),
                    'comments_count' => rand(0, 50),
                    'shares_count' => rand(0, 30),
                    'visibility' => 'public',
                    'status' => 'published',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $createdCount++;
            } catch (\Exception $e) {
                $this->command->error('Failed to create post: ' . $e->getMessage());
            }
        }

        // Re-enable model events
        Post::boot();

        $this->command->info('Successfully created ' . $createdCount . ' posts with realistic data.');
        $this->command->info('Posts have meaningful hashtags that will make search work properly.');
        $this->command->info('Search examples:');
        $this->command->info('  - Search for "tech" will find posts about technology');
        $this->command->info('  - Search for "hiking" will find outdoor activity posts');
        $this->command->info('  - Search for "laravel" will find programming posts');
    }
}
