<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    public function delete(User $user, Comment $comment): Response
    {
        return (string) $comment->user_id === (string) $user->id
            ? Response::allow()
            : Response::denyWithStatus(403);
    }
}
