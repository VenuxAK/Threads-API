<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostMetaData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostMetaDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = Post::all();

        $posts->each(function ($post) {
            PostMetaData::create([
                "post_id" => $post->id,
                "user_id" => $post->user_id,
            ]);
        });
    }
}
