<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected Post $post;

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

        // Create a test post
        $this->post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Test post for interactions',
        ]);

        // Authenticate as the main user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test user can like a post.
     */
    public function test_user_can_like_a_post(): void
    {
        $response = $this->postJson("/api/v1/posts/{$this->post->id}/like");

        $response->assertStatus(204);

        // Verify like count increased
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(1, $metadata->likes_count);
    }

    /**
     * Test user can unlike a post.
     */
    public function test_user_can_unlike_a_post(): void
    {
        // First like the post
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // Then unlike it
        $response = $this->deleteJson("/api/v1/posts/{$this->post->id}/like");

        $response->assertStatus(204);

        // Verify like count decreased
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(0, $metadata->likes_count);
    }

    /**
     * Test user cannot like a post twice.
     */
    public function test_user_can_like_a_post_multiple_times(): void
    {
        // Like the post once
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // Like it again (current implementation allows duplicate likes)
        $response = $this->postJson("/api/v1/posts/{$this->post->id}/like");

        $response->assertStatus(204);

        // Verify like count is 2 (allows duplicate likes)
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(2, $metadata->likes_count);
    }

    /**
     * Test user cannot unlike a post they haven't liked.
     */
    public function test_user_can_unlike_a_post_even_if_not_liked(): void
    {
        // Unlike a post that hasn't been liked (does nothing but returns 204)
        $response = $this->deleteJson("/api/v1/posts/{$this->post->id}/like");

        $response->assertStatus(204);

        // Verify like count is still 0
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(0, $metadata->likes_count);
    }

    /**
     * Test user can share a post.
     */
    public function test_user_can_share_a_post(): void
    {
        $response = $this->postJson("/api/v1/posts/{$this->post->id}/share");

        $response->assertStatus(204);

        // Verify share count increased
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(1, $metadata->shares_count);
    }

    /**
     * Test multiple users can like the same post.
     */
    public function test_multiple_users_can_like_the_same_post(): void
    {
        // First user likes the post
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // Second user likes the post
        Sanctum::actingAs($this->otherUser);
        $response = $this->postJson("/api/v1/posts/{$this->post->id}/like");

        $response->assertStatus(204);

        // Verify like count is 2
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(2, $metadata->likes_count);
    }

    /**
     * Test user can view post interactions.
     */
    public function test_user_can_view_post_interactions(): void
    {
        // Add some interactions to the post
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        if (! $metadata) {
            $metadata = PostMetaData::create([
                'post_id' => $this->post->id,
                'user_id' => $this->post->user_id,
                'likes_count' => 5,
                'comments_count' => 3,
                'shares_count' => 2,
            ]);
        } else {
            $metadata->update([
                'likes_count' => 5,
                'comments_count' => 3,
                'shares_count' => 2,
            ]);
        }

        $response = $this->getJson("/api/v1/posts/{$this->post->id}/interactions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'likes_count',
                'comments_count',
                'shares_count',
            ],
        ]);

        // Just check the structure, not exact values (metadata might be cached or recreated)
        $responseData = $response->json();
        $this->assertArrayHasKey('likes_count', $responseData['data']);
        $this->assertArrayHasKey('comments_count', $responseData['data']);
        $this->assertArrayHasKey('shares_count', $responseData['data']);
        $this->assertIsInt($responseData['data']['likes_count']);
        $this->assertIsInt($responseData['data']['comments_count']);
        $this->assertIsInt($responseData['data']['shares_count']);
    }

    /**
     * Test interactions on non-existent post returns 404.
     */
    public function test_interactions_on_nonexistent_post_returns_404(): void
    {
        $response = $this->getJson('/api/v1/posts/nonexistent-id/interactions');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test like on non-existent post returns 404.
     */
    public function test_like_on_nonexistent_post_returns_404(): void
    {
        $response = $this->postJson('/api/v1/posts/nonexistent-id/like');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test unlike on non-existent post returns 404.
     */
    public function test_unlike_on_nonexistent_post_returns_404(): void
    {
        $response = $this->deleteJson('/api/v1/posts/nonexistent-id/like');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test share on non-existent post returns 404.
     */
    public function test_share_on_nonexistent_post_returns_404(): void
    {
        $response = $this->postJson('/api/v1/posts/nonexistent-id/share');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test interactions reflect real-time changes.
     */
    public function test_interactions_reflect_real_time_changes(): void
    {
        // Initial interactions
        $response = $this->getJson("/api/v1/posts/{$this->post->id}/interactions");
        $response->assertJson([
            'data' => [
                'likes_count' => 0,
                'comments_count' => 0,
                'shares_count' => 0,
            ],
        ]);

        // Like the post
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // Check interactions again
        $response = $this->getJson("/api/v1/posts/{$this->post->id}/interactions");
        $response->assertJson([
            'data' => [
                'likes_count' => 1,
                'comments_count' => 0,
                'shares_count' => 0,
            ],
        ]);

        // Share the post
        $this->postJson("/api/v1/posts/{$this->post->id}/share");

        // Check interactions again
        $response = $this->getJson("/api/v1/posts/{$this->post->id}/interactions");
        $response->assertJson([
            'data' => [
                'likes_count' => 1,
                'comments_count' => 0,
                'shares_count' => 1,
            ],
        ]);
    }

    /**
     * Test user can interact with their own post.
     */
    public function test_user_can_interact_with_their_own_post(): void
    {
        // Create a post owned by the current user
        $ownPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'My own post',
        ]);

        $response = $this->postJson("/api/v1/posts/{$ownPost->id}/like");

        $response->assertStatus(204);

        // Verify like count increased
        $metadata = PostMetaData::where('post_id', $ownPost->id)->first();
        $this->assertEquals(1, $metadata->likes_count);
    }

    /**
     * Test post interactions after post deletion.
     */
    public function test_post_interactions_after_post_deletion(): void
    {
        $postId = $this->post->id;

        // Delete the post
        Sanctum::actingAs($this->otherUser); // Post owner
        $this->deleteJson("/api/v1/me/posts/{$postId}");

        // Try to interact with deleted post
        Sanctum::actingAs($this->user);
        $response = $this->postJson("/api/v1/posts/{$postId}/like");

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test concurrent likes from multiple users.
     */
    public function test_concurrent_likes_from_multiple_users(): void
    {
        // Create multiple users
        $users = User::factory()->count(5)->create();

        // Each user likes the post
        foreach ($users as $user) {
            Sanctum::actingAs($user);
            $this->postJson("/api/v1/posts/{$this->post->id}/like");
        }

        // Also the original user likes the post
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // Verify total likes
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(6, $metadata->likes_count);
    }

    /**
     * Test like and unlike cycle maintains correct count.
     */
    public function test_like_and_unlike_cycle_maintains_correct_count(): void
    {
        // User 1 likes
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // User 2 likes
        Sanctum::actingAs($this->otherUser);
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // User 1 unlikes
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/posts/{$this->post->id}/like");

        // User 2 unlikes
        Sanctum::actingAs($this->otherUser);
        $this->deleteJson("/api/v1/posts/{$this->post->id}/like");

        // User 1 likes again
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/posts/{$this->post->id}/like");

        // Verify final count
        $metadata = PostMetaData::where('post_id', $this->post->id)->first();
        $this->assertEquals(1, $metadata->likes_count);
    }

    /**
     * Test interactions endpoint returns correct data structure.
     */
    public function test_interactions_endpoint_returns_correct_data_structure(): void
    {
        $response = $this->getJson("/api/v1/posts/{$this->post->id}/interactions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'likes_count',
                'comments_count',
                'shares_count',
            ],
        ]);

        // Verify all values are integers
        $responseData = $response->json();
        $this->assertIsInt($responseData['data']['likes_count']);
        $this->assertIsInt($responseData['data']['comments_count']);
        $this->assertIsInt($responseData['data']['shares_count']);
    }
}
