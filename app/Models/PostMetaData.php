<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostMetaData extends Model
{
    use HasFactory;

    protected $fillable = [
        "post_id",
        "user_id",
        "likes_count",
        "comments_count",
        "shares_count",
        "visibility",
        "status",
        "scheduled_at",
        "expires_at"
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Log::debug($model);
            if (empty($model->user_id)) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, "user_id");
    }
}
