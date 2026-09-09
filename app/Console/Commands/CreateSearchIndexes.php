<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Console\Command;

class CreateSearchIndexes extends Command
{
    protected $signature = 'mongodb:create-indexes';

    protected $description = 'Create MongoDB search indexes for posts and comments';

    public function handle(): int
    {
        $this->info('Creating MongoDB indexes...');

        try {
            $post = new Post;
            $collection = $post->getTable();
            $post->getConnection()->getMongoDB()->selectCollection($collection)->createIndex(
                ['content' => 'text'],
                ['name' => 'content_text_index']
            );
            $this->info("Created text index on '{$collection}.content'");
        } catch (\Exception $e) {
            $this->warn("Post content index: {$e->getMessage()}");
        }

        try {
            $post = new Post;
            $post->getConnection()->getMongoDB()->selectCollection($post->getTable())->createIndex(
                ['tags' => 1],
                ['name' => 'tags_index']
            );
            $this->info("Created index on 'posts.tags'");
        } catch (\Exception $e) {
            $this->warn("Posts tags index: {$e->getMessage()}");
        }

        try {
            $comment = new Comment;
            $comment->getConnection()->getMongoDB()->selectCollection($comment->getTable())->createIndex(
                ['post_id' => 1, 'parent_id' => 1, 'created_at' => -1],
                ['name' => 'comments_lookup_index']
            );
            $this->info("Created compound index on 'comments'");
        } catch (\Exception $e) {
            $this->warn("Comments index: {$e->getMessage()}");
        }

        $this->info('Index creation complete.');

        return Command::SUCCESS;
    }
}
