<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchTest extends TestCase
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

        // Create test posts with various content
        Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'This is a post about programming and coding',
            'tags' => ['programming', 'coding'],
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Another post about Laravel framework',
            'tags' => ['laravel', 'php'],
        ]);

        Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Post about travel and adventure',
            'tags' => ['travel', 'adventure'],
        ]);

        Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Programming tips and tricks',
            'tags' => ['programming', 'tips'],
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Food recipe for dinner',
            'tags' => ['food', 'recipe', 'dinner'],
        ]);

        // Authenticate as the main user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test user can search posts by keyword.
     */
    public function test_user_can_search_posts_by_keyword(): void
    {
        $response = $this->postJson('/api/v1/search?posts=include', [
            'keyword' => 'programming',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'users' => [
                    '*' => [
                        'id',
                        'name',
                        'username',
                        'avatar',
                        'bio',
                    ],
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
                'search_metadata' => [
                    'query',
                    'tag_query',
                    'total_posts',
                    'total_users',
                    'search_method',
                ],
            ],
        ]);

        $responseData = $response->json();

        // Should find 2 posts with "programming" in content or tags
        $this->assertEquals(2, $responseData['pagination']['total']);

        // Verify posts contain the search term
        foreach ($responseData['results'] as $post) {
            $this->assertTrue(
                stripos($post['content'], 'programming') !== false ||
                in_array('programming', $post['tags']),
                "Post should contain 'programming' in content or tags"
            );
        }
    }

    /**
     * Test search returns empty results for non-matching query.
     */
    public function test_search_returns_empty_results_for_non_matching_query(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'nonexistentkeywordthatdoesnotexist',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(0, $responseData['pagination']['total']);
        $this->assertCount(0, $responseData['results']);
    }

    /**
     * Test search is case-insensitive.
     */
    public function test_search_is_case_insensitive(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'LARAVEL', // Uppercase
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(1, $responseData['pagination']['total']);
        $this->assertStringContainsStringIgnoringCase(
            'laravel',
            $responseData['results'][0]['content']
        );
    }

    /**
     * Test search by hashtag.
     */
    public function test_search_by_hashtag(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => '#travel',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(1, $responseData['pagination']['total']);
        $this->assertContains('travel', $responseData['results'][0]['tags']);
    }

    /**
     * Test search by multiple keywords.
     */
    public function test_search_by_multiple_keywords(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'programming tips',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should find posts containing either "programming" or "tips"
        $this->assertGreaterThanOrEqual(2, $responseData['pagination']['total']);
    }

    /**
     * Test search with pagination.
     */
    public function test_search_with_pagination(): void
    {
        // Create more posts to test pagination
        Post::factory()->count(15)->create([
            'user_id' => $this->user->id,
            'content' => 'Test post for pagination search',
        ]);

        $response = $this->postJson('/api/v1/search', [
            'query' => 'pagination',
            'per_page' => 5,
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(5, $responseData['pagination']['per_page']);
        $this->assertEquals(1, $responseData['pagination']['current_page']);
        $this->assertCount(5, $responseData['results']);
    }

    /**
     * Test search requires authentication.
     */
    public function test_search_requires_authentication(): void
    {
        // Log out
        Sanctum::actingAs($this->user, [], 'web');
        $this->post('/auth/logout');

        $response = $this->postJson('/api/v1/search', [
            'query' => 'test',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test search requires query parameter.
     */
    public function test_search_requires_query_parameter(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['query']);
    }

    /**
     * Test search query has maximum length.
     */
    public function test_search_query_has_maximum_length(): void
    {
        $longQuery = str_repeat('a', 256); // Exceeds 255 character limit

        $response = $this->postJson('/api/v1/search', [
            'query' => $longQuery,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['query']);
    }

    /**
     * Test search results include author information.
     */
    public function test_search_results_include_author_information(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'programming',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        foreach ($responseData['results'] as $post) {
            $this->assertArrayHasKey('author', $post);
            $this->assertArrayHasKey('name', $post['author']);
            $this->assertArrayHasKey('username', $post['author']);
            $this->assertArrayHasKey('avatar', $post['author']);
            $this->assertArrayHasKey('bio', $post['author']);
        }
    }

    /**
     * Test search results are ordered by relevance.
     */
    public function test_search_results_are_ordered_by_relevance(): void
    {
        // Create posts with different relevance scores
        Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'JavaScript programming language is awesome',
            'tags' => ['javascript', 'programming'],
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'I love programming in JavaScript',
            'tags' => ['programming', 'javascript'],
        ]);

        $response = $this->postJson('/api/v1/search', [
            'query' => 'JavaScript programming',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should have multiple results
        $this->assertGreaterThan(1, $responseData['pagination']['total']);

        // First result should be most relevant (contains both terms)
        $firstPost = $responseData['results'][0];
        $this->assertTrue(
            stripos($firstPost['content'], 'JavaScript') !== false &&
            stripos($firstPost['content'], 'programming') !== false,
            'First result should contain both search terms'
        );
    }

    /**
     * Test search with special characters.
     */
    public function test_search_with_special_characters(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'food & recipe',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should find the food recipe post
        $this->assertEquals(1, $responseData['pagination']['total']);
        $this->assertStringContainsStringIgnoringCase(
            'food',
            $responseData['results'][0]['content']
        );
    }

    /**
     * Test search by partial word.
     */
    public function test_search_by_partial_word(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'prog', // Partial of "programming"
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should find posts with "programming"
        $this->assertEquals(2, $responseData['pagination']['total']);
    }

    /**
     * Test search performance with many posts.
     */
    public function test_search_performance_with_many_posts(): void
    {
        // Create many posts to test performance
        Post::factory()->count(100)->create([
            'user_id' => $this->user->id,
            'content' => 'Test post content for performance testing',
        ]);

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/search', [
            'query' => 'performance',
            'per_page' => 20,
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Assert search time is reasonable (less than 1 second)
        $this->assertLessThan(1, $executionTime, "Search took {$executionTime} seconds, which is too slow");

        $responseData = $response->json();
        $this->assertCount(20, $responseData['results']);
    }

    /**
     * Test search across multiple users' posts.
     */
    public function test_search_across_multiple_users_posts(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'post',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should find all 5 test posts (they all contain "post" in content)
        $this->assertEquals(5, $responseData['pagination']['total']);

        // Verify posts from both users are included
        $userNames = array_unique(array_column($responseData['results'], 'author.name'));
        $this->assertContains('Test User', $userNames);
        $this->assertContains('Other User', $userNames);
    }

    /**
     * Test search with empty results returns proper structure.
     */
    public function test_search_with_empty_results_returns_proper_structure(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'nonexistent',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results' => [],
            'pagination' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
                'from',
                'to',
            ],
        ]);

        $responseData = $response->json();
        $this->assertEquals(0, $responseData['pagination']['total']);
        $this->assertEquals(0, $responseData['pagination']['from']);
        $this->assertEquals(0, $responseData['pagination']['to']);
    }

    /**
     * Test search query with SQL injection attempt is handled safely.
     */
    public function test_search_query_with_sql_injection_attempt_is_handled_safely(): void
    {
        // This should be blocked by WAF, but let's test the search endpoint handles it
        $response = $this->postJson('/api/v1/search', [
            'query' => "test' OR 1=1--",
        ]);

        // The WAF should block this, but if it doesn't, the search should handle it safely
        // Either 403 (blocked by WAF) or 200 (handled safely)
        $this->assertContains($response->status(), [200, 403]);

        if ($response->status() === 200) {
            // If not blocked by WAF, search should return empty or safe results
            $responseData = $response->json();
            $this->assertIsArray($responseData['results']);
        }
    }

    /**
     * Test search with very short query.
     */
    public function test_search_with_very_short_query(): void
    {
        $response = $this->postJson('/api/v1/search', [
            'query' => 'a',
        ]);

        $response->assertStatus(200);
        // Very short queries might return many results or be limited by search logic
        $responseData = $response->json();
        $this->assertIsArray($responseData['results']);
    }

    /**
     * Test search preserves hashtag formatting in results.
     */
    public function test_search_preserves_hashtag_formatting_in_results(): void
    {
        // Create a post with hashtags
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Post with #hashtag1 and #hashtag2',
            'tags' => ['hashtag1', 'hashtag2'],
        ]);

        $response = $this->postJson('/api/v1/search', [
            'query' => 'hashtag1',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(1, $responseData['pagination']['total']);
        $this->assertContains('hashtag1', $responseData['results'][0]['tags']);
        $this->assertStringContainsString('#hashtag1', $responseData['results'][0]['content']);
    }
}
