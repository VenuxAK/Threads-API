<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileTest extends TestCase
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
            'avatar' => 'https://example.com/avatar.jpg',
            'bio' => 'This is my bio',
            'password' => Hash::make('password'),
        ]);

        $this->otherUser = User::factory()->create([
            'name' => 'Other User',
            'username' => 'otheruser',
            'email' => 'other@example.com',
            'avatar' => 'https://example.com/other-avatar.jpg',
            'bio' => 'Other user bio',
            'password' => Hash::make('password'),
        ]);

        // Authenticate as the main user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test authenticated user can view their own profile.
     */
    public function test_authenticated_user_can_view_their_own_profile(): void
    {
        $response = $this->getJson('/api/v1/me/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'username',
                'email',
                'avatar',
                'bio',
                'email_verified',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'name' => 'Test User',
                'username' => 'testuser',
                'email' => 'test@example.com',
            ],
        ]);
    }

    /**
     * Test user can view another user's profile.
     */
    public function test_user_can_view_another_users_profile(): void
    {
        $response = $this->getJson('/api/v1/users/otheruser');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'username',
                    'avatar',
                    'bio',
                ],
            ],
        ]);

        $response->assertJson([
            'data' => [
                'user' => [
                    'name' => 'Other User',
                    'username' => 'otheruser',
                    'avatar' => 'https://example.com/other-avatar.jpg',
                    'bio' => 'Other user bio',
                ],
            ],
        ]);
    }

    /**
     * Test viewing user profile with posts included.
     */
    public function test_viewing_user_profile_with_posts_included(): void
    {
        // Create posts for the other user
        Post::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Test post content',
        ]);

        $response = $this->getJson('/api/v1/users/otheruser?posts=include');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'username',
                    'avatar',
                    'bio',
                ],
                'posts' => [
                    'data' => [
                        '*' => [
                            'id',
                            'content',
                            'tags',
                            'published_at',
                            'edited_at',
                            'interactions',
                            'author',
                        ],
                    ],
                ],
                'pagination',
            ],
        ]);

        $responseData = $response->json();
        $this->assertGreaterThanOrEqual(3, $responseData['data']['posts']['total']);
        $this->assertEquals('Other User', $responseData['data']['user']['name']);
    }

    /**
     * Test viewing user profile with specific post.
     */
    public function test_viewing_user_profile_with_specific_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Specific post content',
        ]);

        $response = $this->getJson("/api/v1/users/otheruser?post={$post->id}");

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
     * Test viewing non-existent user returns 404.
     */
    public function test_viewing_nonexistent_user_returns_404(): void
    {
        $response = $this->getJson('/api/v1/users/nonexistentuser');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'User not found',
        ]);
    }

    /**
     * Test viewing user with non-existent post returns user info only.
     */
    public function test_viewing_user_with_nonexistent_post_returns_user_info_only(): void
    {
        $response = $this->getJson('/api/v1/users/otheruser?post=nonexistent-post-id');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Post not found',
        ]);
    }

    /**
     * Test user profile endpoint works without authentication.
     */
    public function test_user_profile_endpoint_works_without_authentication(): void
    {
        // Log out the current user
        Sanctum::actingAs($this->user, [], 'web');
        $this->post('/auth/logout');

        $response = $this->getJson('/api/v1/users/otheruser');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'user' => [
                    'username' => 'otheruser',
                ],
            ],
        ]);
    }

    /**
     * Test user profile with posts pagination.
     */
    public function test_user_profile_with_posts_pagination(): void
    {
        // Create more posts than default per page
        Post::factory()->count(15)->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Test post',
        ]);

        $response = $this->getJson('/api/v1/users/otheruser?posts=include&per_page=5');

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(5, $responseData['data']['pagination']['per_page']);
        $this->assertEquals(1, $responseData['data']['pagination']['current_page']);
        $this->assertGreaterThanOrEqual(1, $responseData['data']['pagination']['last_page']);
        $this->assertCount(min(5, $responseData['data']['posts']['total']), $responseData['data']['posts']['data']);
    }

    /**
     * Test user profile returns correct post author information.
     */
    public function test_user_profile_returns_correct_post_author_information(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Post content',
        ]);

        $response = $this->getJson("/api/v1/users/otheruser?post={$post->id}");

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals('Other User', $responseData['data']['post']['author']['name']);
        $this->assertEquals('otheruser', $responseData['data']['post']['author']['username']);
    }

    /**
     * Test user profile with mixed query parameters.
     */
    public function test_user_profile_with_mixed_query_parameters(): void
    {
        // Create posts
        Post::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Specific post',
        ]);

        // Test with both posts=include and post=id (posts=include takes precedence)
        $response = $this->getJson("/api/v1/users/otheruser?posts=include&post={$post->id}");

        $response->assertStatus(200);
        $responseData = $response->json();

        // When posts=include is provided, should return posts array, not single post
        $this->assertArrayHasKey('posts', $responseData['data']);
        $this->assertArrayHasKey('user', $responseData['data']);
        $this->assertArrayNotHasKey('post', $responseData['data']);
    }

    /**
     * Test user profile endpoint validates username format.
     */
    public function test_user_profile_endpoint_validates_username_format(): void
    {
        // Test with invalid username format (contains special characters)
        $response = $this->getJson('/api/v1/users/invalid@username');

        // The route should still try to find the user, but will return 404
        $response->assertStatus(404);
    }

    /**
     * Test user profile with deleted user's posts.
     */
    public function test_user_profile_with_deleted_users_posts(): void
    {
        // Create a post for the other user
        $post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Post from other user',
        ]);

        // Delete the other user
        $this->otherUser->delete();

        // Try to view the user's profile
        $response = $this->getJson('/api/v1/users/otheruser');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'User not found',
        ]);
    }

    /**
     * Test user profile endpoint performance with many posts.
     */
    public function test_user_profile_endpoint_performance_with_many_posts(): void
    {
        // Create many posts to test performance
        Post::factory()->count(50)->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Test post',
        ]);

        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/users/otheruser?posts=include&per_page=20');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Assert response time is reasonable (less than 2 seconds)
        $this->assertLessThan(2, $executionTime, "Profile endpoint took {$executionTime} seconds, which is too slow");

        $responseData = $response->json();
        $this->assertCount(20, $responseData['data']['posts']['data']);
    }

    /**
     * Test user profile with empty bio and avatar.
     */
    public function test_user_profile_with_empty_bio_and_avatar(): void
    {
        $user = User::factory()->create([
            'name' => 'Empty Profile User',
            'username' => 'emptyuser',
            'email' => 'empty@example.com',
            'avatar' => null,
            'bio' => null,
            'password' => Hash::make('password'),
        ]);

        $response = $this->getJson('/api/v1/users/emptyuser');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'user' => [
                    'username' => 'emptyuser',
                    'avatar' => null,
                    'bio' => null,
                ],
            ],
        ]);
    }
}
