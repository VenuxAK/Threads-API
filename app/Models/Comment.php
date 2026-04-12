<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use MongoDB\Laravel\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $connection = "mongodb";

    protected $collection = "comments";

    protected $fillable = [
        "content",
        "post_id",
        "user_id",
        "parent_id",
    ];

    protected $casts = [
        "created_at" => "datetime",
        "updated_at" => "datetime",
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->user_id)) {
                $model->user_id = Auth::id();
            }
        });

        static::created(function ($model) {
            try {
                // Increment comments count in PostMetaData
                PostMetaData::where('post_id', $model->post_id)
                    ->increment('comments_count');
            } catch (\Exception $e) {
                Log::error('Failed to increment comments_count for post: ' . $model->post_id, [
                    'error' => $e->getMessage(),
                    'post_id' => $model->post_id,
                    'comment_id' => $model->id
                ]);
            }
        });

        static::deleting(function ($model) {
            try {
                // Decrement comments count in PostMetaData
                PostMetaData::where('post_id', $model->post_id)
                    ->where('comments_count', '>', 0)
                    ->decrement('comments_count');
            } catch (\Exception $e) {
                Log::error('Failed to decrement comments_count for post: ' . $model->post_id, [
                    'error' => $e->getMessage(),
                    'post_id' => $model->post_id,
                    'comment_id' => $model->id
                ]);
            }
        });
    }

    /**
     * Get the user who created the comment
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent comment (for replies)
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the replies to this comment
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
