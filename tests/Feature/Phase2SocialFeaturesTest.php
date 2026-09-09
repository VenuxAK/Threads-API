<?php

namespace Tests\Feature;

use App\Actions\CreateCommentAction;
use App\Actions\FollowUserAction;
use App\Actions\LikePostAction;
use App\Actions\RepostPostAction;
use App\Actions\UnfollowUserAction;
use App\Models\Follow;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test suite covering Phase 2 Social Features:
 * 1. Follow / Unfollow System & Following Feeds
 * 2. In-App Notification Engine & Unread Metrics
 * 3. Saved / Bookmarked Posts & Flag Hydration
 */
class Phase2SocialFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean up MongoDB test documents
        Post::truncate();
        parent::tearDown();
    }

    public function test_user_can_follow_and_unfollow_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $followAction = app(FollowUserAction::class);
        $result = $followAction->execute($userA->id, $userB->id);

        $this->assertTrue($result->status);
        $this->assertEquals(1, $result->followers_count);
        $this->assertDatabaseHas('follows', [
            'follower_id' => $userA->id,
            'following_id' => $userB->id,
        ]);

        // Follow notification dispatched to user B
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userB->id,
            'sender_id' => $userA->id,
            'type' => 'follow',
        ]);

        // Unfollow
        $unfollowAction = app(UnfollowUserAction::class);
        $unfollowResult = $unfollowAction->execute($userA->id, $userB->id);

        $this->assertFalse($unfollowResult->status);
        $this->assertEquals(0, $unfollowResult->followers_count);
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $userA->id,
            'following_id' => $userB->id,
        ]);
    }

    public function test_following_feed_only_includes_followed_authors(): void
    {
        $userA = User::factory()->create();
        $followedUser = User::factory()->create();
        $unfollowedUser = User::factory()->create();

        // Establish follow
        Follow::create([
            'follower_id' => $userA->id,
            'following_id' => $followedUser->id,
        ]);

        $postService = app(PostService::class);
        $followedPost = $postService->createPost('Hello from followed friend!', $followedUser->id);
        $unfollowedPost = $postService->createPost('Hello from stranger!', $unfollowedUser->id);

        $this->actingAs($userA);

        $response = $this->postJson('/graphql', [
            'query' => '
                query {
                    followingFeed(page: 1, perPage: 10) {
                        data {
                            id
                            content
                            author {
                                id
                                username
                                is_following
                            }
                        }
                        pagination {
                            total
                        }
                    }
                }
            ',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.followingFeed.data');
        $this->assertCount(1, $data);
        $this->assertEquals($followedPost->id, $data[0]['id']);
        $this->assertTrue($data[0]['author']['is_following']);
    }

    public function test_interactions_dispatch_notifications_and_manage_read_states(): void
    {
        $author = User::factory()->create();
        $interactor = User::factory()->create();

        $postService = app(PostService::class);
        $post = $postService->createPost('Discussion starter #threads', $author->id);

        // Like post
        $likeAction = app(LikePostAction::class);
        $likeAction->execute($post->id, $interactor->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $author->id,
            'sender_id' => $interactor->id,
            'type' => 'like',
            'entity_id' => $post->id,
        ]);

        // Comment with mention
        $mentionedUser = User::factory()->create(['username' => 'alice']);
        $commentAction = app(CreateCommentAction::class);
        $commentAction->execute('Hey @alice nice post!', $post->id, $interactor->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $author->id,
            'sender_id' => $interactor->id,
            'type' => 'comment',
            'entity_id' => $post->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $mentionedUser->id,
            'sender_id' => $interactor->id,
            'type' => 'mention',
            'entity_id' => $post->id,
        ]);

        // Repost
        $repostAction = app(RepostPostAction::class);
        $repostAction->execute($post->id, $interactor->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $author->id,
            'sender_id' => $interactor->id,
            'type' => 'repost',
            'entity_id' => $post->id,
        ]);

        // Query unread notifications via GraphQL
        $this->actingAs($author);

        $response = $this->postJson('/graphql', [
            'query' => '
                query {
                    unreadNotificationsCount
                    notifications(page: 1, perPage: 10) {
                        data {
                            id
                            type
                            read_at
                            sender {
                                username
                            }
                            post {
                                id
                            }
                        }
                    }
                }
            ',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.unreadNotificationsCount'));
        $notifs = $response->json('data.notifications.data');
        $this->assertCount(3, $notifs);

        // Mark single notification read
        $notifId = $notifs[0]['id'];
        $markResponse = $this->postJson('/graphql', [
            'query' => '
                mutation MarkRead($id: ID!) {
                    markNotificationAsRead(id: $id)
                }
            ',
            'variables' => ['id' => $notifId],
        ]);
        $markResponse->assertStatus(200);
        $this->assertTrue($markResponse->json('data.markNotificationAsRead'));

        // Mark all as read
        $markAllResponse = $this->postJson('/graphql', [
            'query' => '
                mutation {
                    markAllNotificationsAsRead
                }
            ',
        ]);
        $markAllResponse->assertStatus(200);
        $this->assertTrue($markAllResponse->json('data.markAllNotificationsAsRead'));

        // Check unread count is now 0
        $unreadCheck = $this->postJson('/graphql', [
            'query' => '{ unreadNotificationsCount }',
        ]);
        $this->assertEquals(0, $unreadCheck->json('data.unreadNotificationsCount'));
    }

    public function test_user_can_save_and_unsave_posts_and_query_my_saved_posts(): void
    {
        $user = User::factory()->create();
        $postService = app(PostService::class);
        $post = $postService->createPost('Valuable tutorial post #knowledge', $user->id);

        $this->actingAs($user);

        // Save post
        $saveResponse = $this->postJson('/graphql', [
            'query' => '
                mutation Save($postId: ID!) {
                    savePost(postId: $postId)
                }
            ',
            'variables' => ['postId' => (string) $post->id],
        ]);
        $saveResponse->assertStatus(200);
        $this->assertTrue($saveResponse->json('data.savePost'));
        $this->assertDatabaseHas('saved_posts', [
            'user_id' => $user->id,
            'post_id' => (string) $post->id,
        ]);

        // Query mySavedPosts and check is_saved flag
        $queryResponse = $this->postJson('/graphql', [
            'query' => '
                query {
                    mySavedPosts(page: 1, perPage: 10) {
                        data {
                            id
                            is_saved
                        }
                        pagination {
                            total
                        }
                    }
                }
            ',
        ]);

        $queryResponse->assertStatus(200);
        $savedData = $queryResponse->json('data.mySavedPosts.data');
        $this->assertCount(1, $savedData);
        $this->assertEquals((string) $post->id, $savedData[0]['id']);
        $this->assertTrue($savedData[0]['is_saved']);

        // Unsave post
        $unsaveResponse = $this->postJson('/graphql', [
            'query' => '
                mutation Unsave($postId: ID!) {
                    unsavePost(postId: $postId)
                }
            ',
            'variables' => ['postId' => (string) $post->id],
        ]);
        $unsaveResponse->assertStatus(200);
        $this->assertTrue($unsaveResponse->json('data.unsavePost'));
        $this->assertDatabaseMissing('saved_posts', [
            'user_id' => $user->id,
            'post_id' => (string) $post->id,
        ]);
    }
}
