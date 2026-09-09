<?php

namespace App\GraphQL\Queries;

use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Transformers\PostTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL query resolver retrieving paginated notifications for the authenticated user.
 * Employs batch DataLoaders across MySQL users and MongoDB post documents to prevent N+1 queries.
 */
class NotificationsQuery
{
    public function __construct(
        private PostTransformer $postTransformer
    ) {}

    /**
     * Resolve paginated notifications with optional type filter.
     *
     * @param  null  $root
     * @param  array{page?: int, perPage?: int, type?: string}  $args
     * @return array<string, mixed>
     */
    public function __invoke($root, array $args): array
    {
        $userId = (int) Auth::id();
        $page = max($args['page'] ?? 1, 1);
        $perPage = min(max($args['perPage'] ?? 20, 1), 50);
        $typeFilter = $args['type'] ?? null;

        $query = Notification::where('user_id', $userId)->latest();

        if ($typeFilter) {
            $normalizedType = strtolower(trim($typeFilter));
            if ($normalizedType === 'replies' || $normalizedType === 'reply') {
                $query->where('type', 'comment');
            } elseif ($normalizedType === 'mentions') {
                $query->where('type', 'mention');
            } elseif ($normalizedType === 'follows') {
                $query->where('type', 'follow');
            } else {
                $query->where('type', $normalizedType);
            }
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $notifications = $paginator->getCollection();

        if ($notifications->isEmpty()) {
            return [
                'data' => [],
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ];
        }

        // Batch resolve MySQL senders
        $senderIds = $notifications->pluck('sender_id')->unique()->values()->all();
        $senders = User::whereIn('id', $senderIds)
            ->get(['id', 'name', 'username', 'avatar', 'bio'])
            ->keyBy('id');

        // Batch resolve MongoDB post entities for like, comment, repost, and mention alerts
        $postEntityIds = $notifications
            ->whereNotNull('entity_id')
            ->pluck('entity_id')
            ->unique()
            ->values()
            ->all();

        $postsMap = collect([]);
        if (! empty($postEntityIds)) {
            $mongoPosts = Post::whereIn('id', $postEntityIds)->get();
            if ($mongoPosts->isNotEmpty()) {
                $transformed = $this->postTransformer->transformPosts($mongoPosts);
                $postsMap = $transformed->keyBy(fn ($p) => (string) $p['id']);
            }
        }

        $data = $notifications->map(function ($notif) use ($senders, $postsMap) {
            $sender = $senders->get($notif->sender_id);
            $post = $notif->entity_id ? $postsMap->get((string) $notif->entity_id) : null;

            return [
                'id' => (string) $notif->id,
                'type' => $notif->type,
                'entity_id' => $notif->entity_id ? (string) $notif->entity_id : null,
                'read_at' => $notif->read_at ? $notif->read_at->toDateTimeString() : null,
                'created_at' => $notif->created_at->diffForHumans(),
                'sender' => $sender ? [
                    'id' => (string) $sender->id,
                    'name' => $sender->name,
                    'username' => $sender->username,
                    'avatar' => $sender->avatar,
                    'bio' => $sender->bio,
                ] : [
                    'id' => '0',
                    'name' => 'Threads User',
                    'username' => 'user',
                    'avatar' => null,
                    'bio' => null,
                ],
                'post' => $post,
            ];
        })->values()->all();

        return [
            'data' => $data,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
