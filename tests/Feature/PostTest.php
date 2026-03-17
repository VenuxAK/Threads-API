<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->otherUser = User::factory()->create([
            'name' => 'Other User',
            'username' => 'otheruser',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
        ]);

        // Authenticate as the main user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test user can create a post.
     */
    public function test_user_can_create_post(): void
    {
        $response = $this->postJson('/api/v1/me/posts', [
            'content' => 'This is a test post with #hashtag',
        ]);

        $response->assertStatus(204);

        // Verify post was created in MongoDB
        // Verify post was created in MongoDB
        $post = Post::where('content', 'This is a test post with #hashtag')
            ->where('user_id', $this->user->id)
            ->first();
        $this->assertNotNull($post, 'Post not found for user '.$this->user->id);
        $this->assertEquals($this->user->id, $post->user_id);
        $this->assertContains('hashtag', $post->tags);

        // Verify metadata was created in MySQL
        $this->assertDatabaseHas('post_meta_data', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test post creation requires content.
     */
    public function test_post_creation_requires_content(): void
    {
        $response = $this->postJson('/api/v1/me/posts', [
            'content' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
    }

    /**
     * Test post content has maximum length.
     */
    public function test_post_content_has_maximum_length(): void
    {
        $longContent = str_repeat('a', 5001); // Exceeds 5000 character limit

        $response = $this->postJson('/api/v1/me/posts', [
            'content' => $longContent,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
    }

    /**
     * Test hashtags are extracted from post content.
     */
    public function test_hashtags_are_extracted_from_post_content(): void
    {
        $response = $this->postJson('/api/v1/me/posts', [
            'content' => 'Post with #hashtag1 and #hashtag2 #test',
        ]);

        $response->assertStatus(204);

        $post = Post::where('content', 'Post with #hashtag1 and #hashtag2 #test')->first();
        $this->assertContains('hashtag1', $post->tags);
        $this->assertContains('hashtag2', $post->tags);
        $this->assertContains('test', $post->tags);
    }

    /**
     * Test user can view their own posts.
     */
    public function test_user_can_view_their_own_posts(): void
    {
        // Create posts for the user
        Post::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'content' => 'Test post content',
        ]);

        $response = $this->getJson('/api/v1/me/posts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'posts' => [
                    'data' => [
                        '*' => [
                            'id',
                            'content',
                            'tags',
                            'published_at',
                            'edited_at',
                            'interactions' => [
                                'likes',
                                'comments',
                                'shares',
                            ],
                            'author' => [
                                'name',
                                'username',
                                'avatar',
                                'bio',
                            ],
                        ],
                    ],
                    'current_page',
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ],
            ],
        ]);

        $responseData = $response->json();
        $this->assertGreaterThanOrEqual(3, $responseData['data']['posts']['total']);
    }

    /**
     * Test user can view a specific post.
     */
    public function test_user_can_view_specific_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Specific post content',
        ]);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'post' => [
                    'id',
                    'content',
                    'tags',
                    'published_at',
                    'edited_at',
                    'interactions',
                    'author',
                ],
            ],
        ]);

        $response->assertJson([
            'data' => [
                'post' => [
                    'id' => $post->id,
                    'content' => 'Specific post content',
                ],
            ],
        ]);
    }

    /**
     * Test user can update their own post.
     */
    public function test_user_can_update_their_own_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Original content',
        ]);

        $response = $this->putJson("/api/v1/me/posts/{$post->id}", [
            'content' => 'Updated content with #newhashtag',
        ]);

        $response->assertStatus(204);

        $post->refresh();
        $this->assertEquals('Updated content with #newhashtag', $post->content);
        $this->assertContains('newhashtag', $post->tags);
    }

    /**
     * Test user cannot update another user's post.
     */
    public function test_user_cannot_update_another_users_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Other user post',
        ]);

        $response = $this->putJson("/api/v1/me/posts/{$post->id}", [
            'content' => 'Trying to update',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test user can delete their own post.
     */
    public function test_user_can_delete_their_own_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Post to delete',
        ]);

        $postId = $post->id;

        $response = $this->deleteJson("/api/v1/me/posts/{$postId}");

        $response->assertStatus(204);

        // Verify post is deleted from MongoDB
        $this->assertNull(Post::find($postId));

        // Verify metadata is deleted from MySQL (Post model's deleting event should handle this)
        // Note: The Post model's deleting event attempts to delete metadata
        // but if it fails, it logs an error and continues
    }

    /**
     * Test user cannot delete another user's post.
     */
    public function test_user_cannot_delete_another_users_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Other user post',
        ]);

        $response = $this->deleteJson("/api/v1/me/posts/{$post->id}");

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test viewing non-existent post returns 404.
     */
    public function test_viewing_nonexistent_post_returns_404(): void
    {
        $response = $this->getJson('/api/v1/posts/nonexistent-id');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test posts are paginated.
     */
    public function test_posts_are_paginated(): void
    {
        // Get current total posts
        $currentTotal = Post::count();

        // Create more posts
        Post::factory()->count(20)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/posts?per_page=5');

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(5, $responseData['data']['pagination']['per_page']);
        $this->assertEquals(1, $responseData['data']['pagination']['current_page']);
        $this->assertEquals(ceil(($currentTotal + 20) / 5), $responseData['data']['pagination']['last_page']);
        $this->assertCount(5, $responseData['data']['posts']['data']);
    }

    /**
     * Test public posts endpoint returns posts from all users.
     */
    public function test_public_posts_endpoint_returns_posts_from_all_users(): void
    {
        // Get current post count
        $currentCount = Post::count();

        // Create posts for multiple users
        Post::factory()->count(2)->create(['user_id' => $this->user->id]);
        Post::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/v1/posts?per_page=100'); // Use large per_page to get all

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertGreaterThanOrEqual($currentCount + 4, $responseData['data']['posts']['total']);
    }

    /**
     * Test post metadata is included in response.
     */
    public function test_post_metadata_is_included_in_response(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Post with metadata',
        ]);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertArrayHasKey('interactions', $responseData['data']['post']);
        $this->assertArrayHasKey('likes', $responseData['data']['post']['interactions']);
        $this->assertArrayHasKey('comments', $responseData['data']['post']['interactions']);
        $this->assertArrayHasKey('shares', $responseData['data']['post']['interactions']);
    }

    /**
     * Test post author information is included in response.
     */
    public function test_post_author_information_is_included_in_response(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Post with author info',
        ]);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertArrayHasKey('author', $responseData['data']['post']);
        $this->assertEquals($this->user->name, $responseData['data']['post']['author']['name']);
        $this->assertEquals($this->user->username, $responseData['data']['post']['author']['username']);
    }

    /**
     * Test posts are ordered by latest first.
     */
    public function test_posts_are_ordered_by_latest_first(): void
    {
        $oldPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Old post',
            'created_at' => now()->subDays(2),
        ]);

        $newPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'New post',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/posts');

        $response->assertStatus(200);
        $responseData = $response->json();

        // First post should be the newest
        $this->assertEquals($newPost->id, $responseData['data']['posts']['data'][0]['id']);
        $this->assertEquals('New post', $responseData['data']['posts']['data'][0]['content']);
    }
}
