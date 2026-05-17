<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InteractionService;
use App\Utils\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostInteractionController extends Controller
{
    use Http;

    public function __construct(
        private InteractionService $interactionService,
    ) {}

    public function like(string $id)
    {
        try {
            $result = $this->interactionService->likePost($id, Auth::id());

            return $this->success([
                'likes_count' => $result->count,
                'liked' => $result->active,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to like post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to like post. Please try again.', 500);
        }
    }

    public function unlike(string $id)
    {
        try {
            $result = $this->interactionService->unlikePost($id, Auth::id());

            return $this->success([
                'likes_count' => $result->count,
                'liked' => $result->active,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('Failed to unlike post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to unlike post. Please try again.', 500);
        }
    }

    public function checkLike(string $id)
    {
        try {
            $liked = $this->interactionService->checkLike($id, Auth::id());

            return $this->success([
                'liked' => $liked,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check like status', [
                'error' => $e->getMessage(),
                'post_id' => $id,
            ]);

            return $this->error('Failed to check like status. Please try again.', 500);
        }
    }

    public function share(string $id)
    {
        try {
            $sharesCount = $this->interactionService->sharePost($id);

            return $this->success([
                'shares_count' => $sharesCount,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to share post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to share post. Please try again.', 500);
        }
    }

    public function repost(string $id)
    {
        try {
            $result = $this->interactionService->toggleRepost($id, Auth::id());

            return $this->success([
                'reposts_count' => $result->count,
                'reposted' => $result->active,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle repost', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to repost. Please try again.', 500);
        }
    }

    public function checkRepost(string $id)
    {
        try {
            $reposted = $this->interactionService->checkRepost($id, Auth::id());

            return $this->success([
                'reposted' => $reposted,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check repost status', [
                'error' => $e->getMessage(),
                'post_id' => $id,
            ]);

            return $this->error('Failed to check repost status. Please try again.', 500);
        }
    }

    public function interactions(string $id)
    {
        try {
            $counts = $this->interactionService->getInteractions($id);

            return $this->success($counts);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to get interactions', [
                'error' => $e->getMessage(),
                'post_id' => $id,
            ]);

            return $this->error('Failed to get interactions. Please try again.', 500);
        }
    }
}
