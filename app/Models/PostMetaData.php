<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostMetaData extends Model
{
    use HasFactory;

    protected $fillable = [
        "post_id",
        "user_id",
        "like_count",
        "comment_count",
        "shares_count",
        "visibility",
        "status",
        "scheduled_at",
        "expires_at"
    ];

    public function author()
    {
        return $this->belongsTo(User::class, "user_id");
    }
}
